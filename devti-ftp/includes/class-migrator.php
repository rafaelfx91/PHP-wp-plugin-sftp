<?php
if (!defined('ABSPATH')) exit;

class DEVTIFTP_Migrator {
    private $settings;
    private $ftp;

    public function __construct($settings) {
        $this->settings = $settings;
        $this->ftp = new DEVTIFTP_FTP($settings);
    }

    public function migrate_files() {
        $local_path = trailingslashit(ABSPATH . $this->settings['path']);
        $extension = $this->settings['extension'];
        
        // Verificar se o diretório existe
        if (!is_dir($local_path)) {
            return array(
                'success' => false,
                'message' => __('Local directory does not exist.', 'devti-ftp')
            );
        }

        // Buscar arquivos com a extensão especificada
        $files = glob($local_path . '*.' . $extension);
        
        if (empty($files)) {
            return array(
                'success' => false,
                'message' => __('No files found with the specified extension.', 'devti-ftp')
            );
        }

        $this->ftp->connect();
        
        $success_count = 0;
        $error_messages = array();
        
        foreach ($files as $file) {
            $filename = basename($file);
            
            try {
                $this->ftp->upload_file($file, $filename);
                
                // Se o upload for bem-sucedido, excluir o arquivo local
                if (@unlink($file)) {
                    $success_count++;
                } else {
                    $error_messages[] = sprintf(__('File uploaded but could not be deleted: %s', 'devti-ftp'), $filename);
                }
            } catch (Exception $e) {
                $error_messages[] = $e->getMessage();
            }
        }
        
        $this->ftp->disconnect();
        
        if ($success_count > 0) {
            $message = sprintf(
                _n('%d file migrated successfully.', '%d files migrated successfully.', $success_count, 'devti-ftp'),
                $success_count
            );
            
            if (!empty($error_messages)) {
                $message .= '<br><br>' . __('Some errors occurred:', 'devti-ftp') . '<br>- ' . implode('<br>- ', $error_messages);
            }
            
            return array(
                'success' => true,
                'message' => $message
            );
        } else {
            return array(
                'success' => false,
                'message' => __('Migration failed. Errors:', 'devti-ftp') . '<br>- ' . implode('<br>- ', $error_messages)
            );
        }
    }
}