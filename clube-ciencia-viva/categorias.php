<?php
/* ==========================================================================
   FICHEIRO: categorias.php
   Para que serve: as CATEGORIAS de equipamento (ex.: Microscopia). Só
   professores e administradores. Uma categoria só se apaga se não tiver
   equipamentos. Tudo na mesma página: lista + formulário para criar/editar.
   Tabelas usadas: categoria_equipamento, equipamento.
   ========================================================================== */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
exigir_staff();

$erros = [];
$idEditar = $_SERVER['REQUEST_METHOD'] === 'POST' ? pedido_int('id') : pedido_int('editar');
$editar = $idEditar ? linha('SELECT * FROM categoria_equipamento WHERE id_categoria = ?', [$idEditar]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    if ($idEditar && !$editar) erro_pagina(404, 'Categoria não encontrada.');
    $id = pedido_int('id');
    $op = post('op');

    if ($op === 'apagar') {
        if ((int)valor('SELECT COUNT(*) FROM equipamento WHERE id_categoria = ?', [$id]) > 0) {
            flash('Esta categoria tem equipamentos. Muda-os de categoria antes de a apagar.', 'erro');
        } else {
            executa('DELETE FROM categoria_equipamento WHERE id_categoria = ?', [$id]);
            flash('Categoria apagada.');
        }
        redirecionar('categorias.php');
    }
    if ($op === 'guardar') {
        $nome = post('nome'); $desc = post('descricao');
        if ($nome === '' || mb_strlen($nome) > 80)  $erros[] = 'O nome é obrigatório (até 80 letras).';
        if (mb_strlen($desc) > 255)                 $erros[] = 'A descrição pode ter até 255 letras.';
        if (!$erros && valor('SELECT COUNT(*) FROM categoria_equipamento WHERE nome = ? AND id_categoria <> ?', [$nome, $id]))
                                                    $erros[] = 'Já existe uma categoria com esse nome.';
        if (!$erros) {
            if ($id) executa('UPDATE categoria_equipamento SET nome = ?, descricao = ? WHERE id_categoria = ?', [$nome, $desc ?: null, $id]);
            else     executa('INSERT INTO categoria_equipamento (nome, descricao) VALUES (?, ?)', [$nome, $desc ?: null]);
            flash($id ? 'Categoria guardada.' : 'Categoria criada.');
            redirecionar('categorias.php');
        }
    }
}

if ($idEditar && !$editar) erro_pagina(404, 'Categoria não encontrada.');

$cats = consulta('SELECT c.id_categoria, c.nome, c.descricao, COUNT(e.id_equipamento) AS n
                  FROM categoria_equipamento c LEFT JOIN equipamento e ON e.id_categoria = c.id_categoria
                  GROUP BY c.id_categoria, c.nome, c.descricao ORDER BY c.nome');

cabecalho('Categorias', 'equipamentos');
/* CSS deste bloco: caixa (.doc, .lead) na secção `cartoes`; formulário (.row, input, .btn, .link) na secção
   `formularios`; tabela e ações (.acts) na secção `tabelas` — tudo em `css/style.css`. */
?>
<section class="doc wide fade-in">
  <h1>Categorias de equipamento</h1>
  <p class="lead"><a class="link" href="<?= e(BASE_URL) ?>/equipamentos.php">← Voltar aos equipamentos</a></p>
  <?php foreach ($erros as $er): ?><p class="flash" role="alert"><?= e($er) ?></p><?php endforeach; ?>
  <form class="row" method="post" action="<?= e(BASE_URL) ?>/categorias.php">
    <?= csrf_campo() ?><input type="hidden" name="op" value="guardar"><input type="hidden" name="id" value="<?= (int)($editar['id_categoria'] ?? 0) ?>">
    <input name="nome" placeholder="Nome" required maxlength="80" aria-label="Nome" value="<?= e($_POST['nome'] ?? $editar['nome'] ?? '') ?>">
    <input name="descricao" placeholder="Descrição (opcional)" maxlength="255" aria-label="Descrição" value="<?= e($_POST['descricao'] ?? $editar['descricao'] ?? '') ?>">
    <button class="btn"><?= $editar ? 'Guardar' : 'Adicionar' ?></button>
    <?php if ($editar): ?><a class="btn sec" href="<?= e(BASE_URL) ?>/categorias.php">Cancelar</a><?php endif; ?>
  </form>
  <?php if ($cats): ?>
  <div class="scroll"><table>
    <thead><tr><th>Nome</th><th>Descrição</th><th>Equipamentos</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($cats as $c): ?>
      <tr>
        <td><?= e($c['nome']) ?></td><td><?= e($c['descricao']) ?></td><td><?= (int)$c['n'] ?></td>
        <td><div class="acts">
          <a class="link" href="<?= e(BASE_URL) ?>/categorias.php?editar=<?= (int)$c['id_categoria'] ?>">Editar</a>
          <form method="post" action="<?= e(BASE_URL) ?>/categorias.php" data-confirm="Apagar esta categoria?"><?= csrf_campo() ?>
            <input type="hidden" name="op" value="apagar"><input type="hidden" name="id" value="<?= (int)$c['id_categoria'] ?>"><button class="link danger">Apagar</button></form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?><p class="hint">Ainda não há categorias.</p><?php endif; ?>
</section>
<?php rodape(); ?>
