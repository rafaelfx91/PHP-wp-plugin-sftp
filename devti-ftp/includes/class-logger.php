<?php
if (!class_exists('DEVTIFTP_Logger')) {
    class DEVTIFTP_Logger {
        private static $log_file;
        
        public static function init() {
            self::$log_file = DEVTIFTP_LOG_DIR . 'devtiftp-' . date('Y-m-d') . '.log';
        }
        
        public static function log($message, $level = 'INFO') {
            if (!self::$log_file) {
                self::init();
            }
            
            $timestamp = date('Y-m-d H:i:s');
            $log_message = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
            
            file_put_contents(self::$log_file, $log_message, FILE_APPEND);
        }
    }
    
    DEVTIFTP_Logger::init();
}