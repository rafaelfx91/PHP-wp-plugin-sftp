<?php
if (!defined('ABSPATH')) exit;

class DEVTIFTP_Admin {
    private static $instance;
    private $settings;

    public static function init() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->settings = get_option('devtiftp_settings');
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_devtiftp_test_connection', array($this, 'test_connection'));
        add_action('wp_ajax_devtiftp_start_migration', array($this, 'start_migration'));
    }

    public function add_admin_menu() {
        add_options_page(
            __('DEVTIFTP Settings', 'devti-ftp'),
            __('DEVTIFTP', 'devti-ftp'),
            'manage_options',
            'devtiftp',
            array($this, 'options_page')
        );
    }

    public function settings_init() {
        register_setting('devtiftp', 'devtiftp_settings');

        add_settings_section(
            'devtiftp_section',
            __('FTP Connection Settings', 'devti-ftp'),
            array($this, 'settings_section_callback'),
            'devtiftp'
        );

        // Campo Host
        add_settings_field(
            'host',
            __('FTP Host', 'devti-ftp'),
            array($this, 'host_render'),
            'devtiftp',
            'devtiftp_section'
        );

        // Campo Porta
        add_settings_field(
            'port',
            __('FTP Port', 'devti-ftp'),
            array($this, 'port_render'),
            'devtiftp',
            'devtiftp_section'
        );

        // Campo Usuário
        add_settings_field(
            'username',
            __('FTP Username', 'devti-ftp'),
            array($this, 'username_render'),
            'devtiftp',
            'devtiftp_section'
        );

        // Campo Senha
        add_settings_field(
            'password',
            __('FTP Password', 'devti-ftp'),
            array($this, 'password_render'),
            'devtiftp',
            'devtiftp_section'
        );

        // Campo Pasta
        add_settings_field(
            'path',
            __('Local Path', 'devti-ftp'),
            array($this, 'path_render'),
            'devtiftp',
            'devtiftp_section'
        );

        // Campo Extensão
        add_settings_field(
            'extension',
            __('File Extension', 'devti-ftp'),
            array($this, 'extension_render'),
            'devtiftp',
            'devtiftp_section'
        );
    }

    public function host_render() {
        ?>
        <input type="text" name="devtiftp_settings[host]" value="<?php echo esc_attr($this->settings['host']); ?>" class="regular-text">
        <p class="description"><?php _e('Enter the FTP host (e.g., ftp.example.com)', 'devti-ftp'); ?></p>
        <?php
    }

    public function port_render() {
        ?>
        <input type="number" name="devtiftp_settings[port]" value="<?php echo esc_attr($this->settings['port']); ?>" class="small-text" min="1" max="65535">
        <p class="description"><?php _e('Default FTP port is 21', 'devti-ftp'); ?></p>
        <?php
    }

    public function username_render() {
        ?>
        <input type="text" name="devtiftp_settings[username]" value="<?php echo esc_attr($this->settings['username']); ?>" class="regular-text">
        <?php
    }

    public function password_render() {
        ?>
        <input type="password" name="devtiftp_settings[password]" value="<?php echo esc_attr($this->settings['password']); ?>" class="regular-text">
        <?php
    }

    public function path_render() {
        ?>
        <input type="text" name="devtiftp_settings[path]" value="<?php echo esc_attr($this->settings['path']); ?>" class="regular-text">
        <p class="description"><?php _e('Local directory path where files are located (relative to WordPress root)', 'devti-ftp'); ?></p>
        <?php
    }

    public function extension_render() {
        ?>
        <input type="text" name="devtiftp_settings[extension]" value="<?php echo esc_attr($this->settings['extension']); ?>" class="small-text">
        <p class="description"><?php _e('File extension to look for (e.g., wpress)', 'devti-ftp'); ?></p>
        <?php
    }

    public function settings_section_callback() {
        echo __('Configure your FTP connection settings and migration options below.', 'devti-ftp');
    }

    public function options_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Verificar e mostrar notificações
        settings_errors('devtiftp_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form action="options.php" method="post" id="devtiftp-settings-form">
                <?php
                settings_fields('devtiftp');
                do_settings_sections('devtiftp');
                submit_button(__('Save Settings', 'devti-ftp'));
                ?>
            </form>
            
            <div class="devtiftp-actions">
                <h2><?php _e('Actions', 'devti-ftp'); ?></h2>
                <button id="devtiftp-test-connection" class="button button-secondary">
                    <?php _e('Test FTP Connection', 'devti-ftp'); ?>
                </button>
                <button id="devtiftp-start-migration" class="button button-primary">
                    <?php _e('Start Migration', 'devti-ftp'); ?>
                </button>
                <span id="devtiftp-spinner" class="spinner" style="float: none; display: none;"></span>
            </div>
            
            <div id="devtiftp-results"></div>
        </div>
        <?php
    }

    public function enqueue_assets($hook) {
        if ('settings_page_devtiftp' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'devtiftp-admin',
            DEVTIFTP_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            DEVTIFTP_VERSION
        );

        wp_enqueue_script(
            'devtiftp-admin',
            DEVTIFTP_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            DEVTIFTP_VERSION,
            true
        );

        wp_localize_script(
            'devtiftp-admin',
            'devtiftp_vars',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('devtiftp_nonce'),
                'testing' => __('Testing connection...', 'devti-ftp'),
                'migrating' => __('Migrating files...', 'devti-ftp'),
                'success' => __('Success!', 'devti-ftp'),
                'error' => __('Error:', 'devti-ftp')
            )
        );
    }

    public function test_connection() {
        check_ajax_referer('devtiftp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have sufficient permissions.', 'devti-ftp'));
        }

        $settings = get_option('devtiftp_settings');
        
        try {
            $ftp = new DEVTIFTP_FTP($settings);
            $connected = $ftp->test_connection();
            
            if ($connected) {
                wp_send_json_success(__('FTP connection successful!', 'devti-ftp'));
            } else {
                wp_send_json_error(__('FTP connection failed. Please check your settings.', 'devti-ftp'));
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function start_migration() {
        check_ajax_referer('devtiftp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have sufficient permissions.', 'devti-ftp'));
        }

        $settings = get_option('devtiftp_settings');
        
        try {
            $migrator = new DEVTIFTP_Migrator($settings);
            $result = $migrator->migrate_files();
            
            if ($result['success']) {
                wp_send_json_success($result['message']);
            } else {
                wp_send_json_error($result['message']);
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}