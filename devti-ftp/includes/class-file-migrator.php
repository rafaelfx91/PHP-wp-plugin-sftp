<?php
if (!class_exists('DEVTIFTP_File_Migrator')) {
    class DEVTIFTP_File_Migrator {
        private $ftp_handler;
        private $settings;
        
        public function __construct($ftp_handler, $settings) {
            $this->ftp_handler = $ftp_handler;
            $this->settings = $settings;
        }
        
        public function migrate_files() {
            $extension = $this->settings['file_extension'];
            $path = ABSPATH; // Pasta raiz do WordPress
            
            // Encontrar arquivos com a extensão especificada
            $files = glob($path . '/*.' . $extension);
            
            if (empty($files)) {
                DEVTIFTP_Logger::log("Nenhum arquivo .{$extension} encontrado em {$path}");
                return new WP_Error('no_files', "Nenhum arquivo .{$extension} encontrado para migração.");
            }
            
            $results = array();
            $success_count = 0;
            
            foreach ($files as $file) {
                $filename = basename($file);
                $result = $this->ftp_handler->upload_file($file, $filename);
                
                if (is_wp_error($result)) {
                    $results[] = array(
                        'file' => $filename,
                        'success' => false,
                        'message' => $result->get_error_message()
                    );
                } else {
                    // Se o upload foi bem-sucedido, deletar o arquivo local
                    if (unlink($file)) {
                        DEVTIFTP_Logger::log("Arquivo {$filename} migrado com sucesso e removido localmente.");
                        $results[] = array(
                            'file' => $filename,
                            'success' => true,
                            'message' => 'Arquivo migrado e removido com sucesso.'
                        );
                        $success_count++;
                    } else {
                        DEVTIFTP_Logger::log("Arquivo {$filename} migrado mas não pôde ser removido localmente.", 'WARNING');
                        $results[] = array(
                            'file' => $filename,
                            'success' => true,
                            'message' => 'Arquivo migrado mas não pôde ser removido localmente.'
                        );
                        $success_count++;
                    }
                }
            }
            
            return array(
                'total_files' => count($files),
                'success_count' => $success_count,
                'results' => $results
            );
        }
    }
}