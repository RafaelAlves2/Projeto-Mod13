<?php
/* ==========================================================================
   FICHEIRO: login.php
   Para que serve: o ecrã de Entrar. Confere o e-mail e a palavra-passe com
   a tabela `utilizador` (função tentar_login em includes/auth.php).
   Não há registo público: as contas são criadas pelo administrador.
   ========================================================================== */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

if (utilizador_atual()) redirecionar('index.php');

/* Limpeza barata: apaga tentativas de login com mais de 7 dias (1 em cada 100
   visitas ao login, para não pesar em cada pedido). */
if (random_int(1, 100) === 1) {
    executa('DELETE FROM tentativas_login WHERE criado_em < (NOW() - INTERVAL 7 DAY)');
}

$erro = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $email = strtolower(post('email'));
    $password = (string)($_POST['password'] ?? '');
    $erro = email_valido($email)
        ? tentar_login($email, $password)
        : 'E-mail ou palavra-passe errados (ou conta desativada).';
    if ($erro === null) redirecionar('index.php');
}
$semUtilizadores = (int)valor('SELECT COUNT(*) FROM utilizador') === 0;

cabecalho('Entrar', '', true);
/* CSS deste bloco:
   Encontras o CSS da caixa de login (.login, .lead) no ficheiro `css/style.css`, na secção `cartoes`.
   Encontras o CSS dos campos e do botão (.stack, label, input, .btn) no ficheiro `css/style.css`, na secção `formularios`.
   Encontras o CSS da mensagem de erro (.flash) no ficheiro `css/style.css`, na secção `avisos`.
   Encontras a animação de entrada da caixa no ficheiro `css/style.css`, na secção `animacao`. */
?>
<section class="login">
  <h1>Entrar</h1>
  <p class="lead">Clube de Ciência Viva</p>
  <?php mostrar_flash(); ?>
  <?php if ($erro): ?><p class="flash" role="alert"><?= e($erro) ?></p><?php endif; ?>
  <?php if ($semUtilizadores): ?>
    <p class="flash">Ainda não existe nenhum utilizador. <a href="<?= e(BASE_URL) ?>/instalar.php">Cria o administrador</a>.</p>
  <?php endif; ?>
  <form class="stack" method="post" action="">
    <?= csrf_campo() ?>
    <label>E-mail<input name="email" type="email" required autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>"></label>
    <label>Palavra-passe<input name="password" type="password" required autocomplete="current-password"></label>
    <button class="btn">Entrar</button>
  </form>
  <p class="hint">Não tens conta ou esqueceste-te da palavra-passe? Pede ao administrador do clube.</p>
</section>
<?php rodape(); ?>