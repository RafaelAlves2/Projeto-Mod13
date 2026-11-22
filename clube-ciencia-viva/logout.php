<?php
/* ==========================================================================
   FICHEIRO: logout.php
   Para que serve: termina a sessão (botão "Sair" da barra lateral).
   Só aceita POST com token CSRF, para ninguém te "desligar" com um link.
   Apaga os dados da sessão e muda o identificador (session_regenerate_id),
   por isso a sessão antiga deixa de servir.
   ========================================================================== */
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $_SESSION = [];                    // esquece quem estava com sessão
    session_regenerate_id(true);       // sessão nova; a antiga é destruída
    flash('Sessão terminada.');
}
redirecionar('login.php');
