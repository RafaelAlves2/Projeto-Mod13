<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

$erros = [];

/* A tabela pode ainda não existir se o SQL não tiver sido importado. */
try {
    $totalUtilizadores = (int)valor('SELECT COUNT(*) FROM utilizador');
} catch (Throwable $t) {
    error_log('instalar: ' . $t->getMessage());
    cabecalho('Instalação', '', true);
    ?>
    <section class="login">
      <h1>Instalação</h1>
      <p class="lead">Clube de Ciência Viva</p>
      <p class="flash" role="alert">
        A base de dados ainda não está preparada. Importa primeiro
        <code>database/clube_ciencia_viva.sql</code> no phpMyAdmin e volta a esta página.
      </p>
    </section>
    <?php
    rodape();
    exit;
}

if ($totalUtilizadores > 0) {
    flash('A instalação já foi concluída. Entra com a tua conta.');
    redirecionar('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();

    $nome      = post('nome');
    $email     = strtolower(post('email'));
    $password  = (string)($_POST['password'] ?? '');
    $confirmar = (string)($_POST['password2'] ?? '');

    if (mb_strlen($nome) < 2 || mb_strlen($nome) > 100) {
        $erros[] = 'O nome deve ter entre 2 e 100 caracteres.';
    }
    if (!email_valido($email)) {
        $erros[] = 'Indica um e-mail válido (máximo 150 caracteres).';
    }
    if (strlen($password) < 8 || strlen($password) > 200) {
        $erros[] = 'A palavra-passe deve ter entre 8 e 200 caracteres.';
    }
    if ($password !== $confirmar) {
        $erros[] = 'As palavras-passe não coincidem.';
    }

    if (!$erros) {
        try {
            /* A condição NOT EXISTS é verificada pelo próprio MySQL, por isso
               duas submissões ao mesmo tempo não criam dois administradores. */
            $st = db()->prepare(
                'INSERT INTO utilizador (nome, email, password_hash, perfil, ativo)
                 SELECT ?, ?, ?, \'administrador\', 1 FROM DUAL
                  WHERE NOT EXISTS (SELECT 1 FROM utilizador)'
            );
            $st->execute([$nome, $email, password_hash($password, PASSWORD_DEFAULT)]);

            if ($st->rowCount() === 1) {
                flash('Administrador criado com sucesso. Já podes entrar.');
                redirecionar('login.php');
            }
            $erros[] = 'Já existe um utilizador. A instalação só pode ser feita uma vez.';
        } catch (Throwable $t) {
            error_log('instalar: ' . $t->getMessage());
            $erros[] = 'Não foi possível criar o administrador. Tenta novamente.';
        }
    }
}

cabecalho('Instalação', '', true);
/* CSS deste bloco:
   Encontras o CSS da caixa de login (.login, .lead) no ficheiro `css/style.css`, na secção `cartoes`.
   Encontras o CSS dos campos e do botão (.stack, label, input, .btn) no ficheiro `css/style.css`, na secção `formularios`.
   Encontras o CSS das mensagens (.flash) no ficheiro `css/style.css`, na secção `avisos`. */
?>
<section class="login">
  <h1>Instalação</h1>
  <p class="lead">Cria o primeiro administrador</p>

  <p class="hint">Esta página só funciona enquanto não existir nenhum utilizador.</p>

  <?php foreach ($erros as $erro): ?>
    <p class="flash" role="alert"><?= e($erro) ?></p>
  <?php endforeach; ?>

  <form class="stack" method="post" action="">
    <?= csrf_campo() ?>
    <label>Nome<input name="nome" type="text" required maxlength="100" autocomplete="name" value="<?= e($_POST['nome'] ?? '') ?>"></label>
    <label>E-mail<input name="email" type="email" required maxlength="150" autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>"></label>
    <label>Palavra-passe<input name="password" type="password" required minlength="8" maxlength="200" autocomplete="new-password"></label>
    <label>Repetir palavra-passe<input name="password2" type="password" required minlength="8" maxlength="200" autocomplete="new-password"></label>
    <button class="btn">Criar administrador</button>
  </form>
</section>
<?php rodape(); ?>
