# devti-plugin SFTP Uploader

Plugin WordPress para **enviar automaticamente** (ou manualmente) o **primeiro arquivo** encontrado com uma extensão específica (ex.: `.wpress`) de um diretório absoluto dentro da instalação do WordPress para um servidor SFTP.  
Após o envio bem-sucedido, o arquivo local é **removido**.

Inclui:
- Tela de configuração no painel admin
- Botão de **Testar Conexão SFTP**
- Botão de **Enviar Primeiro Arquivo**
- Segurança com *nonces* e checagem de capacidades (`manage_options`)
- Compatibilidade com **phpseclib 3** ou extensão `ssh2`

---

## 📦 Instalação

1. Baixe ou clone este repositório na pasta:


2. (Recomendado) Dentro da pasta do plugin, instale o **phpseclib**:
```bash
composer require phpseclib/phpseclib:^3
No painel WordPress, ative devti-plugin SFTP Uploader.

Vá para Ferramentas → SFTP Uploader (DevTI) e configure:

Host (ex.: sftp.seuservidor.com)

Porta (padrão 22)

Usuário e Senha

Pasta Remota no servidor SFTP (opcional)

Diretório Local Absoluto (ex.: /public_html/wp-content/ai1wm-backups)

Extensão do Arquivo (ex.: wpress)

🚀 Uso

Testar Conexão SFTP: verifica se as credenciais e pasta remota estão corretas.

Enviar Primeiro Arquivo:

Procura o primeiro arquivo encontrado com a extensão configurada (o mais antigo) no diretório local.

Envia via SFTP.

Remove o arquivo local após sucesso.

🔒 Segurança

Apenas administradores (manage_options) têm acesso.

Todos os envios e testes passam por validação com nonce.

Dados são sanitizados antes do uso.

A senha do SFTP é armazenada como opção no banco de dados (texto plano).
Para maior segurança, recomenda-se limitar o acesso ao painel e ao banco de dados,
ou configurar para usar constantes no wp-config.php.

🛠 Requisitos

WordPress 6.0+

PHP 8.0+

phpseclib 3 ou extensão ssh2

📌 Roadmap (melhorias futuras)

Agendamento de envios (WP-Cron)

Autenticação via chave SSH

Opção para enviar todos os arquivos

Registro de logs de envio