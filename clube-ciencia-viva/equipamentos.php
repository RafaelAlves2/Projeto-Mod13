<?php
/* ==========================================================================
   FICHEIRO: equipamentos.php
   Para que serve: o inventário de EQUIPAMENTOS do clube (nome, quantidade
   total, estado, localização e categoria). Todos consultam; professores e
   administradores criam, alteram e apagam.
   Ações (?acao=): lista (por omissão), novo, editar. Formulários (POST): guardar, apagar.
   Tabelas usadas: equipamento, categoria_equipamento (+ as de associação, ao apagar).
   ========================================================================== */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

$u = exigir_login();
$staff = eh_staff($u);
const ESTADOS_EQUIP = ['disponivel', 'em_manutencao', 'avariado', 'abatido'];

/* ------------------------- Tratar formulários (POST) ---------------------- */
$erros = [];
$dados = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_staff();
    csrf_verificar();
    $id = pedido_int('id');

    if (post('op') === 'apagar') {
        $usos = (int)valor('SELECT (SELECT COUNT(*) FROM reserva_equipamento WHERE id_equipamento = ?)
                                 + (SELECT COUNT(*) FROM projeto_equipamento WHERE id_equipamento = ?)
                                 + (SELECT COUNT(*) FROM atividade_equipamento WHERE id_equipamento = ?)', [$id, $id, $id]);
        if ($usos > 0) {
            flash('Este equipamento está a ser usado em reservas, projetos ou atividades. Em vez de apagar, muda o estado para "Abatido".', 'erro');
        } else {
            executa('DELETE FROM equipamento WHERE id_equipamento = ?', [$id]);
            flash('Equipamento apagado.');
        }
        redirecionar('equipamentos.php');
    }

    if (post('op') === 'guardar') {
        $dados = [
            'nome' => post('nome'), 'descricao' => post('descricao'), 'quantidade_total' => pedido_int('quantidade_total'),
            'estado' => escolha(post('estado'), ESTADOS_EQUIP, 'disponivel'), 'localizacao' => post('localizacao'),
            'id_categoria' => pedido_int('id_categoria'),
        ];
        if ($dados['nome'] === '' || mb_strlen($dados['nome']) > 120)   $erros[] = 'O nome é obrigatório (até 120 letras).';
        if (mb_strlen($dados['descricao']) > 3000)                      $erros[] = 'A descrição é demasiado longa.';
        if ($dados['quantidade_total'] < 1 || $dados['quantidade_total'] > 100000) $erros[] = 'A quantidade total tem de ser um número de 1 a 100000.';
        if (mb_strlen($dados['localizacao']) > 120)                     $erros[] = 'A localização é demasiado longa.';
        if (!linha('SELECT 1 FROM categoria_equipamento WHERE id_categoria = ?', [$dados['id_categoria']])) $erros[] = 'Escolhe uma categoria.';

        /* Numa edição, o novo total não pode ficar abaixo do máximo já
           comprometido: associações a projetos/atividades ou reservas
           aprovadas que ainda não terminaram. */
        if ($id) {
            $comprometido = (int)valor(
                "SELECT GREATEST(
                     COALESCE((SELECT MAX(quantidade) FROM projeto_equipamento WHERE id_equipamento = ?), 0),
                     COALESCE((SELECT MAX(quantidade) FROM atividade_equipamento WHERE id_equipamento = ?), 0),
                     COALESCE((SELECT MAX(re.quantidade)
                                 FROM reserva_equipamento re
                                 JOIN reserva r ON r.id_reserva = re.id_reserva
                                WHERE re.id_equipamento = ? AND r.estado = 'aprovada'
                                  AND r.data_fim > NOW()), 0)
                 )",
                [$id, $id, $id]
            );
            if ($dados['quantidade_total'] < $comprometido) {
                $erros[] = 'A quantidade total não pode ser inferior a ' . $comprometido
                         . ', que é o máximo já comprometido em reservas aprovadas ou associações.';
            }
        }

        if (!$erros) {
            $params = [$dados['nome'], $dados['descricao'] ?: null, $dados['quantidade_total'], $dados['estado'], $dados['localizacao'] ?: null, $dados['id_categoria']];
            if ($id) {
                executa('UPDATE equipamento SET nome=?, descricao=?, quantidade_total=?, estado=?, localizacao=?, id_categoria=? WHERE id_equipamento=?', [...$params, $id]);
                flash('Equipamento guardado.');
            } else {
                executa('INSERT INTO equipamento (nome, descricao, quantidade_total, estado, localizacao, id_categoria) VALUES (?,?,?,?,?,?)', $params);
                flash('Equipamento criado.');
            }
            redirecionar('equipamentos.php');
        }
        $_GET['acao'] = $id ? 'editar' : 'novo';
        $_GET['id'] = $id;
    }
}

$acao = $_GET['acao'] ?? 'lista';
$categorias = consulta('SELECT id_categoria, nome FROM categoria_equipamento ORDER BY nome');

/* ------------------------------ Formulário -------------------------------- */
if (in_array($acao, ['novo', 'editar'], true)) {
    exigir_staff();
    $id = $acao === 'editar' ? pedido_int('id') : 0;
    if (!$dados) {
        $dados = ['nome' => '', 'descricao' => '', 'quantidade_total' => 1, 'estado' => 'disponivel', 'localizacao' => '', 'id_categoria' => $categorias[0]['id_categoria'] ?? 0];
        if ($id) {
            $q = linha('SELECT * FROM equipamento WHERE id_equipamento = ?', [$id]);
            if (!$q) erro_pagina(404, 'Equipamento não encontrado.');
            $dados = array_intersect_key($q, $dados);
            $dados['descricao'] = (string)$dados['descricao']; $dados['localizacao'] = (string)$dados['localizacao'];
        }
    }
    cabecalho($id ? 'Editar equipamento' : 'Novo equipamento', 'equipamentos');
    /* CSS deste bloco: caixa (.doc) na secção `cartoes`; formulário (.stack, label, input, select, textarea, .btn)
       na secção `formularios`; botões à direita (.linha-fim) na secção `extras` — tudo em `css/style.css`. */
    ?>
    <section class="doc wide fade-in">
      <h1><?= $id ? 'Editar equipamento' : 'Novo equipamento' ?></h1>
      <?php foreach ($erros as $er): ?><p class="flash" role="alert"><?= e($er) ?></p><?php endforeach; ?>
      <?php if (!$categorias): ?><p class="flash">Ainda não há categorias. <a href="<?= e(BASE_URL) ?>/categorias.php">Cria uma primeiro</a>.</p><?php endif; ?>
      <form class="stack" method="post" action="<?= e(BASE_URL) ?>/equipamentos.php">
        <?= csrf_campo() ?><input type="hidden" name="op" value="guardar"><input type="hidden" name="id" value="<?= $id ?>">
        <label>Nome<input name="nome" required maxlength="120" value="<?= e($dados['nome']) ?>"></label>
        <label>Descrição<textarea name="descricao" rows="3" maxlength="3000"><?= e($dados['descricao']) ?></textarea></label>
        <label>Quantidade total<input type="number" name="quantidade_total" min="1" max="100000" required value="<?= (int)$dados['quantidade_total'] ?>"></label>
        <label>Estado
          <select name="estado"><?php foreach (ESTADOS_EQUIP as $s): ?>
            <option value="<?= e($s) ?>"<?= $dados['estado'] === $s ? ' selected' : '' ?>><?= e(rotulo($s)) ?></option><?php endforeach; ?>
          </select></label>
        <label>Localização<input name="localizacao" maxlength="120" value="<?= e($dados['localizacao']) ?>"></label>
        <label>Categoria
          <select name="id_categoria" required><?php foreach ($categorias as $c): ?>
            <option value="<?= (int)$c['id_categoria'] ?>"<?= (int)$dados['id_categoria'] === (int)$c['id_categoria'] ? ' selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?>
          </select></label>
        <div class="linha-fim"><a class="btn sec" href="<?= e(BASE_URL) ?>/equipamentos.php">Cancelar</a><button class="btn">Guardar</button></div>
      </form>
    </section>
    <?php rodape(); exit;
}

/* ------------------------------- Lista ------------------------------------ */
$filtro = pedido_int('categoria');
$q = pesquisa_get();
$where = [];
$params = [];
if ($filtro) { $where[] = 'e.id_categoria = ?'; $params[] = $filtro; }
if ($q !== '') { $where[] = pesquisa_sql(['e.nome', 'e.descricao', 'e.localizacao', 'c.nome'], $q, $params); }
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$total = (int)valor('SELECT COUNT(*) FROM equipamento e LEFT JOIN categoria_equipamento c ON c.id_categoria = e.id_categoria' . $whereSql, $params);
$pag = paginacao_calcular($total);
$equipamentos = consulta('SELECT e.id_equipamento, e.nome, e.quantidade_total, e.estado, e.localizacao, c.nome AS categoria
        FROM equipamento e LEFT JOIN categoria_equipamento c ON c.id_categoria = e.id_categoria'
        . $whereSql
        . ' ORDER BY e.nome LIMIT ' . (int)$pag['por'] . ' OFFSET ' . (int)$pag['offset'], $params);

cabecalho('Equipamentos', 'equipamentos');
/* CSS deste bloco: caixa (.doc, .lead) na secção `cartoes`; tabela, etiquetas (.tag) e ações (.acts) na
   secção `tabelas`; filtro e botões (.row, select, .btn, .link) na secção `formularios` — tudo em `css/style.css`. */
?>
<section class="doc wide fade-in">
  <h1>Equipamentos</h1>
  <p class="lead">Inventário do clube.</p>
  <form class="row" method="get" action="">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Pesquisar por nome, localização ou categoria" aria-label="Pesquisar equipamentos">
    <button class="btn sec">Pesquisar</button>
    <select name="categoria" aria-label="Filtrar por categoria" data-auto-submit>
      <option value="">Todas as categorias</option>
      <?php foreach ($categorias as $c): ?><option value="<?= (int)$c['id_categoria'] ?>"<?= $filtro === (int)$c['id_categoria'] ? ' selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?>
    </select>
    <?php if ($staff): ?>
      <a class="btn" href="<?= e(BASE_URL) ?>/equipamentos.php?acao=novo">Novo equipamento</a>
      <a class="btn sec" href="<?= e(BASE_URL) ?>/categorias.php">Categorias</a>
    <?php endif; ?>
  </form>
  <?php if ($equipamentos): ?>
  <div class="scroll"><table>
    <thead><tr><th>Nome</th><th>Categoria</th><th>Total</th><th>Estado</th><th>Localização</th><?= $staff ? '<th></th>' : '' ?></tr></thead>
    <tbody>
    <?php foreach ($equipamentos as $eq): ?>
      <tr>
        <td><?= e($eq['nome']) ?></td><td><?= e($eq['categoria']) ?></td><td><?= (int)$eq['quantidade_total'] ?></td>
        <td><?= tag($eq['estado']) ?></td><td><?= e($eq['localizacao']) ?></td>
        <?php if ($staff): ?>
        <td><div class="acts">
          <a class="link" href="<?= e(BASE_URL) ?>/equipamentos.php?acao=editar&amp;id=<?= (int)$eq['id_equipamento'] ?>">Editar</a>
          <form method="post" action="<?= e(BASE_URL) ?>/equipamentos.php" data-confirm="Apagar este equipamento?"><?= csrf_campo() ?>
            <input type="hidden" name="op" value="apagar"><input type="hidden" name="id" value="<?= (int)$eq['id_equipamento'] ?>"><button class="link danger">Apagar</button></form>
        </div></td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= paginacao_links($pag) ?>
  <?php else: aviso_lista_vazia('equipamentos.php', $q, $filtro ?: '', 'equipamentos', 'Não há equipamentos nesta categoria.'); endif; ?>
</section>
<?php rodape(); ?>
