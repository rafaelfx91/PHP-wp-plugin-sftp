=== devti-plugin SFTP Uploader ===
Contributors: devti
Requires at least: 6.0
Tested up to: 6.x
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin para enviar o primeiro arquivo encontrado com uma extensão específica (ex.: .wpress) de um diretório absoluto dentro da instalação do WordPress para um servidor SFTP. Após o envio bem-sucedido, remove o arquivo local. Inclui tela de configuração e botões de teste e envio.

== Instalação ==
1. Extraia a pasta `devti-plugin-sftp-uploader` em `wp-content/plugins/`.
2. (Opcional mas recomendado) Dentro da pasta do plugin, instale phpseclib:
   composer require phpseclib/phpseclib:^3
3. Ative o plugin em "Plugins" no painel.
4. Vá em "Ferramentas" > "SFTP Uploader (DevTI)" para configurar.
