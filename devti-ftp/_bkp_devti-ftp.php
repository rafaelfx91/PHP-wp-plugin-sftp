<?php
/**
 * Plugin Name: DevTi FTP
 * Plugin URI:  https://seusite.com
 * Description: Plugin para migração de arquivos via FTP/SFTP. Permite configurar host, porta, usuário, senha, pasta e extensão de arquivos. Inclui botões para testar conexão e realizar migração. Todos os erros são logados em /wp-content/logs-ftp/.
 * Version:     1.0.0
 * Author:      DevTi
 * Author URI:  https://seusite.com
 * License:     GPL2
 * Text Domain: devti-ftp
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'DEVTIFTP_PATH', plugin_dir_path( __FILE__ ) );
define( 'DEVTIFTP_URL', plugin_dir_url( __FILE__ ) );
define( 'DEVTIFTP_LOG_DIR', WP_CONTENT_DIR . '/logs-ftp' );

// Autoload de classes se existir
spl_autoload_register( function ( $class ) {
    if ( strpos( $class, 'DevTi_' ) === 0 ) {
        $file = DEVTIFTP_PATH . 'includes/' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
        if ( file_exists( $file ) ) {
            include $file;
        }
    }
} );

/**
 * Classe principal do plugin
 */
class DevTi_FTP_Plugin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        // Handlers de ações
        add_action( 'admin_post_devti_ftp_test', [ $this, 'handle_test' ] );
        add_action( 'admin_post_devti_ftp_migrate', [ $this, 'handle_migrate' ] );
    }

    public function add_admin_menu() {
        add_options_page(
            __( 'DevTi FTP', 'devti-ftp' ),
            __( 'DevTi FTP', 'devti-ftp' ),
            'manage_options',
            'devti-ftp',
            [ $this, 'settings_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'devti_ftp_options', 'devti_ftp_options', [ $this, 'sanitize' ] );
    }

    public function sanitize( $input ) {
        $new_input = [];
        $new_input['host']     = sanitize_text_field( $input['host'] ?? '' );
        $new_input['port']     = intval( $input['port'] ?? 21 );
        $new_input['user']     = sanitize_text_field( $input['user'] ?? '' );
        $new_input['pass']     = sanitize_text_field( $input['pass'] ?? '' );
        $new_input['path']     = sanitize_text_field( $input['path'] ?? '' );
        $new_input['ext']      = sanitize_text_field( $input['ext'] ?? 'wpress' );
        $new_input['protocol'] = in_array( $input['protocol'] ?? 'ftp', [ 'ftp', 'sftp' ], true ) ? $input['protocol'] : 'ftp';
        return $new_input;
    }

    public function get_options() {
        return get_option( 'devti_ftp_options', [
            'host'     => '',
            'port'     => 21,
            'user'     => '',
            'pass'     => '',
            'path'     => '',
            'ext'      => 'wpress',
            'protocol' => 'ftp',
        ] );
    }

    public function settings_page() {
        $opts = $this->get_options();
        ?>
        <div class="wrap">
            <h1><?php _e( 'Configurações - DevTi FTP', 'devti-ftp' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'devti_ftp_options' );
                do_settings_sections( 'devti_ftp_options' );
                ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="host"><?php _e( 'Host', 'devti-ftp' ); ?></label></th>
                        <td><input type="text" id="host" name="devti_ftp_options[host]" value="<?php echo esc_attr( $opts['host'] ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="port"><?php _e( 'Porta', 'devti-ftp' ); ?></label></th>
                        <td><input type="number" id="port" name="devti_ftp_options[port]" value="<?php echo esc_attr( $opts['port'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="user"><?php _e( 'Usuário', 'devti-ftp' ); ?></label></th>
                        <td><input type="text" id="user" name="devti_ftp_options[user]" value="<?php echo esc_attr( $opts['user'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pass"><?php _e( 'Senha', 'devti-ftp' ); ?></label></th>
                        <td><input type="password" id="pass" name="devti_ftp_options[pass]" value="<?php echo esc_attr( $opts['pass'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="path"><?php _e( 'Pasta destino', 'devti-ftp' ); ?></label></th>
                        <td><input type="text" id="path" name="devti_ftp_options[path]" value="<?php echo esc_attr( $opts['path'] ); ?>" placeholder="/backups"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ext"><?php _e( 'Extensão do arquivo', 'devti-ftp' ); ?></label></th>
                        <td><input type="text" id="ext" name="devti_ftp_options[ext]" value="<?php echo esc_attr( $opts['ext'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="protocol"><?php _e( 'Protocolo', 'devti-ftp' ); ?></label></th>
                        <td>
                            <select id="protocol" name="devti_ftp_options[protocol]">
                                <option value="ftp" <?php selected( $opts['protocol'], 'ftp' ); ?>>FTP</option>
                                <option value="sftp" <?php selected( $opts['protocol'], 'sftp' ); ?>>SFTP</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'devti_ftp_test', 'devti_ftp_test_nonce' ); ?>
                <input type="hidden" name="action" value="devti_ftp_test">
                <?php submit_button( __( 'Testar FTP', 'devti-ftp' ), 'secondary' ); ?>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'devti_ftp_migrate', 'devti_ftp_migrate_nonce' ); ?>
                <input type="hidden" name="action" value="devti_ftp_migrate">
                <?php submit_button( __( 'Fazer Migração', 'devti-ftp' ), 'primary' ); ?>
            </form>
        </div>
        <?php
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
            DevTi_FTP_Logger::log( 'TEST_ERROR', sprintf(
                'Host: %s | Porta: %s | Pasta destino: %s',
                $opts['host'],
                $opts['port'],
                $opts['path']
            ) );
            DevTi_FTP_Logger::log( 'TEST_ERROR', $e->getMessage() );

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

            // Encontra arquivos com a extensão
            $upload_dir = wp_upload_dir();
            $local_path = trailingslashit( $upload_dir['basedir'] );
            $files = glob( $local_path . '*.' . $opts['ext'] );

            if ( empty( $files ) ) {
                throw new Exception( __( 'Nenhum arquivo encontrado para migração.', 'devti-ftp' ) );
            }

            foreach ( $files as $file ) {
                $client->upload( $file );
                unlink( $file ); // remove após sucesso
            }

            $client->disconnect();

            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' .
                    esc_html__( 'Migração concluída com sucesso!', 'devti-ftp' ) .
                '</p></div>';
            } );
        } catch ( Exception $e ) {
            DevTi_FTP_Logger::log( 'MIGRATE_ERROR', sprintf(
                'Host: %s | Porta: %s | Pasta destino: %s',
                $opts['host'],
                $opts['port'],
                $opts['path']
            ) );
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
