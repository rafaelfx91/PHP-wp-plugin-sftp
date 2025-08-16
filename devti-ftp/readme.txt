=== DevTi FTP ===
Contributors: devti
Requires at least: 6.0
Tested up to: 6.x
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later

Plugin para migração de arquivos via FTP/SFTP com logs.

== Descrição ==
- Configura host, porta, usuário, senha, pasta (local e remota) e extensão (.wpress por padrão).
- Testa conexão e permissões de escrita na pasta remota.
- Migra todos os arquivos com a extensão configurada e remove localmente após sucesso.
- Gera logs diários em wp-content/logs-ftp/.

== Instalação ==
1. Copie a pasta `devti-ftp` para `wp-content/plugins/`.
2. (Para SFTP) Baixe phpseclib em https://github.com/phpseclib/phpseclib e coloque em `devti-ftp/includes/libs/phpseclib/` (deve existir `autoload.php`).
3. Ative o plugin no painel.
4. Vá em Configurações > DevTi FTP, preencha os campos e salve.

== Uso ==
- "Testar FTP": valida login e escrita em `Pasta` remota.
- "Fazer migração": envia todos os arquivos com a extensão informada a partir da pasta local e, em caso de sucesso, exclui os arquivos locais.

== Logs ==
- `wp-content/logs-ftp/log-YYYY-MM-DD.txt`
