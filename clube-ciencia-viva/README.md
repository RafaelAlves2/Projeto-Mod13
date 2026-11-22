# Clube de Ciência Viva

Aplicação web em PHP e MySQL para gerir os projetos, atividades, equipamentos e
reservas de um clube escolar. Inclui autenticação por perfis, upload de ficheiros,
pesquisa e paginação nas listas principais e um assistente de IA opcional que corre
localmente através do Ollama.

## Requisitos

- Windows com WampServer 3.3 ou equivalente (Apache + PHP + MySQL).
- PHP 8.1 ou superior. O projeto foi testado com PHP 8.3.28.
- Extensões PHP: `pdo`, `pdo_mysql`, `mbstring`, `session`, `json` e `fileinfo`.
- MySQL 8.0 ou superior. O projeto foi testado com MySQL 8.4.7.
- Apache com `AllowOverride All` e `mod_headers` ativo, para que os ficheiros
  `.htaccess` de proteção sejam aplicados.
- Um navegador atual (Chrome, Edge, Firefox ou Safari).

O assistente de IA é opcional. Sem o Ollama, o resto da aplicação funciona
normalmente.

## Instalação

1. Copiar a pasta do projeto para `C:\wamp64\www\meu-projeto\clube-ciencia-viva`.
2. Ligar o WampServer e confirmar que o ícone está verde.
3. Abrir `http://localhost/phpmyadmin` e importar
   `database/clube_ciencia_viva.sql`.
   - O SQL cria a base `clube_ciencia_viva`, as 17 tabelas e as 22 chaves
     estrangeiras.
   - O SQL não apaga dados. Se as tabelas já existirem, a importação falha em vez
     de as substituir.
4. Confirmar os valores de `includes/config.php` (base de dados, uploads e
   Ollama). No WampServer, o MySQL local usa normalmente `root` sem palavra-passe.
5. Abrir `http://localhost/meu-projeto/clube-ciencia-viva/instalar.php` e criar o
   primeiro administrador. Esta página só funciona enquanto a tabela
   `utilizador` estiver vazia.
6. Entrar em `login.php` com a conta criada.

Se o projeto estiver noutra pasta, `BASE_URL` é calculado automaticamente e não
precisa de ser alterado.

### Assistente de IA (opcional)

1. Instalar o Ollama.
2. Descarregar um modelo, por exemplo: `ollama pull llama3.2`.
3. Manter o Ollama em execução.
4. Ajustar `OLLAMA_URL` e `OLLAMA_MODEL` em `includes/config.php`, se necessário.

## Funcionalidades

- **Início**: indicadores do clube, gráficos de reservas e equipamentos, atalhos
  para as ações mais comuns e as próximas atividades.
- **Projetos**: criação, edição e consulta; associação de equipamentos e
  ficheiros; estados planeado, em curso, concluído e cancelado.
- **Atividades**: criação e gestão de atividades, inscrições, participantes,
  equipamentos e ficheiros associados.
- **Equipamentos**: inventário por categoria, quantidade, estado e localização.
- **Reservas**: pedido de equipamento por período, verificação de disponibilidade,
  aprovação, recusa, conclusão e cancelamento.
- **Utilizadores**: gestão de contas e perfis; as contas são desativadas em vez de
  apagadas para preservar o histórico.
- **Assistente IA**: perguntas em linguagem natural sobre os dados do clube, com
  limite de perguntas por utilizador.
- **Pesquisa e paginação**: as listas de projetos, atividades, equipamentos,
  reservas e utilizadores mostram 20 registos por página e permitem pesquisar.
- **Interface responsiva**: layout adaptado a computador, tablet e telemóvel, com
  navegação por teclado e mensagens de erro/sucesso.

## Perfis e permissões

| Perfil | Permissões principais |
| --- | --- |
| Aluno | Consulta projetos, atividades e equipamentos; inscreve-se em atividades; pede e cancela as suas reservas. |
| Professor | Tudo o que o aluno faz, mais gestão de projetos, atividades, equipamentos, reservas e participantes. |
| Administrador | Acesso total, incluindo a gestão de utilizadores e perfis. |

Não existe registo público. As contas são criadas pelo administrador.

## Segurança

O projeto aplica, entre outras medidas:

- Consultas SQL com prepared statements e parâmetros.
- Escape de todo o texto apresentado em HTML com `e()`.
- Tokens CSRF em todos os formulários que alteram dados.
- Palavras-passe com `password_hash()` e `password_verify()`.
- Sessões com `HttpOnly`, `SameSite=Lax`, modo estrito e `Secure` quando o site
  está em HTTPS.
- Cabeçalhos `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` e
  Content Security Policy; HSTS quando a ligação é HTTPS.
- Validação do tipo real dos ficheiros com `finfo` e limite de 2 MB.
- Limitação de tentativas de login por e-mail e por IP.
- `.htaccess` a bloquear listagem de diretórios e acesso a configurações, SQL,
  backups e ficheiros internos.
- `expose_php=Off` no `php.ini` do Apache e do CLI.

Recomendações para produção:

- Alterar a conta `root` do MySQL e usar um utilizador próprio, com palavra-passe.
- Ativar HTTPS com um certificado válido.
- Manter `CCV_DEBUG` desligado e `display_errors=Off`.
- Fazer backups regulares e guardá-los fora da pasta pública.
- Restringir o acesso ao phpMyAdmin e ao `instalar.php` depois da instalação.
- Manter PHP, Apache e MySQL atualizados.

## Testes

O teste de fumo é não destrutivo: só lê a base de dados, verifica a estrutura,
testa funções auxiliares e, opcionalmente, pede a página de login por HTTP.
Pressupõe que o SQL já foi importado e que existe pelo menos um administrador
ativo.

```powershell
# Testes locais (CLI), sem pedidos HTTP
C:\wamp64\bin\php\php8.3.28\php.exe tests\smoke.php

# Incluir a verificação HTTP da página de login
C:\wamp64\bin\php\php8.3.28\php.exe tests\smoke.php --url=http://localhost/meu-projeto/clube-ciencia-viva
```

O script devolve código de saída `0` quando passa e `1` quando há falhas. As
verificações incluem:

- versão do PHP e extensões necessárias;
- ligação à base de dados e nome da base;
- 17 tabelas e 22 chaves estrangeiras;
- existência de pelo menos um administrador ativo e hashes de palavra-passe
  válidos;
- escape de HTML, pesquisa, paginação e links;
- ficheiros `.htaccess` e limite de upload coerente com o `php.ini`;
- cabeçalhos de segurança e ausência de `X-Powered-By` (quando `--url` é usado).

A pasta `tests` está bloqueada por `.htaccess`; o teste foi pensado para ser
executado a partir da linha de comandos.

Checklist manual recomendado antes de entregar:

1. Entrar com administrador, professor e aluno e confirmar os menus visíveis.
2. Criar, editar e consultar um projeto, uma atividade e um equipamento.
3. Inscrever um aluno numa atividade e gerir o estado da participação.
4. Criar uma reserva, aprová-la, concluí-la e cancelá-la.
5. Testar uma reserva sem unidades suficientes e confirmar a mensagem de erro.
6. Carregar um ficheiro válido e recusar um ficheiro com extensão proibida.
7. Pesquisar e paginar as cinco listas principais.
8. Confirmar que um aluno não consegue abrir `utilizadores.php` nem ações de staff.
9. Testar em telemóvel, tablet e computador, com teclado e leitor de ecrã.

## Backups

O script `tools/backup.ps1` cria um ZIP com o dump da base de dados e a pasta
`uploads`. As credenciais são lidas de `includes/config.php` e o ficheiro
temporário com a palavra-passe é apagado no fim.

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tools\backup.ps1
```

Por omissão, o ficheiro é criado em `backups\ccv_backup_AAAAMMDD_HHMMSS.zip` e
contém:

```text
database.sql
uploads/
```

Opções:

```powershell
# Escolher outra pasta de destino
powershell -NoProfile -ExecutionPolicy Bypass -File tools\backup.ps1 -BackupDir D:\Backups\CCV

# Manter a pasta temporária (útil para diagnosticar erros)
powershell -NoProfile -ExecutionPolicy Bypass -File tools\backup.ps1 -KeepTemp
```

A pasta `backups` está protegida por `.htaccess`. Mesmo assim, o ZIP contém dados
sensíveis: guarde-o num local seguro e não o deixe acessível pela web.

### Restaurar um backup

1. Colocar o site em manutenção ou parar o Apache para impedir escritas.
2. Extrair o ZIP para uma pasta temporária.
3. Importar `database.sql` no phpMyAdmin. O dump inclui `DROP TABLE IF EXISTS`,
   por isso substitui as tabelas atuais.
4. Copiar o conteúdo de `uploads/` para a pasta `uploads/` do projeto.
5. Confirmar as permissões e abrir o site.
6. Testar o login, as listas, as reservas e o download de um ficheiro.

Antes de restaurar sobre a base real, testar sempre a restauração numa base de
dados separada. Nunca restaurar sem ter um backup recente e confirmado.

## Estrutura

```text
clube-ciencia-viva/
├── api/                 endpoints de disponibilidade e do assistente
├── backups/             backups locais (bloqueados por .htaccess)
├── css/                 estilos
├── database/            esquema SQL e proteção da pasta
├── includes/            configuração, ligação à BD, autenticação, layout e funções
├── js/                  scripts da interface, gráficos, reservas e assistente
├── tests/               teste de fumo (bloqueado por .htaccess)
├── tools/               script de backup
├── uploads/             ficheiros carregados (bloqueados por .htaccess)
├── index.php            painel inicial
├── projetos.php         gestão de projetos
├── atividades.php       gestão de atividades e inscrições
├── equipamentos.php     inventário
├── reservas.php         reservas de equipamento
├── utilizadores.php     gestão de utilizadores (administrador)
├── assistente.php       interface do assistente de IA
├── instalar.php         criação do primeiro administrador
└── login.php            autenticação
```

## Resolução de problemas

- **“Não foi possível ligar à base de dados”**: confirmar que o MySQL está a
  correr, que a base `clube_ciencia_viva` existe e que `includes/config.php` tem
  o nome, utilizador e palavra-passe corretos.
- **O serviço MySQL não arranca e o log diz `ibdata1 must be writable`**:
  confirmar que não há outro `mysqld.exe` a usar a pasta `data`; parar o processo
  antigo com `mysqladmin -u root shutdown`, confirmar que a porta 3306 fechou e
  iniciar o serviço `wampmysqld64`. Verificar as permissões da pasta `data` e o
  antivírus. Não apagar nem substituir `ibdata1`.
- **MySQL parado depois de reiniciar o Windows**: neste ambiente, os serviços
  `wampmysqld64` e `wampapache64` estão configurados para arranque automático.
  Se estiverem em modo manual, abrir o WAMP e escolher “Start All Services” ou,
  numa consola de administrador, executar `Start-Service wampmysqld64` e
  `Start-Service wampapache64`.
- **Erro 500**: consultar `C:\wamp64\logs\php_error.log` e o log de erros do
  Apache. Para ver os detalhes no browser, definir `CCV_DEBUG=1` apenas em
  desenvolvimento.
- **Upload recusado**: confirmar `upload_max_filesize` no `php.ini`, o limite
  `UPLOAD_MAX_BYTES` e as permissões da pasta `uploads`.
- **Assistente sem resposta**: confirmar que o Ollama está a correr e que o
  modelo indicado em `OLLAMA_MODEL` foi descarregado.
- **HTTPS local**: configurar um certificado no Apache do WampServer. O
  cabeçalho HSTS e o cookie `Secure` só aparecem quando o pedido é HTTPS.
- **`X-Powered-By` visível**: confirmar `expose_php=Off` no `php.ini` usado pelo
  Apache e reiniciar o serviço.

## Limitações

- As regras de `.htaccess` só são aplicadas pelo Apache. O servidor embutido
  `php -S` não as lê e não deve ser usado como servidor de produção.
- O limite de upload da aplicação é 2 MB, alinhado com o `php.ini` local.
- O assistente depende de um serviço externo local (Ollama) e não é necessário
  para as restantes funcionalidades.
- A aplicação não tem registo público nem recuperação de palavra-passe por
  e-mail; a reposição é feita pelo administrador.
