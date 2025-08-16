<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DevTi_FTP_Client {

    /** @var array */
    private $opts;

    /** @var resource|null */
    private $ftp = null;

    /** @var \phpseclib3\Net\SFTP|null */
    private $sftp = null;

    /** @var bool */
    private $isSftp = false;

    public function __construct( array $options ) {
        $defaults = [
            'host'      => '',
            'port'      => '',
            'user'      => '',
            'pass'      => '',
            'path'      => '',
            'extension' => '.wpress',
        ];
        $this->opts = wp_parse_args( $options, $defaults );

        // Protocolo: porta 22 => SFTP, caso contrário FTP
        $this->isSftp = (string)$this->opts['port'] === '22';
    }

    public static function sftp_lib_missing(): bool {
        if ( class_exists( '\phpseclib3\Net\SFTP' ) ) {
            return false;
        }
        // Tentar carregar autoload manualmente
        $autoload = DEVTIFTP_PLUGIN_DIR . 'includes/libs/phpseclib/autoload.php';
        if ( file_exists( $autoload ) ) {
            require_once $autoload;
        }
        return ! class_exists( '\phpseclib3\Net\SFTP' );
    }

    public static function resolve_local_dir( string $path ): string {
        $path = trim( $path );
        if ( $path === '' ) {
            return ABSPATH;
        }
        // Se for relativo, resolve a partir do ABSPATH
        if ( ! self::is_absolute_path( $path ) ) {
            $path = ABSPATH . ltrim( $path, '/\\' );
        }
        return wp_normalize_path( $path );
    }

    private static function is_absolute_path( string $path ): bool {
        return (bool) preg_match( '#^([a-zA-Z]:\\\\|/|\\\\)#', $path );
    }

    public function connect(): void {
        $host = $this->opts['host'];
        $port = (int) ( $this->opts['port'] ?: ( $this->isSftp ? 22 : 21 ) );
        $user = $this->opts['user'];
        $pass = $this->opts['pass'];

        if ( $this->isSftp ) {
            if ( self::sftp_lib_missing() ) {
                throw new Exception( __( 'Biblioteca SFTP (phpseclib) ausente.', 'devti-ftp' ) );
            }
            $this->sftp = new \phpseclib3\Net\SFTP( $host, $port, 15 );
            if ( ! $this->sftp->login( $user, $pass ) ) {
                throw new Exception( __( 'Falha no login SFTP. Verifique usuário/senha.', 'devti-ftp' ) );
            }
        } else {
            if ( ! function_exists( 'ftp_connect' ) ) {
                throw new Exception( __( 'Extensão FTP do PHP não está habilitada.', 'devti-ftp' ) );
            }
            $this->ftp = @ftp_connect( $host, $port, 15 );
            if ( ! $this->ftp ) {
                throw new Exception( __( 'Não foi possível conectar ao servidor FTP.', 'devti-ftp' ) );
            }
            if ( ! @ftp_login( $this->ftp, $user, $pass ) ) {
                throw new Exception( __( 'Falha no login FTP. Verifique usuário/senha.', 'devti-ftp' ) );
            }
            @ftp_pasv( $this->ftp, true );
        }
    }

    public function ensureRemoteDir(): void {
        $remote = $this->opts['path'] ?: '/';
        if ( $this->isSftp ) {
            if ( ! @$this->sftp->chdir( $remote ) ) {
                // tenta criar recursivamente
                $this->mkdirs_sftp( $remote );
                if ( ! @$this->sftp->chdir( $remote ) ) {
                    throw new Exception( sprintf( __( 'Não foi possível acessar/criar pasta remota: %s', 'devti-ftp' ), $remote ) );
                }
            }
            // teste de escrita
            $tmp = '.devti-ftp-write-test';
            if ( $this->sftp->put( rtrim( $remote, '/' ) . '/' . $tmp, 'ok' ) === false ) {
                throw new Exception( __( 'Sem permissão de escrita na pasta remota (SFTP).', 'devti-ftp' ) );
            }
            $this->sftp->delete( rtrim( $remote, '/' ) . '/' . $tmp );
        } else {
            if ( ! @$this->ftp_chdir_deep( $remote ) ) {
                $this->mkdirs_ftp( $remote );
                if ( ! @$this->ftp_chdir_deep( $remote ) ) {
                    throw new Exception( sprintf( __( 'Não foi possível acessar/criar pasta remota: %s', 'devti-ftp' ), $remote ) );
                }
            }
            // teste de escrita
            $tmp = '.devti-ftp-write-test';
            $localTmp = wp_tempnam( 'devti-ftp' );
            file_put_contents( $localTmp, 'ok' );
            $ok = @ftp_put( $this->ftp, rtrim( $remote, '/' ) . '/' . $tmp, $localTmp, FTP_BINARY );
            @unlink( $localTmp );
            if ( ! $ok ) {
                throw new Exception( __( 'Sem permissão de escrita na pasta remota (FTP).', 'devti-ftp' ) );
            }
            @ftp_delete( $this->ftp, rtrim( $remote, '/' ) . '/' . $tmp );
        }
    }

    public function upload( string $localFile, string $remoteBasename ): void {
        $remote = rtrim( $this->opts['path'] ?: '/', '/' ) . '/' . $remoteBasename;

        if ( $this->isSftp ) {
            $stream = @fopen( $localFile, 'rb' );
            if ( ! $stream ) {
                throw new Exception( sprintf( __( 'Não foi possível abrir arquivo local: %s', 'devti-ftp' ), $localFile ) );
            }
            $ok = $this->sftp->put( $remote, $stream );
            @fclose( $stream );
            if ( ! $ok ) {
                throw new Exception( sprintf( __( 'Falha ao enviar via SFTP: %s', 'devti-ftp' ), $remoteBasename ) );
            }
        } else {
            $ok = @ftp_put( $this->ftp, $remote, $localFile, FTP_BINARY );
            if ( ! $ok ) {
                throw new Exception( sprintf( __( 'Falha ao enviar via FTP: %s', 'devti-ftp' ), $remoteBasename ) );
            }
        }
    }

    public function disconnect(): void {
        if ( $this->sftp ) {
            $this->sftp->disconnect();
            $this->sftp = null;
        }
        if ( $this->ftp ) {
            @ftp_close( $this->ftp );
            $this->ftp = null;
        }
    }

    /* ---------- Helpers privados ---------- */

    private function ftp_chdir_deep( string $path ): bool {
        $parts = array_values( array_filter( explode( '/', trim( $path, '/' ) ) ) );
        if ( empty( $parts ) ) {
            return @ftp_chdir( $this->ftp, '/' );
        }
        @ftp_chdir( $this->ftp, '/' );
        foreach ( $parts as $p ) {
            if ( ! @ftp_chdir( $this->ftp, $p ) ) {
                return false;
            }
        }
        return true;
    }

    private function mkdirs_ftp( string $path ): void {
        @ftp_chdir( $this->ftp, '/' );
        $parts = array_values( array_filter( explode( '/', trim( $path, '/' ) ) ) );
        $current = '';
        foreach ( $parts as $p ) {
            $current .= '/' . $p;
            if ( ! @$this->ftp_chdir( $this->ftp, $current ) ) {
                @ftp_mkdir( $this->ftp, $current );
            }
        }
    }

    private function mkdirs_sftp( string $path ): void {
        $parts = array_values( array_filter( explode( '/', trim( $path, '/' ) ) ) );
        $current = '';
        foreach ( $parts as $p ) {
            $current .= '/' . $p;
            if ( ! @$this->sftp->chdir( $current ) ) {
                @$this->sftp->mkdir( $current );
            }
        }
    }
}
