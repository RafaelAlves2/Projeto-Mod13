<?php
/* ==========================================================================
   FICHEIRO: api/ia.php
   Para que serve: recebe uma pergunta (JSON) do js/assistente.js, envia-a ao
   Ollama (servidor de IA no teu computador), guarda pergunta e resposta na
   tabela pergunta_ia e devolve a resposta em JSON.
   Pedido (POST):  {"pergunta":"...", "categoria":"Física"}
   Resposta:       {"ok":true,"resposta":"..."}  ou  {"ok":false,"erro":"..."}
   Segurança: só com sessão iniciada, token CSRF, limite de perguntas por hora
   e tamanho máximo da pergunta. A configuração está em includes/config.php.
   ========================================================================== */
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

function responder(int $codigo, array $corpo)
{
    http_response_code($codigo);
    exit(json_encode($corpo, JSON_UNESCAPED_UNICODE));
}

$u = utilizador_atual();
if (!$u)                                         responder(401, ['ok' => false, 'erro' => 'A sessão expirou. Volta a entrar.']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST')       responder(405, ['ok' => false, 'erro' => 'Método não permitido.']);
if (!hash_equals(csrf_token(), (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')))
                                                 responder(400, ['ok' => false, 'erro' => 'Token de segurança inválido. Recarrega a página.']);

$corpo = json_decode((string)file_get_contents('php://input'), true);
$pergunta = trim((string)($corpo['pergunta'] ?? ''));
$categoria = mb_substr(trim((string)($corpo['categoria'] ?? 'Geral')), 0, 60);
if ($pergunta === '' || mb_strlen($pergunta) > 1000) responder(400, ['ok' => false, 'erro' => 'Escreve uma pergunta com até 1000 caracteres.']);

/* Limite por hora, para uma pessoa não sobrecarregar o computador. */
$ultimaHora = (int)valor('SELECT COUNT(*) FROM pergunta_ia WHERE id_utilizador = ? AND data_hora > (NOW() - INTERVAL 1 HOUR)', [$u['id_utilizador']]);
if ($ultimaHora >= IA_MAX_PERGUNTAS_HORA) responder(429, ['ok' => false, 'erro' => 'Atingiste o limite de perguntas por hora. Tenta mais tarde.']);

/* Importante: libertar a sessão, senão o resto do site fica "preso" enquanto a IA pensa. */
session_write_close();

$sistema = 'És o assistente do Clube de Ciência Viva do Agrupamento de Escolas Manuel Teixeira Gomes, em Portimão. '
         . 'Respondes em português europeu, de forma clara e simples, para alunos do ensino secundário. '
         . 'Ajudas com ciência, tecnologia e experiências. Se a pergunta envolver uma experiência, indica as precauções de segurança. '
         . 'Se não souberes algo, diz que não sabes; não inventes factos, nem informações sobre o clube que não te foram dadas. '
         . 'Se a pergunta não tiver nada a ver com ciência, tecnologia ou o clube, explica gentilmente que só podes ajudar nesses temas.';

$payload = json_encode([
    'model' => OLLAMA_MODEL, 'stream' => false,
    'messages' => [['role' => 'system', 'content' => $sistema], ['role' => 'user', 'content' => '[' . $categoria . '] ' . $pergunta]],
], JSON_UNESCAPED_UNICODE);

/* --- Chamar o Ollama (com cURL; se não houver, usa file_get_contents) ---- */
$resposta = null; $http = 0; $erroLigacao = '';
if (function_exists('curl_init')) {
    $ch = curl_init(OLLAMA_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => OLLAMA_TIMEOUT,
    ]);
    $resposta = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erroLigacao = curl_error($ch);
    curl_close($ch);
} else {
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n",
                                             'content' => $payload, 'timeout' => OLLAMA_TIMEOUT, 'ignore_errors' => true]]);
    $resposta = @file_get_contents(OLLAMA_URL, false, $ctx);
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) $http = (int)$m[1];
    if ($resposta === false) $erroLigacao = 'sem ligação';
}

if ($resposta === false || $resposta === null || $http === 0) {
    error_log('Ollama: ' . $erroLigacao);
    responder(502, ['ok' => false, 'erro' => 'Não consegui falar com o Ollama. Confirma que está ligado (abre a aplicação Ollama ou corre "ollama serve" no cmd).']);
}
if ($http === 404) responder(502, ['ok' => false, 'erro' => 'O modelo "' . OLLAMA_MODEL . '" não existe. No cmd corre "ollama pull ' . OLLAMA_MODEL . '" ou muda OLLAMA_MODEL em includes/config.php.']);

$j = json_decode($resposta, true);
$texto = trim((string)($j['message']['content'] ?? ''));
if ($http >= 400 || $texto === '') {
    error_log('Ollama resposta inesperada (' . $http . '): ' . mb_substr((string)$resposta, 0, 300));
    responder(502, ['ok' => false, 'erro' => 'O Ollama não devolveu uma resposta válida. Tenta outra vez.']);
}

executa('INSERT INTO pergunta_ia (pergunta, resposta, categoria, id_utilizador) VALUES (?, ?, ?, ?)',
        [$pergunta, $texto, $categoria, $u['id_utilizador']]);
responder(200, ['ok' => true, 'resposta' => $texto]);
