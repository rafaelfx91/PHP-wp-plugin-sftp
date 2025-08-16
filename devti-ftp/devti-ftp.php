<?php
/**
 * Plugin Name:       DevTi FTP
 * Description:       Envia arquivos com uma extensão específica (ex.: .wpress) para FTP/SFTP remoto a partir de uma pasta local, com testes de conexão e logs.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            DevTi
 * Text Domain:       devti-ftp
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DEVTIFTP_VERSION', '1.0.0' );
define( 'DEVTIFTP_PLUGIN_FILE', __FILE__ );
define( 'DEVTIFTP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DEVTIFTP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DEVTIFTP_OPTION_KEY', 'devti_ftp_options' );
define( 'DEVTIFTP_LOG_DIR', trailingslashit( WP_CONTENT_DIR ) . 'logs-ftp' ); // wp-content/logs-ftp

// Carrega classes
require_once DEVTIFTP_PLUGIN_DIR . 'includes/class-devti-ftp-logger.php';
require_once DEVTIFTP_PLUGIN_DIR . 'includes/class-devti-ftp-client.php';

class DevTi_FTP_Plugin {

    public function __construct() {
        // i18n
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );

        // Admin
        add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // Handlers de ações do formulário (POST)
        add_action( 'admin_post_devti_ftp_save', [ $this, 'handle_save' ] );
        add_action( 'admin_post_devti_ftp_test', [ $this, 'handle_test' ] );
        add_action( 'admin_post_devti_ftp_migrate', [ $this, 'handle_migrate' ] );

        // Garante diretório de logs
        DevTi_FTP_Logger::ensure_log_dir();
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'devti-ftp', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    public function register_settings_page() {
        // Menu em "Configurações" > "DevTi FTP"
        add_options_page(
            __( 'DevTi FTP', 'devti-ftp' ),
            __( 'DevTi FTP', 'devti-ftp' ),
            'manage_options',
            'devti-ftp',
            [ $this, 'render_settings_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'devti_ftp_settings', DEVTIFTP_OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [ $this, 'sanitize_options' ],
            'default' => $this->get_default_options(),
        ] );
    }

    private function get_default_options(): array {
        return [
            'host'      => '',
            'port'      => '',
            'user'      => '',
            'pass'      => '',
            'path'      => '', // usado como pasta remota
            'local_path'      => '/wp-content/ai1wm-backups', // pasta local padrão @rafa 
            'extension' => '.wpress',
            // protocolo é inferido pela porta (22 = SFTP). Se quiser forçar, acrescente aqui uma flag.
        ];
    }

    public function sanitize_options( $input ): array {
        $output = $this->get_options(); // base nos existentes
        $output['host']      = isset( $input['host'] ) ? sanitize_text_field( $input['host'] ) : '';
        $output['port']      = isset( $input['port'] ) ? preg_replace( '/[^0-9]/', '', $input['port'] ) : '';
        $output['user']      = isset( $input['user'] ) ? sanitize_text_field( $input['user'] ) : '';
        $output['pass']      = isset( $input['pass'] ) ? $input['pass'] : '';
        $output['path']      = isset( $input['path'] ) ? sanitize_text_field( $input['path'] ) : '';
        $ext                 = isset( $input['extension'] ) ? trim( sanitize_text_field( $input['extension'] ) ) : '.wpress';
        if ( $ext !== '' && $ext[0] !== '.' ) {
            $ext = '.' . $ext;
        }
        $output['extension'] = $ext;
        return $output;
    }

    public function get_options(): array {
        $opts = get_option( DEVTIFTP_OPTION_KEY, $this->get_default_options() );
        if ( ! is_array( $opts ) ) {
            $opts = $this->get_default_options();
        }
        return wp_parse_args( $opts, $this->get_default_options() );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( $hook !== 'settings_page_devti-ftp' ) {
            return;
        }
        wp_enqueue_script(
            'devti-ftp-admin',
            DEVTIFTP_PLUGIN_URL . 'assets/admin.js',
            [ 'jquery' ],
            DEVTIFTP_VERSION,
            true
        );
        wp_localize_script( 'devti-ftp-admin', 'DevTiFTP', [
            'confirmMigrate' => __( 'Deseja iniciar a migração agora? Todos os arquivos com a extensão configurada serão enviados e removidos localmente após sucesso.', 'devti-ftp' ),
        ] );
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'devti-ftp' ) );
        }

        //var_dump( get_option( 'devti_ftp_options' ) ); // Depuração @rafa
        //var_dump( get_option( 'devti_ftp_settings' ) ); // Depuração @rafa
        // global $wpdb;
        //echo 'Prefixo da tabela wp_options: ' . $wpdb->prefix . 'options<br>';
        //var_dump( get_option( DEVTIFTP_OPTION_KEY ) );  // Depuração @rafa



        $opts = $this->get_options();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'DevTi FTP', 'devti-ftp' ); ?></h1>
            <p><?php esc_html_e( 'Configure o servidor remoto e a pasta/local da migração. O plugin suporta FTP e SFTP (porta 22).', 'devti-ftp' ); ?></p>

            <?php if ( DevTi_FTP_Client::sftp_lib_missing() ) : ?>
                <div class="notice notice-warning">
                    <p>
                        <?php
                        printf(
                            esc_html__( 'Suporte SFTP requer a biblioteca phpseclib. Baixe e coloque em %s (veja instruções abaixo).', 'devti-ftp' ),
                            '<code>includes/libs/phpseclib/</code>'
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php settings_fields( 'devti_ftp_settings' ); ?>
                <?php wp_nonce_field( 'devti_ftp_save', 'devti_ftp_nonce' ); ?>
                <input type="hidden" name="action" value="devti_ftp_save" />

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="devti_host"><?php esc_html_e( 'Host', 'devti-ftp' ); ?></label></th>
                            <td><input name="<?php echo esc_attr( DEVTIFTP_OPTION_KEY ); ?>[host]" type="text" id="devti_host" value="<?php echo esc_attr( $opts['host'] ); ?>" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="devti_port"><?php esc_html_e( 'Porta', 'devti-ftp' ); ?></label></th>
                            <td><input name="<?php echo esc_attr( DEVTIFTP_OPTION_KEY ); ?>[port]" type="number" id="devti_port" value="<?php echo esc_attr( $opts['port'] ); ?>" class="small-text" placeholder="21 ou 22" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="devti_user"><?php esc_html_e( 'Usuário', 'devti-ftp' ); ?></label></th>
                            <td><input name="<?php echo esc_attr( DEVTIFTP_OPTION_KEY ); ?>[user]" type="text" id="devti_user" value="<?php echo esc_attr( $opts['user'] ); ?>" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="devti_pass"><?php esc_html_e( 'Senha', 'devti-ftp' ); ?></label></th>
                            <td><input name="<?php echo esc_attr( DEVTIFTP_OPTION_KEY ); ?>[pass]" type="password" id="devti_pass" value="<?php echo esc_attr( $opts['pass'] ); ?>" class="regular-text" autocomplete="new-password"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="devti_path"><?php esc_html_e( 'Pasta', 'devti-ftp' ); ?></label></th>
                            <td>
                                <input name="<?php echo esc_attr( DEVTIFTP_OPTION_KEY ); ?>[path]" type="text" id="devti_path" value="<?php echo esc_attr( $opts['path'] ); ?>" class="regular-text" placeholder="/caminho/local e remoto">
                                <p class="description">
                                    <?php esc_html_e( 'Usado como: (1) pasta local para ler os arquivos; (2) pasta remota de destino. Pode ser absoluto ou relativo à raiz do WordPress.', 'devti-ftp' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="devti_ext"><?php esc_html_e( 'Extensão do arquivo', 'devti-ftp' ); ?></label></th>
                            <td><input name="<?php echo esc_attr( DEVTIFTP_OPTION_KEY ); ?>[extension]" type="text" id="devti_ext" value="<?php echo esc_attr( $opts['extension'] ); ?>" class="regular-text" placeholder=".wpress" required></td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button( __( 'Salvar', 'devti-ftp' ) ); ?>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px;">
                <?php wp_nonce_field( 'devti_ftp_test', 'devti_ftp_test_nonce' ); ?>
                <input type="hidden" name="action" value="devti_ftp_test" />
                <?php submit_button( __( 'Testar FTP', 'devti-ftp' ), 'secondary', 'devti_ftp_test_btn' ); ?>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px;" onsubmit="return window.DevTiFTPAreYouSure && DevTiFTPAreYouSure();">
                <?php wp_nonce_field( 'devti_ftp_migrate', 'devti_ftp_migrate_nonce' ); ?>
                <input type="hidden" name="action" value="devti_ftp_migrate" />
                <?php submit_button( __( 'Fazer migração', 'devti-ftp' ), 'primary', 'devti_ftp_migrate_btn' ); ?>
            </form>

            <hr>
            <h2><?php esc_html_e( 'Suporte SFTP (phpseclib)', 'devti-ftp' ); ?></h2>
            <p><?php esc_html_e( 'Para SFTP (porta 22), baixe a biblioteca e coloque em includes/libs/phpseclib/.', 'devti-ftp' ); ?></p>
            <p>
                <strong>Biblioteca:</strong> phpseclib/phpseclib<br>
                <strong>Download (GitHub):</strong> <a href="https://github.com/phpseclib/phpseclib" target="_blank" rel="noopener">https://github.com/phpseclib/phpseclib</a><br>
                <strong>Estrutura esperada:</strong> <code>devti-ftp/includes/libs/phpseclib/autoload.php</code>
            </p>
        </div>
        <?php
    }

    public function handle_save() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permissão negada.', 'devti-ftp' ) );
    }
    check_admin_referer( 'devti_ftp_save', 'devti_ftp_nonce' );

    if ( isset( $_POST[ DEVTIFTP_OPTION_KEY ] ) ) {
        update_option( DEVTIFTP_OPTION_KEY, $_POST[ DEVTIFTP_OPTION_KEY ] );
    }

    $redirect = add_query_arg( [ 'page' => 'devti-ftp', 'updated' => 'true' ], admin_url( 'options-general.php' ) );
    wp_safe_redirect( $redirect );
    exit;
}

    public function handle_test() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permissão negada.', 'devti-ftp' ) );
        }
        check_admin_referer( 'devti_ftp_test', 'devti_ftp_test_nonce' );

        $opts = $this->get_options();

        try {
            $client = new DevTi_FTP_Client( $opts );
            $client->connect();
            $client->ensureRemoteDir();
            $client->disconnect();

            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' .
                    esc_html__( 'Conexão testada com sucesso e pasta remota acessível para escrita.', 'devti-ftp' ) .
                '</p></div>';
            } );
        } catch ( Exception $e ) {
            DevTi_FTP_Logger::log( 'TEST_ERROR ASD', $e->getMessage() );
            add_action( 'admin_notices', function() use ( $e ) {
                echo '<div class="notice notice-error"><p>' .
                    esc_html__( 'Falha no teste de conexão: ', 'devti-ftp' ) . esc_html( $e->getMessage() ) .
                '</p></div>';
                echo '<script>alert(' . json_encode( __( 'Erro no teste de conexão. Veja os logs.', 'devti-ftp' ), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ) . ');</script>';
            } );
        }

        $redirect = add_query_arg( [ 'page' => 'devti-ftp' ], admin_url( 'options-general.php' ) );
        wp_safe_redirect( $redirect );
        exit;
    }

    public function handle_migrate() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permissão negada.', 'devti-ftp' ) );
        }
        check_admin_referer( 'devti_ftp_migrate', 'devti_ftp_migrate_nonce' );

        $opts = $this->get_options();

        try {
            $client = new DevTi_FTP_Client( $opts );
            $client->connect();
            $client->ensureRemoteDir();

            $localDir = DevTi_FTP_Client::resolve_local_dir( $opts['path'] );
            $ext      = $opts['extension'];

            if ( ! is_dir( $localDir ) ) {
                throw new Exception( sprintf( __( 'Pasta local inválida: %s', 'devti-ftp' ), $localDir ) );
            }

            $files = glob( rtrim( $localDir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . '*' . preg_quote( $ext, '/' ) );
            if ( ! $files ) {
                $files = [];
            }

            $uploaded = 0;
            foreach ( $files as $filePath ) {
                if ( ! is_file( $filePath ) ) {
                    continue;
                }
                $basename = basename( $filePath );

                $client->upload( $filePath, $basename );
                // Se upload OK, remove local
                if ( ! @unlink( $filePath ) ) {
                    DevTi_FTP_Logger::log( 'DELETE_WARNING', 'Não foi possível excluir local: ' . $filePath );
                } else {
                    $uploaded++;
                }
            }

            $client->disconnect();

            if ( $uploaded > 0 ) {
                add_action( 'admin_notices', function() use ( $uploaded ) {
                    echo '<div class="notice notice-success is-dismissible"><p>' .
                        sprintf( esc_html__( 'Migração concluída: %d arquivo(s) enviado(s) e removido(s) localmente.', 'devti-ftp' ), intval( $uploaded ) ) .
                    '</p></div>';
                    echo '<script>alert(' . json_encode( __( 'Migração concluída com sucesso!', 'devti-ftp' ), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ) . ');</script>';
                } );
            } else {
                add_action( 'admin_notices', function() {
                    echo '<div class="notice notice-warning is-dismissible"><p>' .
                        esc_html__( 'Nenhum arquivo encontrado para migrar com a extensão configurada.', 'devti-ftp' ) .
                    '</p></div>';
                    echo '<script>alert(' . json_encode( __( 'Nenhum arquivo para migrar.', 'devti-ftp' ), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ) . ');</script>';
                } );
            }
        } catch ( Exception $e ) {
            DevTi_FTP_Logger::log( 'MIGRATE_ERROR', $e->getMessage() );
            add_action( 'admin_notices', function() use ( $e ) {
                echo '<div class="notice notice-error"><p>' .
                    esc_html__( 'Falha na migração: ', 'devti-ftp' ) . esc_html( $e->getMessage() ) .
                '</p></div>';
                echo '<script>alert(' . json_encode( __( 'Erro na migração. Veja os logs.', 'devti-ftp' ), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ) . ');</script>';
            } );
        }

        $redirect = add_query_arg( [ 'page' => 'devti-ftp' ], admin_url( 'options-general.php' ) );
        wp_safe_redirect( $redirect );
        exit;
    }
}

new DevTi_FTP_Plugin();
