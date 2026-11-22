<?php
/* ==========================================================================
   FICHEIRO: includes/layout.php
   Para que serve: a "moldura" de todas as páginas (o mesmo HTML de antes):
   barra lateral escura à esquerda + conteúdo à direita.
     cabecalho()  abre a página e desenha a barra lateral
     rodape()     fecha a página e carrega os scripts
     erro_pagina() (definida em funcoes.php) usa esta moldura quando disponível
   ========================================================================== */

/* cabecalho($titulo, $ativa, $solo)
   $ativa = nome do link a destacar no menu; $solo = true para o ecrã de login.
   CSS deste bloco:
     Encontras o CSS da barra e do logo no ficheiro `css/style.css`, na secção `barra-lateral`.
     Encontras o CSS dos links do menu no ficheiro `css/style.css`, na secção `nav`.
     Encontras o CSS da zona de conteúdo no ficheiro `css/style.css`, na secção `conteudo`. */
function cabecalho(string $titulo, string $ativa = '', bool $solo = false): void
{
    $u = utilizador_atual();
    $links = [
        'inicio'       => ['index.php',        'Início'],
        'projetos'     => ['projetos.php',     'Projetos'],
        'atividades'   => ['atividades.php',   'Atividades'],
        'equipamentos' => ['equipamentos.php', 'Equipamentos'],
        'reservas'     => ['reservas.php',     'Reservas'],
        'assistente'   => ['assistente.php',   'Assistente IA'],
    ];
    ?><!doctype html>
<html lang="pt-PT">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="Clube de Ciência Viva: projetos, atividades, equipamentos e reservas do clube.">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<meta name="base" content="<?= e(BASE_URL) ?>">
<title><?= e($titulo) ?> · Clube de Ciência Viva</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;800&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/css/style.css">
</head>
<body>
<?php if (!$solo): ?><a class="skip" href="#conteudo">Saltar para o conteúdo</a><?php endif; ?>
<?php if (!$solo && $u): ?>
<aside class="side">
  <div class="side-top">
    <a class="brand" href="<?= e(BASE_URL) ?>/index.php">CCV</a>
    <button class="menu-btn" type="button" aria-expanded="false" aria-controls="menu-principal">Menu</button>
  </div>
  <nav id="menu-principal" aria-label="Principal">
    <?php foreach ($links as $chave => [$pagina, $texto]): ?>
      <a href="<?= e(BASE_URL . '/' . $pagina) ?>"<?= $chave === $ativa ? ' aria-current="page"' : '' ?>><?= e($texto) ?></a>
    <?php endforeach; ?>
    <?php if (eh_admin($u)): ?>
      <a class="staff" href="<?= e(BASE_URL) ?>/utilizadores.php"<?= $ativa === 'utilizadores' ? ' aria-current="page"' : '' ?>>Utilizadores</a>
    <?php endif; ?>
  </nav>
  <form class="who" method="post" action="<?= e(BASE_URL) ?>/logout.php">
    <span><?= e($u['nome']) ?></span>
    <?= csrf_campo() ?>
    <button class="link">Sair</button>
  </form>
</aside>
<main class="with-side" id="conteudo" tabindex="-1">
<?php else: ?>
<main class="solo" id="conteudo" tabindex="-1">
<?php endif;
    if (!$solo) mostrar_flash();
}

/* rodape($scripts): $scripts = lista de ficheiros em js/ a carregar nesta página. */
function rodape(array $scripts = []): void
{
    echo "\n</main>\n";
    foreach (array_merge(['animacoes.js'], $scripts) as $s) {
        echo '<script src="' . e(BASE_URL) . '/js/' . e($s) . '" defer></script>' . "\n";
    }
    echo "</body>\n</html>";
}

