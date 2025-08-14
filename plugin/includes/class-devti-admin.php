<?php
declare(strict_types=1);

namespace Devti\SFTPUploader;

defined('ABSPATH') || exit;

class Admin {

	const OPTION_KEY = 'devti_sftp_settings';
	const NONCE_ACTION = 'devti_sftp_nonce_action';
	const NONCE_FIELD = 'devti_sftp_nonce_field';

	/**
	 * Registra hooks do admin.
	 */
	public function hooks(): void {
		add_action('admin_menu', [$this, 'add_menu_page']);
		add_action('admin_init', [$this, 'register_settings']);

		// Assets
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

		// AJAX
		add_action('wp_ajax_devti_sftp_test', [$this, 'ajax_test_connection']);
		add_action('wp_ajax_devti_sftp_send', [$this, 'ajax_send_first_file']);
	}

	public function add_menu_page(): void {
		add_management_page(
			__('SFTP Uploader (DevTI)', 'devti-plugin'),
			__('SFTP Uploader (DevTI)', 'devti-plugin'),
			'manage_options',
			'devti-sftp-uploader',
			[$this, 'render_page']
		);
	}

	public function register_settings(): void {
		register_setting(
			'devti_sftp_group',
			self::OPTION_KEY,
			[
				'type'              => 'array',
				'sanitize_callback' => [$this, 'sanitize_settings'],
				'show_in_rest'      => false,
				'default'           => [
					'host'        => '',
					'port'        => 22,
					'username'    => '',
					'password'    => '',
					'remote_path' => '',
					'local_dir'   => '/public_html/wp-content/ai1wm-backups',
					'extension'   => 'wpress',
				],
			]
		);
	}

	/**
	 * Enfileira JS/CSS apenas na página do plugin.
	 */
	public function enqueue_assets(string $hook): void {
		if ($hook !== 'tools_page_devti-sftp-uploader') {
			return;
		}
		wp_enqueue_script(
			'devti-admin',
			DEVTI_PLUGIN_URL . 'assets/js/admin.js',
			['jquery'],
			DEVTI_PLUGIN_VERSION,
			true
		);
		wp_localize_script(
			'devti-admin',
			'DEVTI_SFTP',
			[
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce(self::NONCE_ACTION),
				'strings'  => [
					'testing'   => __('Testando conexão...', 'devti-plugin'),
					'sending'   => __('Enviando arquivo...', 'devti-plugin'),
					'success'   => __('Sucesso', 'devti-plugin'),
					'error'     => __('Erro', 'devti-plugin'),
				],
			]
		);
	}

	/**
	 * Sanitiza configurações vindas do formulário.
	 */
	public function sanitize_settings(array $input): array {
		$sanitized = [
			'host'        => isset($input['host']) ? sanitize_text_field((string) $input['host']) : '',
			'port'        => isset($input['port']) ? (int) $input['port'] : 22,
			'username'    => isset($input['username']) ? sanitize_text_field((string) $input['username']) : '',
			'password'    => isset($input['password']) ? (string) $input['password'] : '',
			'remote_path' => isset($input['remote_path']) ? sanitize_text_field((string) $input['remote_path']) : '',
			'local_dir'   => isset($input['local_dir']) ? rtrim((string) $input['local_dir']) : '',
			'extension'   => isset($input['extension']) ? ltrim((string) $input['extension']) : 'wpress',
		];

		// Garante extensão sem ponto inicial (ex.: "wpress")
		$sanitized['extension'] = ltrim($sanitized['extension'], '.');

		return $sanitized;
	}

	/**
	 * Renderiza a página de configurações.
	 */
	public function render_page(): void {
		if (!current_user_can('manage_options')) {
			wp_die(__('Acesso negado.', 'devti-plugin'));
		}

		$options = get_option(self::OPTION_KEY);
		$options = wp_parse_args(
			$options,
			[
				'host'        => '',
				'port'        => 22,
				'username'    => '',
				'password'    => '',
				'remote_path' => '',
				'local_dir'   => '/public_html/wp-content/ai1wm-backups',
				'extension'   => 'wpress',
			]
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e('SFTP Uploader (DevTI)', 'devti-plugin'); ?></h1>
			<p><?php esc_html_e('Configure a conexão SFTP e o diretório local/extension para envio de arquivos.', 'devti-plugin'); ?></p>

			<form method="post" action="options.php">
				<?php
				settings_fields('devti_sftp_group');
				?>
				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row"><label for="devti_host"><?php esc_html_e('Host', 'devti-plugin'); ?></label></th>
						<td><input name="<?php echo esc_attr(self::OPTION_KEY); ?>[host]" type="text" id="devti_host" value="<?php echo esc_attr($options['host']); ?>" class="regular-text" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="devti_port"><?php esc_html_e('Porta', 'devti-plugin'); ?></label></th>
						<td><input name="<?php echo esc_attr(self::OPTION_KEY); ?>[port]" type="number" id="devti_port" value="<?php echo esc_attr((string) $options['port']); ?>" class="small-text" min="1" max="65535" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="devti_username"><?php esc_html_e('Usuário', 'devti-plugin'); ?></label></th>
						<td><input name="<?php echo esc_attr(self::OPTION_KEY); ?>[username]" type="text" id="devti_username" value="<?php echo esc_attr($options['username']); ?>" class="regular-text" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="devti_password"><?php esc_html_e('Senha', 'devti-plugin'); ?></label></th>
						<td><input name="<?php echo esc_attr(self::OPTION_KEY); ?>[password]" type="password" id="devti_password" value="<?php echo esc_attr($options['password']); ?>" class="regular-text" autocomplete="new-password"></td>
					</tr>
					<tr>
						<th scope="row"><label for="devti_remote_path"><?php esc_html_e('Pasta Remota (SFTP)', 'devti-plugin'); ?></label></th>
						<td><input name="<?php echo esc_attr(self::OPTION_KEY); ?>[remote_path]" type="text" id="devti_remote_path" value="<?php echo esc_attr($options['remote_path']); ?>" class="regular-text" placeholder="/uploads/backups"></td>
					</tr>
					<tr>
						<th scope="row"><label for="devti_local_dir"><?php esc_html_e('Diretório Local (absoluto)', 'devti-plugin'); ?></label></th>
						<td>
							<input name="<?php echo esc_attr(self::OPTION_KEY); ?>[local_dir]" type="text" id="devti_local_dir" value="<?php echo esc_attr($options['local_dir']); ?>" class="regular-text" required>
							<p class="description"><?php esc_html_e('Ex.: /public_html/wp-content/ai1wm-backups (precisa estar dentro da instalação do WordPress).', 'devti-plugin'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="devti_extension"><?php esc_html_e('Extensão do arquivo (sem ponto)', 'devti-plugin'); ?></label></th>
						<td>
							<input name="<?php echo esc_attr(self::OPTION_KEY); ?>[extension]" type="text" id="devti_extension" value="<?php echo esc_attr($options['extension']); ?>" class="small-text" required>
							<p class="description"><?php esc_html_e('Ex.: wpress', 'devti-plugin'); ?></p>
						</td>
					</tr>
					</tbody>
				</table>
				<?php submit_button(__('Salvar Configurações', 'devti-plugin')); ?>
			</form>

			<hr>

			<h2><?php esc_html_e('Ações', 'devti-plugin'); ?></h2>
			<p>
				<button id="devti-test-conn" class="button button-secondary"><?php esc_html_e('Testar Conexão SFTP', 'devti-plugin'); ?></button>
				<button id="devti-send-file" class="button button-primary"><?php esc_html_e('Enviar Primeiro Arquivo', 'devti-plugin'); ?></button>
			</p>
			<div id="devti-result" style="margin-top:10px;"></div>
		</div>
		<?php
	}

	/**
	 * AJAX: test connection
	 */
	public function ajax_test_connection(): void {
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('Permissão negada.', 'devti-plugin')], 403);
		}
		check_ajax_referer(self::NONCE_ACTION, 'nonce');

		$options = get_option(self::OPTION_KEY, []);
		$uploader = new SFTPUploader();
		$result = $uploader->test_connection($options);

		if (is_wp_error($result)) {
			wp_send_json_error(['message' => $result->get_error_message()]);
		}

		wp_send_json_success(['message' => __('Conexão SFTP bem-sucedida!', 'devti-plugin')]);
	}

	/**
	 * AJAX: send first file
	 */
	public function ajax_send_first_file(): void {
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('Permissão negada.', 'devti-plugin')], 403);
		}
		check_ajax_referer(self::NONCE_ACTION, 'nonce');

		$options = get_option(self::OPTION_KEY, []);
		$uploader = new SFTPUploader();
		$result = $uploader->send_first_file($options);

		if (is_wp_error($result)) {
			wp_send_json_error(['message' => $result->get_error_message()]);
		}

		wp_send_json_success([
			'message' => sprintf(
				/* translators: 1: filename, 2: dest path, 3: method */
				__('Arquivo "%1$s" enviado para "%2$s" via %3$s e removido localmente.', 'devti-plugin'),
				$result['file'] ?? '',
				$result['to'] ?? '',
				$result['method'] ?? 'sftp'
			),
			'details' => $result,
		]);
	}
}
