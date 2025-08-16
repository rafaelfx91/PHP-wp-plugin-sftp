<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DevTi_FTP_Logger {

    public static function ensure_log_dir(): void {
        if ( ! is_dir( DEVTIFTP_LOG_DIR ) ) {
            wp_mkdir_p( DEVTIFTP_LOG_DIR );
        }
        // Tenta proteger
        $ht = trailingslashit( DEVTIFTP_LOG_DIR ) . '.htaccess';
        if ( ! file_exists( $ht ) ) {
            @file_put_contents( $ht, "Deny from all\n" );
        }
        $idx = trailingslashit( DEVTIFTP_LOG_DIR ) . 'index.html';
        if ( ! file_exists( $idx ) ) {
            @file_put_contents( $idx, '' );
        }
    }

    public static function log( string $type, string $message ): void {
        self::ensure_log_dir();
        $file = trailingslashit( DEVTIFTP_LOG_DIR ) . 'log-' . gmdate( 'Y-m-d' ) . '.txt';
        $line = sprintf(
            "[%s] [%s] %s\n",
            gmdate( 'Y-m-d H:i:s' ),
            strtoupper( $type ),
            $message
        );
        @file_put_contents( $file, $line, FILE_APPEND );
    }
}
