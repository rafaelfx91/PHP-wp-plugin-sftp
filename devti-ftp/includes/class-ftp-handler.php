<?php
if (!class_exists('DEVTIFTP_FTP_Handler')) {
    class DEVTIFTP_FTP_Handler {
        private $connection;
        private $settings;
        private $is_sftp;
        
        public function __construct($settings) {
            $this->settings = $settings;
            $this->is_sftp = ($settings['connection_type'] === 'sftp');
        }
        
        public function connect() {
            try {
                if ($this->is_sftp) {
                    if (!function_exists('ssh2_connect')) {
                        throw new Exception('Extensão SSH2 não está disponível no servidor.');
                    }
                    
                    $this->connection = ssh2_connect($this->settings['host'], $this->settings['port']);
                    if (!$this->connection) {
                        throw new Exception('Não foi possível conectar ao servidor SFTP.');
                    }
                    
                    if (!ssh2_auth_password($this->connection, $this->settings['username'], $this->settings['password'])) {
                        throw new Exception('Autenticação SFTP falhou.');
                    }
                    
                    DEVTIFTP_Logger::log('Conexão SFTP estabelecida com sucesso.');
                    return true;
                } else {
                    $this->connection = ftp_connect($this->settings['host'], $this->settings['port']);
                    if (!$this->connection) {
                        throw new Exception('Não foi possível conectar ao servidor FTP.');
                    }
                    
                    if (!ftp_login($this->connection, $this->settings['username'], $this->settings['password'])) {
                        throw new Exception('Autenticação FTP falhou.');
                    }
                    
                    ftp_pasv($this->connection, true); // Modo passivo
                    DEVTIFTP_Logger::log('Conexão FTP estabelecida com sucesso.');
                    return true;
                }
            } catch (Exception $e) {
                DEVTIFTP_Logger::log('Erro de conexão: ' . $e->getMessage(), 'ERROR');
                return new WP_Error('connection_error', $e->getMessage());
            }
        }
        
        public function disconnect() {
            if ($this->is_sftp) {
                // Não há função explícita para desconectar SFTP
                $this->connection = null;
            } else {
                if ($this->connection) {
                    ftp_close($this->connection);
                }
            }
        }
        
        public function test_connection() {
            $result = $this->connect();
            
            if ($result === true) {
                $this->disconnect();
                return true;
            }
            
            return $result;
        }
        
        public function upload_file($local_file, $remote_file) {
            if (!$this->connection && !$this->connect()) {
                return new WP_Error('not_connected', 'Não conectado ao servidor.');
            }
            
            try {
                if ($this->is_sftp) {
                    $sftp = ssh2_sftp($this->connection);
                    $remote_path = $this->settings['path'] . '/' . $remote_file;
                    $stream = fopen("ssh2.sftp://{$sftp}{$remote_path}", 'w');
                    
                    if (!$stream) {
                        throw new Exception('Não foi possível abrir o arquivo remoto.');
                    }
                    
                    $local_data = file_get_contents($local_file);
                    if ($local_data === false) {
                        throw new Exception('Não foi possível ler o arquivo local.');
                    }
                    
                    if (fwrite($stream, $local_data) {
                        fclose($stream);
                        DEVTIFTP_Logger::log("Arquivo {$local_file} enviado para {$remote_path} via SFTP.");
                        return true;
                    } else {
                        throw new Exception('Falha ao escrever no arquivo remoto.');
                    }
                } else {
                    $remote_path = $this->settings['path'] . '/' . $remote_file;
                    if (ftp_put($this->connection, $remote_path, $local_file, FTP_BINARY)) {
                        DEVTIFTP_Logger::log("Arquivo {$local_file} enviado para {$remote_path} via FTP.");
                        return true;
                    } else {
                        throw new Exception('Falha ao enviar arquivo via FTP.');
                    }
                }
            } catch (Exception $e) {
                DEVTIFTP_Logger::log('Erro ao enviar arquivo: ' . $e->getMessage(), 'ERROR');
                return new WP_Error('upload_error', $e->getMessage());
            }
        }
    }
}