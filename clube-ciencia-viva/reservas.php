<?php
/* ==========================================================================
   FICHEIRO: reservas.php
   Para que serve: as RESERVAS de equipamento. Qualquer utilizador pede uma
   reserva (período + equipamentos + quantidades); o sistema confirma se há
   unidades livres nesse período. Professores e administradores aprovam ou
   recusam. Quem pediu (ou um professor) pode cancelar.
   Estados: pendente -> aprovada / recusada; aprovada -> concluida; cancelada.
   Ações (?acao=): lista (por omissão), nova.
   Formulários (POST, campo "op"): criar, aprovar, recusar, concluir, cancelar.
   Lógica de disponibilidade: includes/reservas.php.
   Tabelas usadas: reserva, reserva_equipamento, equipamento, categoria_equipamento, utilizador.
   ========================================================================== */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/reservas.php';

$u = exigir_login();
$staff = eh_staff($u);
$meuId = (int)$u['id_utilizador'];
const ESTADOS_RESERVA = ['pendente', 'aprovada', 'recusada', 'cancelada', 'concluida'];

/* ------------------------- Tratar formulários (POST) ---------------------- */
$erros = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $op = post('op');
    $id = pedido_int('id');

    if ($op === 'criar') {
        $ini = valida_data(post('inicio'), 'Y-m-d\TH:i');
        $fim = valida_data(post('fim'), 'Y-m-d\TH:i');
        if (!$ini || !$fim)                                       $erros[] = 'Escolhe a data e hora de início e de fim.';
        elseif ($fim <= $ini)                                     $erros[] = 'O fim tem de ser depois do início.';
        elseif ($ini < new DateTime('-5 minutes'))                $erros[] = 'O início não pode estar no passado.';
        elseif ($ini->diff($fim)->days > 30)                      $erros[] = 'Uma reserva pode durar no máximo 30 dias.';
        $qtds = [];
        foreach ((array)($_POST['qtd'] ?? []) as $idEq => $q) {
            if ((int)$idEq > 0 && (int)$q > 0) $qtds[(int)$idEq] = (int)$q;
        }
        if (!$qtds)                                               $erros[] = 'Escolhe pelo menos um equipamento (quantidade maior que 0).';
        if (!$erros) {
            $erro = criar_reserva($meuId, $ini->format('Y-m-d H:i:00'), $fim->format('Y-m-d H:i:00'), $qtds);
            if ($erro === null) { flash('Pedido de reserva enviado. Fica pendente até um professor aprovar.'); redirecionar('reservas.php'); }
            $erros[] = $erro;
        }
        $_GET['acao'] = 'nova';
    } else {
        $r = linha('SELECT estado, id_utilizador FROM reserva WHERE id_reserva = ?', [$id]);
        if (!$r) erro_pagina(404, 'Reserva não encontrada.');

        if ($op === 'cancelar') {
            if (!$staff && (int)$r['id_utilizador'] !== $meuId) erro_pagina(403, 'Só podes cancelar as tuas reservas.');
            if (!in_array($r['estado'], ['pendente', 'aprovada'], true)) flash('Esta reserva já não pode ser cancelada.', 'erro');
            else { executa("UPDATE reserva SET estado = 'cancelada' WHERE id_reserva = ?", [$id]); flash('Reserva cancelada.'); }
        } else {
            exigir_staff();
            if ($op === 'aprovar') {
                $erro = aprovar_reserva($id);
                $erro ? flash($erro, 'erro') : flash('Reserva aprovada.');
            } elseif ($op === 'recusar' && $r['estado'] === 'pendente') {
                executa("UPDATE reserva SET estado = 'recusada' WHERE id_reserva = ?", [$id]); flash('Reserva recusada.');
            } elseif ($op === 'concluir' && $r['estado'] === 'aprovada') {
                executa("UPDATE reserva SET estado = 'concluida' WHERE id_reserva = ?", [$id]); flash('Reserva concluída.');
            } else {
                flash('Ação não permitida para o estado atual da reserva.', 'erro');
            }
        }
        redirecionar('reservas.php');
    }
}

$acao = $_GET['acao'] ?? 'lista';

/* ------------------------------ Nova reserva ------------------------------ */
if ($acao === 'nova') {
    $equip = consulta("SELECT e.id_equipamento, e.nome, e.quantidade_total, c.nome AS categoria
                       FROM equipamento e LEFT JOIN categoria_equipamento c ON c.id_categoria = e.id_categoria
                       WHERE e.estado = 'disponivel' ORDER BY e.nome");
    cabecalho('Nova reserva', 'reservas');
    /* CSS deste bloco: caixa (.doc, .lead) na secção `cartoes`; formulário (.stack, label, input, .btn) na secção
       `formularios`; tabela (.scroll, table) na secção `tabelas`; texto pequeno (.hint) na secção `avisos`;
       botões à direita (.linha-fim) na secção `extras` — tudo em `css/style.css`.
       O JavaScript que mostra as unidades livres está em js/reservas.js. */
    ?>
    <section class="doc wide fade-in">
      <h1>Nova reserva</h1>
      <p class="lead">Escolhe o período e as quantidades. Um professor aprova o pedido.</p>
      <?php foreach ($erros as $er): ?><p class="flash" role="alert"><?= e($er) ?></p><?php endforeach; ?>
      <form class="stack" method="post" action="<?= e(BASE_URL) ?>/reservas.php" id="form-reserva">
        <?= csrf_campo() ?><input type="hidden" name="op" value="criar">
        <label>Início<input type="datetime-local" id="inicio" name="inicio" required value="<?= e($_POST['inicio'] ?? '') ?>"></label>
        <label>Fim<input type="datetime-local" id="fim" name="fim" required value="<?= e($_POST['fim'] ?? '') ?>"></label>
        <?php if ($equip): ?>
        <div class="scroll"><table>
          <thead><tr><th>Equipamento</th><th>Categoria</th><th>Total</th><th>Livres no período</th><th>Quantidade</th></tr></thead>
          <tbody>
          <?php foreach ($equip as $q): ?>
            <tr>
              <td><?= e($q['nome']) ?></td><td><?= e($q['categoria']) ?></td><td><?= (int)$q['quantidade_total'] ?></td>
              <td data-disp="<?= (int)$q['id_equipamento'] ?>">—</td>
              <td><input type="number" name="qtd[<?= (int)$q['id_equipamento'] ?>]" min="0" max="<?= (int)$q['quantidade_total'] ?>"
                         value="<?= (int)($_POST['qtd'][$q['id_equipamento']] ?? 0) ?>" aria-label="Quantidade de <?= e($q['nome']) ?>"></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <p class="hint">As unidades livres aparecem quando escolheres o início e o fim. Só contam as reservas já aprovadas.</p>
        <?php else: ?><p class="hint">Não há equipamentos disponíveis para reserva.</p><?php endif; ?>
        <div class="linha-fim"><a class="btn sec" href="<?= e(BASE_URL) ?>/reservas.php">Cancelar</a><button class="btn">Pedir reserva</button></div>
      </form>
    </section>
    <?php rodape(['reservas.js']); exit;
}

/* ------------------------------- Lista ------------------------------------ */
$filtro = $_GET['estado'] ?? '';
$q = pesquisa_get();
$where = []; $params = [];
if (!$staff)                                           { $where[] = 'r.id_utilizador = ?'; $params[] = $meuId; }
if (in_array($filtro, ESTADOS_RESERVA, true))          { $where[] = 'r.estado = ?';        $params[] = $filtro; }
if ($q !== '') {
    $where[] = "(u.nome LIKE ? OR EXISTS (SELECT 1 FROM reserva_equipamento re2
                     JOIN equipamento e2 ON e2.id_equipamento = re2.id_equipamento
                    WHERE re2.id_reserva = r.id_reserva AND e2.nome LIKE ?))";
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$total = (int)valor('SELECT COUNT(DISTINCT r.id_reserva) FROM reserva r
                     JOIN utilizador u ON u.id_utilizador = r.id_utilizador' . $whereSql, $params);
$pag = paginacao_calcular($total);
$reservas = consulta("SELECT r.id_reserva, r.data_inicio, r.data_fim, r.estado, r.id_utilizador, u.nome AS utilizador,
                             GROUP_CONCAT(CONCAT(e.nome, ' ×', re.quantidade) ORDER BY e.nome SEPARATOR ', ') AS itens
                      FROM reserva r
                      JOIN utilizador u ON u.id_utilizador = r.id_utilizador
                      LEFT JOIN reserva_equipamento re ON re.id_reserva = r.id_reserva
                      LEFT JOIN equipamento e ON e.id_equipamento = re.id_equipamento"
                      . $whereSql .
                     " GROUP BY r.id_reserva, r.data_inicio, r.data_fim, r.estado, r.id_utilizador, u.nome
                       ORDER BY r.data_inicio DESC LIMIT " . (int)$pag['por'] . " OFFSET " . (int)$pag['offset'], $params);

/* Botão pequeno que envia uma ação para uma reserva. */
function botao_reserva(int $id, string $op, string $texto, bool $perigo = false, string $confirma = ''): string
{
    return '<form method="post" action="' . e(BASE_URL) . '/reservas.php"' . ($confirma ? ' data-confirm="' . e($confirma) . '"' : '') . '>'
         . csrf_campo() . '<input type="hidden" name="op" value="' . e($op) . '"><input type="hidden" name="id" value="' . $id . '">'
         . '<button class="link' . ($perigo ? ' danger' : '') . '">' . e($texto) . '</button></form>';
}

cabecalho('Reservas', 'reservas');
/* CSS deste bloco: caixa (.doc, .lead) na secção `cartoes`; tabela, etiquetas (.tag) e ações (.acts) na secção
   `tabelas`; filtro e botões (.row, select, .btn, .link) na secção `formularios` — tudo em `css/style.css`. */
?>
<section class="doc wide fade-in">
  <h1>Reservas</h1>
  <p class="lead"><?= $staff ? 'Todas as reservas do clube.' : 'As tuas reservas.' ?></p>
  <form class="row" method="get" action="">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Pesquisar por utilizador ou equipamento" aria-label="Pesquisar reservas">
    <button class="btn sec">Pesquisar</button>
    <select name="estado" aria-label="Filtrar por estado" data-auto-submit>
      <option value="">Todos os estados</option>
      <?php foreach (ESTADOS_RESERVA as $s): ?><option value="<?= e($s) ?>"<?= $filtro === $s ? ' selected' : '' ?>><?= e(rotulo($s)) ?></option><?php endforeach; ?>
    </select>
    <a class="btn" href="<?= e(BASE_URL) ?>/reservas.php?acao=nova">Nova reserva</a>
  </form>
  <?php if ($reservas): ?>
  <div class="scroll"><table>
    <thead><tr><?= $staff ? '<th>Utilizador</th>' : '' ?><th>Período</th><th>Equipamentos</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($reservas as $r): $rid = (int)$r['id_reserva']; ?>
      <tr>
        <?= $staff ? '<td>' . e($r['utilizador']) . '</td>' : '' ?>
        <td><?= e(data_pt($r['data_inicio'], true)) ?> → <?= e(data_pt($r['data_fim'], true)) ?></td>
        <td><?= e($r['itens']) ?></td>
        <td><?= tag($r['estado']) ?></td>
        <td><div class="acts">
          <?php if ($staff && $r['estado'] === 'pendente'): ?>
            <?= botao_reserva($rid, 'aprovar', 'Aprovar') ?><?= botao_reserva($rid, 'recusar', 'Recusar', true, 'Recusar esta reserva?') ?>
          <?php endif; ?>
          <?php if ($staff && $r['estado'] === 'aprovada'): ?><?= botao_reserva($rid, 'concluir', 'Concluir') ?><?php endif; ?>
          <?php if (in_array($r['estado'], ['pendente', 'aprovada'], true) && ($staff || (int)$r['id_utilizador'] === $meuId)): ?>
            <?= botao_reserva($rid, 'cancelar', 'Cancelar', true, 'Cancelar esta reserva?') ?>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= paginacao_links($pag) ?>
  <?php else: aviso_lista_vazia('reservas.php', $q, $filtro, 'reservas', 'Não há reservas neste estado.'); endif; ?>
</section>
<?php rodape(); ?>
