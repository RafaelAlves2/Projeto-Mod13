<?php
/* ==========================================================================
   FICHEIRO: projetos.php
   Para que serve: os PROJETOS científicos do clube. Todos podem consultar;
   professores e administradores podem criar, alterar e apagar, e associar
   equipamentos e ficheiros a cada projeto.
   Ações (?acao=): lista (por omissão), ver, novo, editar. Formulários (POST): guardar, apagar.
   Tabelas usadas: projeto, projeto_equipamento, projeto_ficheiro, utilizador.
   ========================================================================== */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/blocos.php';
require_once __DIR__ . '/includes/ficheiros.php';

$u = exigir_login();
$staff = eh_staff($u);
const ESTADOS_PROJETO = ['planeado', 'em_curso', 'concluido', 'cancelado'];

/* ------------------------- Tratar formulários (POST) ---------------------- */
$erros = [];
$dados = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_staff();
    csrf_verificar();
    $id = pedido_int('id');

    if (post('op') === 'apagar') {
        $fs = array_column(consulta('SELECT id_ficheiro FROM projeto_ficheiro WHERE id_projeto = ?', [$id]), 'id_ficheiro');
        $pdo = db(); $pdo->beginTransaction();
        executa('DELETE FROM projeto_equipamento WHERE id_projeto = ?', [$id]);
        executa('DELETE FROM projeto_ficheiro WHERE id_projeto = ?', [$id]);
        executa('DELETE FROM projeto WHERE id_projeto = ?', [$id]);
        $pdo->commit();
        foreach ($fs as $idF) ficheiro_limpar_se_orfao((int)$idF);
        flash('Projeto apagado.');
        redirecionar('projetos.php');
    }

    if (post('op') === 'guardar') {
        $dados = [
            'titulo' => post('titulo'), 'descricao' => post('descricao'),
            'data_inicio' => post('data_inicio'), 'data_fim' => post('data_fim'),
            'estado' => escolha(post('estado'), ESTADOS_PROJETO, 'planeado'),
            'id_responsavel' => pedido_int('id_responsavel'),
        ];
        if ($dados['titulo'] === '' || mb_strlen($dados['titulo']) > 150) $erros[] = 'O título é obrigatório (até 150 letras).';
        if (mb_strlen($dados['descricao']) > 5000)                        $erros[] = 'A descrição é demasiado longa.';
        $ini = valida_data($dados['data_inicio']);
        if (!$ini)                                                        $erros[] = 'Data de início inválida.';
        $fim = $dados['data_fim'] === '' ? null : valida_data($dados['data_fim']);
        if ($dados['data_fim'] !== '' && !$fim)                           $erros[] = 'Data de fim inválida.';
        if ($ini && $fim && $fim < $ini)                                  $erros[] = 'A data de fim não pode ser anterior à de início.';
        if (!linha("SELECT 1 FROM utilizador WHERE id_utilizador = ? AND ativo = 1 AND perfil IN ('administrador','professor')", [$dados['id_responsavel']]))
                                                                          $erros[] = 'Escolhe um responsável válido.';
        if (!$erros) {
            $params = [$dados['titulo'], $dados['descricao'] ?: null, $dados['data_inicio'], $fim ? $dados['data_fim'] : null,
                       $dados['estado'], $dados['id_responsavel']];
            if ($id) {
                executa('UPDATE projeto SET titulo=?, descricao=?, data_inicio=?, data_fim=?, estado=?, id_responsavel=? WHERE id_projeto=?', [...$params, $id]);
                flash('Projeto guardado.');
            } else {
                executa('INSERT INTO projeto (titulo, descricao, data_inicio, data_fim, estado, id_responsavel) VALUES (?,?,?,?,?,?)', $params);
                $id = (int)db()->lastInsertId();
                flash('Projeto criado.');
            }
            redirecionar('projetos.php?acao=ver&id=' . $id);
        }
        $_GET['acao'] = $id ? 'editar' : 'novo';          // voltar ao formulário com os erros
        $_GET['id'] = $id;
    }
}

$acao = $_GET['acao'] ?? 'lista';

/* ------------------------------ Formulário -------------------------------- */
if (in_array($acao, ['novo', 'editar'], true)) {
    exigir_staff();
    $id = $acao === 'editar' ? pedido_int('id') : 0;
    if (!$dados) {
        $dados = ['titulo' => '', 'descricao' => '', 'data_inicio' => date('Y-m-d'), 'data_fim' => '', 'estado' => 'planeado', 'id_responsavel' => $u['id_utilizador']];
        if ($id) {
            $p = linha('SELECT * FROM projeto WHERE id_projeto = ?', [$id]);
            if (!$p) erro_pagina(404, 'Projeto não encontrado.');
            $dados = array_intersect_key($p, $dados);
            $dados['data_fim'] = (string)$dados['data_fim'];
            $dados['descricao'] = (string)$dados['descricao'];
        }
    }
    $resp = consulta("SELECT id_utilizador, nome FROM utilizador WHERE ativo = 1 AND perfil IN ('administrador','professor') ORDER BY nome");
    cabecalho($id ? 'Editar projeto' : 'Novo projeto', 'projetos');
    /* CSS deste bloco:
       Encontras o CSS da caixa branca (.doc, .lead) no ficheiro `css/style.css`, na secção `cartoes`.
       Encontras o CSS do formulário (.stack, label, input, select, textarea, .btn) no ficheiro `css/style.css`, na secção `formularios`.
       Encontras o CSS dos botões à direita (.linha-fim) no ficheiro `css/style.css`, na secção `extras`. */
    ?>
    <section class="doc wide fade-in">
      <h1><?= $id ? 'Editar projeto' : 'Novo projeto' ?></h1>
      <?php foreach ($erros as $er): ?><p class="flash" role="alert"><?= e($er) ?></p><?php endforeach; ?>
      <form class="stack" method="post" action="<?= e(BASE_URL) ?>/projetos.php">
        <?= csrf_campo() ?><input type="hidden" name="op" value="guardar"><input type="hidden" name="id" value="<?= $id ?>">
        <label>Título<input name="titulo" required maxlength="150" value="<?= e($dados['titulo']) ?>"></label>
        <label>Descrição<textarea name="descricao" rows="5" maxlength="5000"><?= e($dados['descricao']) ?></textarea></label>
        <label>Data de início<input type="date" name="data_inicio" required value="<?= e($dados['data_inicio']) ?>"></label>
        <label>Data de fim (opcional)<input type="date" name="data_fim" value="<?= e($dados['data_fim']) ?>"></label>
        <label>Estado
          <select name="estado"><?php foreach (ESTADOS_PROJETO as $s): ?>
            <option value="<?= e($s) ?>"<?= $dados['estado'] === $s ? ' selected' : '' ?>><?= e(rotulo($s)) ?></option><?php endforeach; ?>
          </select></label>
        <label>Responsável
          <select name="id_responsavel"><?php foreach ($resp as $r): ?>
            <option value="<?= (int)$r['id_utilizador'] ?>"<?= (int)$dados['id_responsavel'] === (int)$r['id_utilizador'] ? ' selected' : '' ?>><?= e($r['nome']) ?></option><?php endforeach; ?>
          </select></label>
        <div class="linha-fim"><a class="btn sec" href="<?= e(BASE_URL) ?>/projetos.php">Cancelar</a><button class="btn">Guardar</button></div>
      </form>
    </section>
    <?php rodape(); exit;
}

/* ------------------------------ Detalhe ----------------------------------- */
if ($acao === 'ver') {
    $id = pedido_int('id');
    $p = linha('SELECT p.*, u.nome AS responsavel FROM projeto p LEFT JOIN utilizador u ON u.id_utilizador = p.id_responsavel WHERE p.id_projeto = ?', [$id]);
    if (!$p) erro_pagina(404, 'Projeto não encontrado.');
    cabecalho($p['titulo'], 'projetos');
    /* CSS deste bloco: caixa (.doc, .lead) na secção `cartoes`; etiqueta (.tag) na secção `tabelas`;
       botões (.btn, .link) na secção `formularios` — tudo em `css/style.css`. */
    ?>
    <section class="doc wide fade-in">
      <h1><?= e($p['titulo']) ?></h1>
      <p class="lead"><?= tag($p['estado']) ?> · Responsável: <?= e($p['responsavel']) ?> ·
        <?= e(data_pt($p['data_inicio'])) ?><?= $p['data_fim'] ? ' → ' . e(data_pt($p['data_fim'])) : '' ?></p>
      <p><?= nl2br(e($p['descricao'])) ?></p>
      <?php if ($staff): ?>
        <div class="row">
          <a class="btn" href="<?= e(BASE_URL) ?>/projetos.php?acao=editar&amp;id=<?= $id ?>">Editar</a>
          <form method="post" action="<?= e(BASE_URL) ?>/projetos.php" data-confirm="Apagar este projeto e as suas associações?">
            <?= csrf_campo() ?><input type="hidden" name="op" value="apagar"><input type="hidden" name="id" value="<?= $id ?>">
            <button class="btn sec">Apagar</button>
          </form>
        </div>
      <?php endif; ?>
      <?php bloco_equipamentos('projeto', $id, $staff); ?>
      <?php bloco_ficheiros('projeto', $id, $staff); ?>
      <p><a class="link" href="<?= e(BASE_URL) ?>/projetos.php">← Voltar à lista</a></p>
    </section>
    <?php rodape(); exit;
}

/* ------------------------------- Lista ------------------------------------ */
$filtro = $_GET['estado'] ?? '';
$q = pesquisa_get();
$where = [];
$params = [];
if (in_array($filtro, ESTADOS_PROJETO, true)) { $where[] = 'p.estado = ?'; $params[] = $filtro; }
if ($q !== '') { $where[] = pesquisa_sql(['p.titulo', 'p.descricao', 'u.nome'], $q, $params); }
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$total = (int)valor('SELECT COUNT(*) FROM projeto p LEFT JOIN utilizador u ON u.id_utilizador = p.id_responsavel' . $whereSql, $params);
$pag = paginacao_calcular($total);
$projetos = consulta('SELECT p.id_projeto, p.titulo, p.estado, p.data_inicio, p.data_fim, u.nome AS responsavel
        FROM projeto p LEFT JOIN utilizador u ON u.id_utilizador = p.id_responsavel'
        . $whereSql
        . ' ORDER BY p.data_inicio DESC, p.id_projeto DESC LIMIT ' . (int)$pag['por'] . ' OFFSET ' . (int)$pag['offset'], $params);

cabecalho('Projetos', 'projetos');
/* CSS deste bloco:
   Encontras o CSS da caixa (.doc, .lead) no ficheiro `css/style.css`, na secção `cartoes`.
   Encontras o CSS da tabela, etiquetas (.tag) e ações (.acts) no ficheiro `css/style.css`, na secção `tabelas`.
   Encontras o CSS do filtro e botões (.row, select, .btn) no ficheiro `css/style.css`, na secção `formularios`. */
?>
<section class="doc wide fade-in">
  <h1>Projetos</h1>
  <p class="lead">Projetos científicos do clube.</p>
  <form class="row" method="get" action="">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Pesquisar por título ou responsável" aria-label="Pesquisar projetos">
    <button class="btn sec">Pesquisar</button>
    <select name="estado" aria-label="Filtrar por estado" data-auto-submit>
      <option value="">Todos os estados</option>
      <?php foreach (ESTADOS_PROJETO as $s): ?><option value="<?= e($s) ?>"<?= $filtro === $s ? ' selected' : '' ?>><?= e(rotulo($s)) ?></option><?php endforeach; ?>
    </select>
    <?php if ($staff): ?><a class="btn" href="<?= e(BASE_URL) ?>/projetos.php?acao=novo">Novo projeto</a><?php endif; ?>
  </form>
  <?php if ($projetos): ?>
  <div class="scroll"><table>
    <thead><tr><th>Título</th><th>Estado</th><th>Período</th><th>Responsável</th></tr></thead>
    <tbody>
    <?php foreach ($projetos as $p): ?>
      <tr>
        <td><a class="link" href="<?= e(BASE_URL) ?>/projetos.php?acao=ver&amp;id=<?= (int)$p['id_projeto'] ?>"><?= e($p['titulo']) ?></a></td>
        <td><?= tag($p['estado']) ?></td>
        <td><?= e(data_pt($p['data_inicio'])) ?><?= $p['data_fim'] ? ' → ' . e(data_pt($p['data_fim'])) : '' ?></td>
        <td><?= e($p['responsavel']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= paginacao_links($pag) ?>
  <?php else: aviso_lista_vazia('projetos.php', $q, $filtro, 'projetos', 'Não há projetos neste estado.'); endif; ?>
</section>
<?php rodape(); ?>
