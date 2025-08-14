<?php
/**
 * Plugin Name:       devti-plugin SFTP Uploader
 * Description:       Envia o primeiro arquivo encontrado com a extensão configurada de um diretório absoluto do WordPress para um servidor SFTP e, após sucesso, remove o arquivo local. Inclui UI de configuração e botões de teste/envio.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            DevTI
 * Text Domain:       devti-plugin
 * Domain Path:       /languages
 */

defined('ABSPATH') || exit;

// Carrega autoload do Composer (phpseclib), se existir.
$devti_autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($devti_autoload)) {
	require_once $devti_autoload;
}

// Constantes do plugin.
define('DEVTI_PLUGIN_VERSION', '1.0.0');
define('DEVTI_PLUGIN_FILE', __FILE__);
define('DEVTI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DEVTI_PLUGIN_URL', plugin_dir_url(__FILE__));

// Carrega classes.
require_once DEVTI_PLUGIN_DIR . 'includes/class-devti-sftp-uploader.php';
require_once DEVTI_PLUGIN_DIR . 'includes/class-devti-admin.php';

/**
 * Inicializa i18n.
 */
function devti_plugin_load_textdomain() {
	load_plugin_textdomain('devti-plugin', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'devti_plugin_load_textdomain');

/**
 * Inicialização do Admin.
 */
function devti_plugin_init_admin() {
	if (!is_admin()) {
		return;
	}
	$admin = new Devti\SFTPUploader\Admin();
	$admin->hooks();
}
add_action('init', 'devti_plugin_init_admin');
