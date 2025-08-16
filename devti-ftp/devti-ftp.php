<?php
/**
 * Plugin Name: DEVTIFTP - Gerenciador de Migração FTP/SFTP
 * Plugin URI: https://seusite.com/devti-ftp
 * Description: Plugin para gerenciar conexões FTP/SFTP e migrar arquivos.
 * Version: 1.0.0
 * Author: Seu Nome
 * Author URI: https://seusite.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: devti-ftp
 * Domain Path: /languages
 */

// Evitar acesso direto
defined('ABSPATH') || exit;

// Definir constantes do plugin
define('DEVTIFTP_VERSION', '1.0.0');
define('DEVTIFTP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DEVTIFTP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DEVTIFTP_LOG_DIR', WP_CONTENT_DIR . '/logs-ftp/');

// Verificar e criar diretório de logs
if (!file_exists(DEVTIFTP_LOG_DIR)) {
    wp_mkdir_p(DEVTIFTP_LOG_DIR);
}

// Carregar classes
require_once DEVTIFTP_PLUGIN_DIR . 'includes/class-logger.php';
require_once DEVTIFTP_PLUGIN_DIR . 'includes/class-ftp-handler.php';
require_once DEVTIFTP_PLUGIN_DIR . 'includes/class-file-migrator.php';
require_once DEVTIFTP_PLUGIN_DIR . 'includes/class-admin-page.php';

// Inicializar o plugin
function devtiftp_init() {
    // Carregar texto para internacionalização
    load_plugin_textdomain('devti-ftp', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    
    // Inicializar página admin
    new DEVTIFTP_Admin_Page();
}
add_action('plugins_loaded', 'devtiftp_init');

// Registrar ativação e desativação
register_activation_hook(__FILE__, 'devtiftp_activate');
register_deactivation_hook(__FILE__, 'devtiftp_deactivate');

function devtiftp_activate() {
    // Verificar requisitos
    if (!extension_loaded('ftp') && !extension_loaded('ssh2')) {
        $error = 'O plugin DEVTIFTP requer pelo menos a extensão FTP ou SSH2 do PHP.';
        DEVTIFTP_Logger::log($error);
        wp_die($error);
    }
    
    // Criar opções padrão
    $default_options = array(
        'host' => '',
        'port' => '21',
        'username' => '',
        'password' => '',
        'path' => '/',
        'file_extension' => 'wpress',
        'connection_type' => 'ftp' // ftp ou sftp
    );
    
    add_option('devtiftp_settings', $default_options);
}

function devtiftp_deactivate() {
    // Limpar agendamentos se houver
}

require_once DEVTIFTP_PLUGIN_DIR . 'vendor/phpseclib/phpseclib/phpseclib/Net/SFTP.php';





