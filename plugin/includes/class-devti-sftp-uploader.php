<?php
declare(strict_types=1);

namespace Devti\SFTPUploader;

use WP_Error;

defined('ABSPATH') || exit;

class SFTPUploader {

	/**
	 * Testa a conexão SFTP com as credenciais fornecidas.
	 *
	 * @param array $settings
	 * @return true|WP_Error
	 */
	public function test_connection(array $settings) {
		$settings = $this->sanitize_settings($settings);

		// Tenta phpseclib 3
		/*if (class_exists('\phpseclib3\Net\SFTP')) {
			try {
				$sftp = new \phpseclib3\Net\SFTP($settings['host'], (int) $settings['port'], 10);
				if (!$sftp->login($settings['username'], $settings['password'])) {
					return new WP_Error('devti_login_failed', __('Falha ao autenticar no SFTP.', 'devti-plugin'));
				}
				// Tenta mudar para a pasta remota (se fornecida)
				if (!empty($settings['remote_path'])) {
					if (!$sftp->chdir($settings['remote_path'])) {
						return new WP_Error('devti_remote_path', __('Não foi possível acessar a pasta remota configurada.', 'devti-plugin'));
					}
				}
				return true;
			} catch (\Throwable $e) {
				return new WP_Error('devti_sftp_error', sprintf(__('Erro SFTP (phpseclib): %s', 'devti-plugin'), $e->getMessage()));
			}
		}*/
        // Carrega phpseclib embutido se a classe não existir
            // Carregar phpseclib interno, caso não exista
            if ( ! class_exists('\phpseclib3\Net\SFTP') ) {
                require_once plugin_dir_path(__FILE__) . 'includes/phpseclib/autoload.php';
            }


		// Fallback: extensão ssh2
		if (function_exists('ssh2_connect')) {
			try {
				$connection = @ssh2_connect($settings['host'], (int) $settings['port']);
				if (!$connection) {
					return new WP_Error('devti_connect_failed', __('Falha ao conectar ao host SFTP (ssh2).', 'devti-plugin'));
				}
				if (!@ssh2_auth_password($connection, $settings['username'], $settings['password'])) {
					return new WP_Error('devti_login_failed', __('Falha ao autenticar no SFTP (ssh2).', 'devti-plugin'));
				}
				$sftp = @ssh2_sftp($connection);
				if (!$sftp) {
					return new WP_Error('devti_sftp_failed', __('Falha ao inicializar sessão SFTP (ssh2).', 'devti-plugin'));
				}
				if (!empty($settings['remote_path'])) {
					$remote_dir = 'ssh2.sftp://' . intval($sftp) . $settings['remote_path'];
					if (!@is_dir($remote_dir)) {
						return new WP_Error('devti_remote_path', __('Não foi possível acessar a pasta remota configurada (ssh2).', 'devti-plugin'));
					}
				}
				return true;
			} catch (\Throwable $e) {
				return new WP_Error('devti_ssh2_error', sprintf(__('Erro SFTP (ssh2): %s', 'devti-plugin'), $e->getMessage()));
			}
		}

		return new WP_Error(
			'devti_no_transport',
			sprintf(
				/* translators: %s: Composer command */
				__('Nenhum transporte SFTP disponível. Instale phpseclib: %s (na pasta do plugin) ou habilite a extensão ssh2.', 'devti-plugin'),
				'<code>composer require phpseclib/phpseclib:^3</code>'
			)
		);
	}

	/**
	 * Envia o primeiro arquivo encontrado e remove o original após sucesso.
	 *
	 * @param array $settings
	 * @return array|WP_Error  Retorna informações do envio (arquivo, destino) ou WP_Error.
	 */
	public function send_first_file(array $settings) {
		$settings = $this->sanitize_settings($settings);

		// Valida diretório local
		$local_dir = rtrim($settings['local_dir'], '/');
		if (!is_dir($local_dir)) {
			return new WP_Error('devti_local_dir', __('Diretório local não existe.', 'devti-plugin'));
		}

		// (Segurança) Garante que o diretório esteja dentro da instalação do WP.
		$real_local = realpath($local_dir);
		$real_root  = realpath(ABSPATH);
		if ($real_local === false || $real_root === false || strpos($real_local, $real_root) !== 0) {
			return new WP_Error('devti_outside_wp', __('Diretório local precisa estar dentro da instalação do WordPress.', 'devti-plugin'));
		}

		// Extensão (com ou sem ponto)
		$ext = ltrim($settings['extension'], '.');
		if ($ext === '') {
			return new WP_Error('devti_extension', __('Extensão do arquivo inválida.', 'devti-plugin'));
		}

		// Procura o primeiro arquivo com a extensão
		$pattern = $local_dir . '/*.' . $ext;
		$files = glob($pattern, GLOB_NOSORT);
		if (!$files || empty($files)) {
			return new WP_Error('devti_no_files', __('Nenhum arquivo encontrado com a extensão configurada.', 'devti-plugin'));
		}

		// Seleciona o "primeiro" — aqui, vamos pegar pelo menor mtime (mais antigo)
		usort($files, static function ($a, $b) {
			return filemtime($a) <=> filemtime($b);
		});
		$file_path = $files[0];
		$filename  = basename($file_path);

		// Tenta phpseclib 3
		if (class_exists('\phpseclib3\Net\SFTP')) {
			try {
				$sftp = new \phpseclib3\Net\SFTP($settings['host'], (int) $settings['port'], 15);
				if (!$sftp->login($settings['username'], $settings['password'])) {
					return new WP_Error('devti_login_failed', __('Falha ao autenticar no SFTP.', 'devti-plugin'));
				}
				if (!empty($settings['remote_path']) && !$sftp->chdir($settings['remote_path'])) {
					return new WP_Error('devti_remote_path', __('Não foi possível acessar a pasta remota configurada.', 'devti-plugin'));
				}
				$remote_dest = (empty($settings['remote_path']) ? '' : rtrim($settings['remote_path'], '/') . '/') . $filename;

				// Envia
				$stream = @fopen($file_path, 'rb');
				if (!$stream) {
					return new WP_Error('devti_open_local', __('Não foi possível abrir o arquivo local para leitura.', 'devti-plugin'));
				}
				$put = $sftp->put($remote_dest, $stream);
				@fclose($stream);

				if (!$put) {
					return new WP_Error('devti_put_failed', __('Falha ao enviar o arquivo via SFTP.', 'devti-plugin'));
				}

				// Remove local após sucesso
				if (!@unlink($file_path)) {
					return new WP_Error('devti_unlink_failed', __('Arquivo enviado, mas não foi possível remover o arquivo local.', 'devti-plugin'));
				}

				return [
					'file'   => $filename,
					'from'   => $file_path,
					'to'     => $remote_dest,
					'method' => 'phpseclib3',
				];
			} catch (\Throwable $e) {
				return new WP_Error('devti_sftp_error', sprintf(__('Erro SFTP (phpseclib): %s', 'devti-plugin'), $e->getMessage()));
			}
		}

		// Fallback ssh2
		if (function_exists('ssh2_connect')) {
			try {
				$connection = @ssh2_connect($settings['host'], (int) $settings['port']);
				if (!$connection) {
					return new WP_Error('devti_connect_failed', __('Falha ao conectar ao host SFTP (ssh2).', 'devti-plugin'));
				}
				if (!@ssh2_auth_password($connection, $settings['username'], $settings['password'])) {
					return new WP_Error('devti_login_failed', __('Falha ao autenticar no SFTP (ssh2).', 'devti-plugin'));
				}
				$sftp = @ssh2_sftp($connection);
				if (!$sftp) {
					return new WP_Error('devti_sftp_failed', __('Falha ao inicializar sessão SFTP (ssh2).', 'devti-plugin'));
				}

				$remote_base = 'ssh2.sftp://' . intval($sftp);
				$remote_dir  = (empty($settings['remote_path']) ? '' : rtrim($settings['remote_path'], '/'));
				if ($remote_dir !== '' && !@is_dir($remote_base . $remote_dir)) {
					return new WP_Error('devti_remote_path', __('Não foi possível acessar a pasta remota configurada (ssh2).', 'devti-plugin'));
				}

				$remote_dest = $remote_base . ( $remote_dir === '' ? '' : $remote_dir . '/' ) . $filename;
				$src = @fopen($file_path, 'rb');
				if (!$src) {
					return new WP_Error('devti_open_local', __('Não foi possível abrir o arquivo local para leitura.', 'devti-plugin'));
				}
				$dst = @fopen($remote_dest, 'wb');
				if (!$dst) {
					@fclose($src);
					return new WP_Error('devti_open_remote', __('Não foi possível abrir o destino remoto para escrita.', 'devti-plugin'));
				}

				$ok = stream_copy_to_stream($src, $dst) > 0;
				@fclose($src);
				@fclose($dst);

				if (!$ok) {
					return new WP_Error('devti_put_failed', __('Falha ao enviar o arquivo via SFTP (ssh2).', 'devti-plugin'));
				}

				if (!@unlink($file_path)) {
					return new WP_Error('devti_unlink_failed', __('Arquivo enviado, mas não foi possível remover o arquivo local.', 'devti-plugin'));
				}

				return [
					'file'   => $filename,
					'from'   => $file_path,
					'to'     => ( $remote_dir === '' ? '/' : $remote_dir . '/' ) . $filename,
					'method' => 'ssh2',
				];
			} catch (\Throwable $e) {
				return new WP_Error('devti_ssh2_error', sprintf(__('Erro SFTP (ssh2): %s', 'devti-plugin'), $e->getMessage()));
			}
		}

		return new WP_Error(
			'devti_no_transport',
			sprintf(
				__('Nenhum transporte SFTP disponível. Instale phpseclib: %s (na pasta do plugin) ou habilite a extensão ssh2.', 'devti-plugin'),
				'<code>composer require phpseclib/phpseclib:^3</code>'
			)
		);
	}

	/**
	 * Sanitiza e normaliza configurações.
	 *
	 * @param array $settings
	 * @return array
	 */
	private function sanitize_settings(array $settings): array {
		return [
			'host'        => isset($settings['host']) ? sanitize_text_field((string) $settings['host']) : '',
			'port'        => isset($settings['port']) ? (int) $settings['port'] : 22,
			'username'    => isset($settings['username']) ? sanitize_text_field((string) $settings['username']) : '',
			'password'    => isset($settings['password']) ? (string) $settings['password'] : '',
			'remote_path' => isset($settings['remote_path']) ? sanitize_text_field((string) $settings['remote_path']) : '',
			'local_dir'   => isset($settings['local_dir']) ? rtrim((string) $settings['local_dir']) : '',
			'extension'   => isset($settings['extension']) ? ltrim((string) $settings['extension']) : 'wpress',
		];
	}
}
