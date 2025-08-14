<?php
/**
 * Plugin Name: devti-plugin SFTP Uploader
 * Description: Envia o primeiro arquivo de um diretório específico para um SFTP e remove o original. Configurável via painel admin.
 * Version: 1.0.0
 * Author: DevTI
 * Text Domain: devti-plugin
 */

// Bloqueia acesso direto
if ( ! defined('ABSPATH') ) {
    exit;
}

// Define constantes do plugin
define('DEVTI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DEVTI_PLUGIN_URL', plugin_dir_url(__FILE__));

// Carregar phpseclib interno, caso não exista
if ( ! class_exists('\phpseclib3\Net\SFTP') ) {
    require_once DEVTI_PLUGIN_DIR . 'includes/phpseclib/autoload.php';
}

// Carregar classes do plugin
require_once DEVTI_PLUGIN_DIR . 'includes/class-devti-admin.php';
require_once DEVTI_PLUGIN_DIR . 'includes/class-devti-sftp-uploader.php';

// Inicializa admin e uploader
add_action('plugins_loaded', function() {
    if ( is_admin() ) {
        new DevTI_SFTP_Admin();
    }
    new DevTI_SFTP_Uploader();
});
