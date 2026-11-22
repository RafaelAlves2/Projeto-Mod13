<?php
/* ==========================================================================
   FICHEIRO: download.php
   Para que serve: entrega um ficheiro enviado. Só quem tem sessão iniciada
   consegue descarregar, e só ficheiros ligados a um projeto ou atividade.
   (A pasta uploads/ está bloqueada ao acesso direto pelo .htaccess.)
   ========================================================================== */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
exigir_login();

$id = pedido_int('id');
$f = linha('SELECT nome, caminho, tipo FROM ficheiro f
            WHERE id_ficheiro = ? AND (EXISTS (SELECT 1 FROM projeto_ficheiro p WHERE p.id_ficheiro = f.id_ficheiro)
                                    OR EXISTS (SELECT 1 FROM atividade_ficheiro a WHERE a.id_ficheiro = f.id_ficheiro))', [$id]);
$caminho = $f ? UPLOAD_DIR . basename($f['caminho']) : '';
if (!$f || !is_file($caminho)) erro_pagina(404, 'Ficheiro não encontrado.');

/* Liberta já o lock da sessão: o readfile() pode demorar e, sem isto, outros
   pedidos do mesmo utilizador ficariam à espera que o download terminasse. */
session_write_close();

$nome = str_replace(['"', "\r", "\n", '\\'], '', $f['nome']);
header('Content-Type: application/octet-stream');                       // força descarregar
header('Content-Disposition: attachment; filename="' . $nome . '"; filename*=UTF-8\'\'' . rawurlencode($nome));
header('Content-Length: ' . filesize($caminho));
readfile($caminho);
