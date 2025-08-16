<?php
if (!class_exists('DEVTIFTP_Admin_Page')) {
    class DEVTIFTP_Admin_Page {
        public function __construct() {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'settings_init'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            add_action('wp_ajax_devtiftp_test_connection', array($this, 'ajax_test_connection'));
            add_action('wp_ajax_devtiftp_migrate_files', array($this, 'ajax_migrate_files'));
        }
        
        public function add_admin_menu() {
            add_options_page(
                'DevTi FTP', 
                'DevTi FTP', 
                'manage_options', 
                'devti-ftp', 
                array($this, 'options_page')
            );
        }
        
        public function settings_init() {
            register_setting('devti-ftp', 'devtiftp_settings');
            
            add_settings_section(
                'devtiftp_settings_section',
                __('Configurações de Conexão', 'devti-ftp'),
                array($this, 'settings_section_callback'),
                'devti-ftp'
            );
            
            $fields = array(
                array(
                    'id' => 'host',
                    'title' => __('Host', 'devti-ftp'),
                    'type' => 'text'
                ),
                array(
                    'id' => 'port',
                    'title' => __('Porta', 'devti-ftp'),
                    'type' => 'number',
                    'default' => '21'
                ),
                array(
                    'id' => 'username',
                    'title' => __('Usuário', 'devti-ftp'),
                    'type' => 'text'
                ),
                array(
                    'id' => 'password',
                    'title' => __('Senha', 'devti-ftp'),
                    'type' => 'password'
                ),
                array(
                    'id' => 'path',
                    'title' => __('Pasta Remota', 'devti-ftp'),
                    'type' => 'text',
                    'default' => '/'
                ),
                array(
                    'id' => 'file_extension',
                    'title' => __('Extensão do Arquivo', 'devti-ftp'),
                    'type' => 'text',
                    'default' => 'wpress'
                ),
                array(
                    'id' => 'connection_type',
                    'title' => __('Tipo de Conexão', 'devti-ftp'),
                    'type' => 'select',
                    'options' => array(
                        'ftp' => 'FTP',
                        'sftp' => 'SFTP'
                    )
                )
            );
            
            foreach ($fields as $field) {
                add_settings_field(
                    'devtiftp_' . $field['id'],
                    $field['title'],
                    array($this, 'render_field'),
                    'devti-ftp',
                    'devtiftp_settings_section',
                    $field
                );
            }
        }
        
        public function render_field($args) {
            $options = get_option('devtiftp_settings');
            $value = isset($options[$args['id']]) ? $options[$args['id']] : (isset($args['default']) ? $args['default'] : '');
            
            switch ($args['type']) {
                case 'text':
                case 'password':
                case 'number':
                    printf(
                        '<input type="%s" id="devtiftp_%s" name="devtiftp_settings[%s]" value="%s" class="regular-text" />',
                        esc_attr($args['type']),
                        esc_attr($args['id']),
                        esc_attr($args['id']),
                        esc_attr($value)
                    );
                    break;
                
                case 'select':
                    echo '<select id="devtiftp_' . esc_attr($args['id']) . '" name="devtiftp_settings[' . esc_attr($args['id']) . ']" class="regular-text">';
                    foreach ($args['options'] as $key => $label) {
                        printf(
                            '<option value="%s" %s>%s</option>',
                            esc_attr($key),
                            selected($value, $key, false),
                            esc_html($label)
                        );
                    }
                    echo '</select>';
                    break;
            }
            
            if (isset($args['description'])) {
                printf('<p class="description">%s</p>', esc_html($args['description']));
            }
        }
        
        public function settings_section_callback() {
            echo '<p>' . __('Configure as informações de conexão FTP/SFTP para migração de arquivos.', 'devti-ftp') . '</p>';
        }
        
        public function options_page() {
            if (!current_user_can('manage_options')) {
                return;
            }
            
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                
                <form action="options.php" method="post">
                    <?php
                    settings_fields('devti-ftp');
                    do_settings_sections('devti-ftp');
                    submit_button(__('Salvar Configurações', 'devti-ftp'));
                    ?>
                </form>
                
                <div class="devtiftp-actions">
                    <h2><?php _e('Ações', 'devti-ftp'); ?></h2>
                    <button id="devtiftp-test-connection" class="button button-secondary">
                        <?php _e('Testar Conexão', 'devti-ftp'); ?>
                    </button>
                    <button id="devtiftp-migrate-files" class="button button-primary">
                        <?php _e('Fazer Migração', 'devti-ftp'); ?>
                    </button>
                    <div id="devtiftp-results" class="devtiftp-results"></div>
                </div>
            </div>
            <?php
        }
        
        public function enqueue_admin_assets($hook) {
            if ($hook !== 'settings_page_devti-ftp') {
                return;
            }
            
            wp_enqueue_style(
                'devtiftp-admin-css',
                DEVTIFTP_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                DEVTIFTP_VERSION
            );
            
            wp_enqueue_script(
                'devtiftp-admin-js',
                DEVTIFTP_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                DEVTIFTP_VERSION,
                true
            );
            
            wp_localize_script(
                'devtiftp-admin-js',
                'devtiftp_vars',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('devtiftp_nonce'),
                    'testing' => __('Testando conexão...', 'devti-ftp'),
                    'migrating' => __('Migrando arquivos...', 'devti-ftp')
                )
            );
        }
        
        public function ajax_test_connection() {
            check_ajax_referer('devtiftp_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('Permissão negada.', 'devti-ftp'), 403);
            }
            
            $settings = get_option('devtiftp_settings');
            
            if (empty($settings['host']) || empty($settings['username'])) {
                wp_send_json_error(__('Host e usuário são obrigatórios.', 'devti-ftp'));
            }
            
            $ftp_handler = new DEVTIFTP_FTP_Handler($settings);
            $result = $ftp_handler->test_connection();
            
            if ($result === true) {
                wp_send_json_success(__('Conexão bem-sucedida!', 'devti-ftp'));
            } else {
                wp_send_json_error($result->get_error_message());
            }
        }
        
        public function ajax_migrate_files() {
            check_ajax_referer('devtiftp_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('Permissão negada.', 'devti-ftp'), 403);
            }
            
            $settings = get_option('devtiftp_settings');
            
            if (empty($settings['host']) || empty($settings['username'])) {
                wp_send_json_error(__('Configure a conexão antes de migrar arquivos.', 'devti-ftp'));
            }
            
            $ftp_handler = new DEVTIFTP_FTP_Handler($settings);
            $migrator = new DEVTIFTP_File_Migrator($ftp_handler, $settings);
            
            $result = $migrator->migrate_files();
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            } else {
                wp_send_json_success($result);
            }
        }
    }
}