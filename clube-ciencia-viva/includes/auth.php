<?php
/* ==========================================================================
   FICHEIRO: includes/auth.php
   Para que serve: sessão, login e permissões. Inclui este ficheiro no topo
   de TODAS as páginas. Tem também a proteção CSRF (token escondido nos
   formulários que impede pedidos falsos vindos de outros sites).
   ========================================================================== */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/funcoes.php';

/* Cabeçalhos de segurança simples. */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
/* CSP: só scripts/estilos locais (e as folhas de letra do Google). Não há
   `onchange`/`style` inline no HTML; o JS está todo em ficheiros próprios. */
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data: https:; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self'; connect-src 'self'");
$__https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
if ($__https) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

/* Sessão com cookie protegido (não acessível por JavaScript). O atributo
   Secure só é aplicado quando o site está em HTTPS, para o Wamp local
   continuar a funcionar. O modo estrito rejeita IDs de sessão inventados. */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('ccv_sessao');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $__https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
unset($__https);

/* --- Quem está com sessão? Vai sempre à BD, por isso uma conta desativada
       deixa de funcionar logo. ------------------------------------------- */
function utilizador_atual(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $u = linha('SELECT id_utilizador, nome, email, perfil FROM utilizador WHERE id_utilizador = ? AND ativo = 1',
                   [$_SESSION['uid']]);
        if (!$u) unset($_SESSION['uid']);
    }
    return $u;
}

function eh_admin(?array $u): bool { return $u && $u['perfil'] === 'administrador'; }
function eh_staff(?array $u): bool { return $u && in_array($u['perfil'], ['administrador', 'professor'], true); }

/* --- Guardas: chama no topo da página que queres proteger ---------------- */
function exigir_login(): array
{
    $u = utilizador_atual();
    if (!$u) redirecionar('login.php');
    return $u;
}
function exigir_staff(): array
{
    $u = exigir_login();
    if (!eh_staff($u)) erro_pagina(403, 'Só professores e administradores podem fazer isto.');
    return $u;
}
function exigir_admin(): array
{
    $u = exigir_login();
    if (!eh_admin($u)) erro_pagina(403, 'Só o administrador pode fazer isto.');
    return $u;
}

/* --- CSRF ---------------------------------------------------------------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
/* Coloca isto dentro de CADA <form method="post">. */
function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
/* Chama no início de qualquer código que trate um POST. */
function csrf_verificar(): void
{
    $enviado = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($enviado) || !hash_equals(csrf_token(), $enviado)) {
        erro_pagina(400, 'Pedido inválido (token de segurança em falta). Volta atrás e tenta de novo.');
    }
}

/* --- Login --------------------------------------------------------------- */
/* Bloqueia tentativas por email e por IP, guardadas na tabela tentativas_login
   (sobrevive a limpar cookies e a mudar de sessão). Devolve null em sucesso,
   ou o texto do erro. */
function tentar_login(string $email, string $password): ?string
{
    $email = strtolower(trim($email));
    if (!email_valido($email)) {
        return 'E-mail ou palavra-passe errados (ou conta desativada).';
    }

    $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $pdo = db();

    // 1. Já está bloqueado? (conta falhas na janela em curso)
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM tentativas_login
          WHERE email = ? AND sucesso = 0
            AND criado_em > (NOW() - INTERVAL ' . BLOQUEIO_MINUTOS . ' MINUTE)'
    );
    $st->execute([$email]);
    $falhasEmail = (int)$st->fetchColumn();

    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM tentativas_login
          WHERE ip = ? AND sucesso = 0
            AND criado_em > (NOW() - INTERVAL ' . BLOQUEIO_MINUTOS . ' MINUTE)'
    );
    $st->execute([$ip]);
    $falhasIp = (int)$st->fetchColumn();

    if ($falhasEmail >= MAX_TENTATIVAS_EMAIL || $falhasIp >= MAX_TENTATIVAS_IP) {
        return 'Demasiadas tentativas falhadas. Tenta novamente daqui a ' . BLOQUEIO_MINUTOS . ' minutos.';
    }

    // 2. Verificar credenciais
    $u = linha('SELECT id_utilizador, password_hash, ativo FROM utilizador WHERE email = ?', [$email]);
    if ($u && (int)$u['ativo'] === 1 && password_verify($password, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id_utilizador'];

        // Se o algoritmo de hash mudou (ex.: bcrypt -> argon2), atualiza agora
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            executa('UPDATE utilizador SET password_hash = ? WHERE id_utilizador = ?',
                    [password_hash($password, PASSWORD_DEFAULT), $u['id_utilizador']]);
        }

        // Limpa as falhas deste email e regista o sucesso
        executa('DELETE FROM tentativas_login WHERE email = ? AND sucesso = 0', [$email]);
        executa('INSERT INTO tentativas_login (email, ip, sucesso) VALUES (?, ?, 1)', [$email, $ip]);
        return null;
    }

    // 3. Falhou: regista e devolve mensagem genérica
    executa('INSERT INTO tentativas_login (email, ip, sucesso) VALUES (?, ?, 0)', [$email, $ip]);
    return 'E-mail ou palavra-passe errados (ou conta desativada).';
}