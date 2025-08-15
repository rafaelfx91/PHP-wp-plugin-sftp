<?php
/**
 * Plugin Name: DEVTIFTP Manager
 * Plugin URI: https://seusite.com/devti-ftp
 * Description: Plugin para gerenciamento de conexões FTP e migração de arquivos.
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

// Carregar arquivos necessários
require_once DEVTIFTP_PLUGIN_DIR . 'includes/class-admin.php';
require_once DEVTIFTP_PLUGIN_DIR . 'includes/class-ftp.php';
require_once DEVTIFTP_PLUGIN_DIR . 'includes/class-migrator.php';

// Inicializar o plugin
function devtiftp_init() {
    // Carregar traduções
    load_plugin_textdomain('devti-ftp', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    
    // Inicializar classes
    DEVTIFTP_Admin::init();
}

add_action('plugins_loaded', 'devtiftp_init');

// Ativação do plugin
function devtiftp_activate() {
    // Adicionar opções padrão se não existirem
    if (!get_option('devtiftp_settings')) {
        $defaults = array(
            'host' => '',
            'port' => '21',
            'username' => '',
            'password' => '',
            'path' => '',
            'extension' => 'wpress'
        );
        update_option('devtiftp_settings', $defaults);
    }
}

register_activation_hook(__FILE__, 'devtiftp_activate');

// Desativação do plugin
function devtiftp_deactivate() {
    // Limpar quaisquer agendamentos ou transients
}

register_deactivation_hook(__FILE__, 'devtiftp_deactivate');