<?php
/* ==========================================================================
   FICHEIRO: includes/config.php
   Para que serve: as definições do projeto num só sítio (base de dados,
   pasta de uploads, assistente de IA, proteção de login). Se algo não
   funcionar no teu computador, é provavelmente aqui que tens de mexer.
   ========================================================================== */

/* --- Base de dados (valores normais do WampServer) ---------------------- */
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');            // se tiveres MySQL e MariaDB ao mesmo tempo, pode ser 3307
define('DB_NAME', 'clube_ciencia_viva');
define('DB_USER', 'root');
define('DB_PASS', '');                // no WampServer o root vem sem palavra-passe

/* --- Endereço do site (usado nos links, no CSS e no JavaScript) ---------- */
/* Calculado sozinho a partir da localização real do projeto.
   Funciona em Wamp (Windows) e em Linux. Se o projeto estiver na raiz do
   servidor, BASE_URL fica "". Se estiver em /meu-projeto/clube-ciencia-viva,
   fica "/meu-projeto/clube-ciencia-viva". Assim mudar de pasta não obriga
   a editar isto.
   Nota: stripos() em vez de str_starts_with() porque no WampServer o
   DOCUMENT_ROOT pode vir com "c:/..." e o realpath com "C:/...". */
$__raiz = rtrim(str_replace('\\', '/', (string)realpath(__DIR__ . '/..')), '/');
$__doc  = rtrim(str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
if ($__doc !== '' && stripos($__raiz, $__doc) === 0) {
    define('BASE_URL', substr($__raiz, strlen($__doc)));
} else {
    define('BASE_URL', '');
}
unset($__raiz, $__doc);

/* --- Uploads de ficheiros ------------------------------------------------
   O limite da aplicação tem de coincidir com upload_max_filesize do php.ini
   (no WampServer está em 2M). Assim a mensagem mostrada ao utilizador é
   verdadeira e um ficheiro maior é recusado antes de chegar ao servidor. */
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('UPLOAD_MAX_BYTES', 2 * 1024 * 1024);   // 2 MB (igual a upload_max_filesize=2M)
// extensão => tipos MIME aceites (verificados com finfo, não só pelo nome)
define('UPLOAD_TIPOS', [
    'pdf'  => ['application/pdf'],
    'png'  => ['image/png'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'gif'  => ['image/gif'],
    'txt'  => ['text/plain'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
    'zip'  => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
]);

/* --- Assistente de IA (Ollama a correr no teu computador) ---------------- */
define('OLLAMA_URL', 'http://localhost:11434/api/chat');
define('OLLAMA_MODEL', 'llama3.2');   // muda para um modelo que tenhas: no cmd, "ollama list"
define('OLLAMA_TIMEOUT', 120);        // segundos à espera da resposta
define('IA_MAX_PERGUNTAS_HORA', 30);  // limite por utilizador, para não sobrecarregar

/* --- Proteção contra tentativas repetidas de login ---------------------- */
define('MAX_TENTATIVAS_EMAIL', 5);    // falhas por email, na janela abaixo
define('MAX_TENTATIVAS_IP',    20);   // falhas por IP, na janela abaixo
define('BLOQUEIO_MINUTOS',     15);   // janela de tempo considerada

/* --- Erros ---------------------------------------------------------------
   Por omissão o site está em modo de produção: não mostra erros ao visitante,
   mas registra-os no log do PHP. Para desenvolveres localmente, define a
   variável de ambiente CCV_DEBUG=1 antes de iniciar o Apache/PHP.
   Exemplo no PowerShell:  $env:CCV_DEBUG='1'; php -S 127.0.0.1:8080
   ------------------------------------------------------------------------ */
$__debug = getenv('CCV_DEBUG');
if ($__debug === false && isset($_SERVER['CCV_DEBUG'])) {
    $__debug = (string)$_SERVER['CCV_DEBUG'];
}
define('DEBUG', $__debug !== false && filter_var($__debug, FILTER_VALIDATE_BOOLEAN));
unset($__debug);

ini_set('display_errors', DEBUG ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

/* O php.ini do WAMP tem expose_php=On por omissão. Removemos o cabeçalho
   X-Powered-By em runtime para não revelar a versão do PHP. */
header_remove('X-Powered-By');

date_default_timezone_set('Europe/Lisbon');