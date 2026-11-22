<?php
/* ==========================================================================
   FICHEIRO: utilizadores.php
   Para que serve: gestão de UTILIZADORES e PERMISSÕES. Só o administrador.
   Cria contas, muda o perfil (administrador, professor, aluno), ativa/desativa
   e repõe palavras-passe. Não há "apagar": desativa-se a conta (assim não se
   perdem inscrições, reservas e ficheiros ligados a essa pessoa).
   Ações (?acao=): lista (por omissão), novo, editar. Formulário (POST): guardar.
   Tabela usada: utilizador.
   ========================================================================== */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

$eu = exigir_admin();
const PERFIS = ['administrador', 'professor', 'aluno'];

$erros = [];
$dados = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('op') === 'guardar') {
    csrf_verificar();
    $id = pedido_int('id');
    $dados = [
        'nome' => post('nome'), 'email' => strtolower(post('email')),
        'perfil' => escolha(post('perfil'), PERFIS, 'aluno'), 'ativo' => isset($_POST['ativo']) ? 1 : 0,
    ];
    $pw = (string)($_POST['password'] ?? '');
    $atual = $id ? linha('SELECT * FROM utilizador WHERE id_utilizador = ?', [$id]) : null;
    if ($id && !$atual) erro_pagina(404, 'Utilizador não encontrado.');

    if ($dados['nome'] === '' || mb_strlen($dados['nome']) > 100)       $erros[] = 'O nome é obrigatório (até 100 letras).';
    if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL) || strlen($dados['email']) > 150) $erros[] = 'E-mail inválido.';
    elseif (valor('SELECT COUNT(*) FROM utilizador WHERE email = ? AND id_utilizador <> ?', [$dados['email'], $id]))
                                                                        $erros[] = 'Já existe um utilizador com esse e-mail.';
    if (!$id && strlen($pw) < 8)                                        $erros[] = 'A palavra-passe tem de ter pelo menos 8 caracteres.';
    if ($id && $pw !== '' && strlen($pw) < 8)                           $erros[] = 'A nova palavra-passe tem de ter pelo menos 8 caracteres.';
    if ($id === (int)$eu['id_utilizador'] && ($dados['perfil'] !== 'administrador' || !$dados['ativo']))
                                                                        $erros[] = 'Não podes retirar o teu próprio acesso de administrador.';
    if (!$erros) {
        if ($id) {
            executa('UPDATE utilizador SET nome = ?, email = ?, perfil = ?, ativo = ? WHERE id_utilizador = ?',
                    [$dados['nome'], $dados['email'], $dados['perfil'], $dados['ativo'], $id]);
            if ($pw !== '') executa('UPDATE utilizador SET password_hash = ? WHERE id_utilizador = ?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
            flash('Utilizador guardado.');
        } else {
            executa('INSERT INTO utilizador (nome, email, password_hash, perfil, ativo) VALUES (?, ?, ?, ?, ?)',
                    [$dados['nome'], $dados['email'], password_hash($pw, PASSWORD_DEFAULT), $dados['perfil'], $dados['ativo']]);
            flash('Utilizador criado.');
        }
        redirecionar('utilizadores.php');
    }
    $_GET['acao'] = $id ? 'editar' : 'novo';
    $_GET['id'] = $id;
}

$acao = $_GET['acao'] ?? 'lista';

/* ------------------------------ Formulário -------------------------------- */
if (in_array($acao, ['novo', 'editar'], true)) {
    $id = $acao === 'editar' ? pedido_int('id') : 0;
    if (!$dados) {
        $dados = ['nome' => '', 'email' => '', 'perfil' => 'aluno', 'ativo' => 1];
        if ($id) {
            $x = linha('SELECT nome, email, perfil, ativo FROM utilizador WHERE id_utilizador = ?', [$id]);
            if (!$x) erro_pagina(404, 'Utilizador não encontrado.');
            $dados = $x;
        }
    }
    $proprio = $id === (int)$eu['id_utilizador'];
    cabecalho($id ? 'Editar utilizador' : 'Novo utilizador', 'utilizadores');
    /* CSS deste bloco: caixa (.doc) na secção `cartoes`; formulário (.stack, label, input, select, .btn) e o
       campo de verificação na secção `formularios` e `extras`; texto pequeno (.hint) na secção `avisos`
       — tudo em `css/style.css`. */
    ?>
    <section class="doc wide fade-in">
      <h1><?= $id ? 'Editar utilizador' : 'Novo utilizador' ?></h1>
      <?php foreach ($erros as $er): ?><p class="flash" role="alert"><?= e($er) ?></p><?php endforeach; ?>
      <form class="stack" method="post" action="<?= e(BASE_URL) ?>/utilizadores.php">
        <?= csrf_campo() ?><input type="hidden" name="op" value="guardar"><input type="hidden" name="id" value="<?= $id ?>">
        <label>Nome<input name="nome" required maxlength="100" value="<?= e($dados['nome']) ?>"></label>
        <label>E-mail<input type="email" name="email" required maxlength="150" value="<?= e($dados['email']) ?>"></label>
        <label>Perfil
          <select name="perfil"<?= $proprio ? ' disabled' : '' ?>><?php foreach (PERFIS as $p): ?>
            <option value="<?= e($p) ?>"<?= $dados['perfil'] === $p ? ' selected' : '' ?>><?= e(rotulo($p)) ?></option><?php endforeach; ?>
          </select></label>
        <?php if ($proprio): ?><input type="hidden" name="perfil" value="administrador"><?php endif; ?>
        <label><span><input type="checkbox" name="ativo" value="1"<?= $dados['ativo'] ? ' checked' : '' ?><?= $proprio ? ' disabled' : '' ?>> Conta ativa</span></label>
        <?php if ($proprio): ?><input type="hidden" name="ativo" value="1"><?php endif; ?>
        <label>Palavra-passe<?= $id ? ' (deixa em branco para não alterar)' : ' (mín. 8)' ?>
          <input type="password" name="password" minlength="8" autocomplete="new-password"<?= $id ? '' : ' required' ?>></label>
        <div class="linha-fim"><a class="btn sec" href="<?= e(BASE_URL) ?>/utilizadores.php">Cancelar</a><button class="btn">Guardar</button></div>
      </form>
    </section>
    <?php rodape(); exit;
}

/* ------------------------------- Lista ------------------------------------ */
$q = pesquisa_get();
$where = [];
$params = [];
if ($q !== '') { $where[] = pesquisa_sql(['nome', 'email'], $q, $params); }
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$total = (int)valor('SELECT COUNT(*) FROM utilizador' . $whereSql, $params);
$pag = paginacao_calcular($total);
$us = consulta('SELECT id_utilizador, nome, email, perfil, ativo FROM utilizador' . $whereSql
    . ' ORDER BY ativo DESC, perfil, nome LIMIT ' . (int)$pag['por'] . ' OFFSET ' . (int)$pag['offset'], $params);
cabecalho('Utilizadores', 'utilizadores');
/* CSS deste bloco: caixa (.doc, .lead) na secção `cartoes`; tabela e etiquetas (.tag) na secção `tabelas`;
   botão (.btn, .link) na secção `formularios` — tudo em `css/style.css`. */
?>
<section class="doc wide fade-in">
  <h1>Utilizadores</h1>
  <p class="lead">Contas e permissões do clube.</p>
  <form class="row" method="get" action="">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Pesquisar por nome ou e-mail" aria-label="Pesquisar utilizadores">
    <button class="btn sec">Pesquisar</button>
    <a class="btn" href="<?= e(BASE_URL) ?>/utilizadores.php?acao=novo">Novo utilizador</a>
  </form>
  <?php if ($us): ?>
  <div class="scroll"><table>
    <thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($us as $x): ?>
      <tr>
        <td><?= e($x['nome']) ?></td><td><?= e($x['email']) ?></td><td><?= e(rotulo($x['perfil'])) ?></td>
        <td><span class="tag <?= $x['ativo'] ? 'on' : 'off' ?>"><?= $x['ativo'] ? 'Ativo' : 'Desativado' ?></span></td>
        <td><a class="link" href="<?= e(BASE_URL) ?>/utilizadores.php?acao=editar&amp;id=<?= (int)$x['id_utilizador'] ?>">Editar</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= paginacao_links($pag) ?>
  <?php else: aviso_lista_vazia('utilizadores.php', $q, '', 'utilizadores', ''); endif; ?>
</section>
<?php rodape(); ?>
