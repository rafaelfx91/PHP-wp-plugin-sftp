<?php
if (!defined('ABSPATH')) exit;

class DEVTIFTP_FTP {
    private $host;
    private $port;
    private $username;
    private $password;
    private $conn;

    public function __construct($settings) {
        $this->host = sanitize_text_field($settings['host']);
        $this->port = intval($settings['port']);
        $this->username = sanitize_text_field($settings['username']);
        $this->password = $settings['password']; // Senha não deve ser sanitizada para evitar problemas com caracteres especiais
    }

    public function test_connection() {
        $this->connect();
        $success = ($this->conn !== false);
        $this->disconnect();
        return $success;
    }

    public function connect() {
        if (!function_exists('ftp_connect')) {
            throw new Exception(__('FTP functions are not available on this server.', 'devti-ftp'));
        }

        $this->conn = @ftp_connect($this->host, $this->port, 30);
        
        if (!$this->conn) {
            throw new Exception(__('Could not connect to FTP host.', 'devti-ftp'));
        }

        $login = @ftp_login($this->conn, $this->username, $this->password);
        
        if (!$login) {
            $this->disconnect();
            throw new Exception(__('FTP login failed. Please check your username and password.', 'devti-ftp'));
        }

        // Ativar modo passivo para melhor compatibilidade
        ftp_pasv($this->conn, true);
        
        return true;
    }

    public function disconnect() {
        if ($this->conn) {
            @ftp_close($this->conn);
            $this->conn = null;
        }
    }

    public function upload_file($local_path, $remote_path) {
        if (!$this->conn) {
            $this->connect();
        }

        $upload = @ftp_put($this->conn, $remote_path, $local_path, FTP_BINARY);
        
        if (!$upload) {
            throw new Exception(sprintf(__('Failed to upload file: %s', 'devti-ftp'), $local_path);
        }
        
        return true;
    }

    public function __destruct() {
        $this->disconnect();
    }
}