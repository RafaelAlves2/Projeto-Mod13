<?php
/* ==========================================================================
   FICHEIRO: atividades.php
   Para que serve: as ATIVIDADES do clube (workshops, sessões...). Todos
   consultam e os alunos inscrevem-se; professores e administradores criam,
   alteram, apagam, gerem os participantes (estado e papel) e associam
   equipamentos e ficheiros.
   Ações (?acao=): lista (por omissão), ver, novo, editar.
   Formulários (POST, campo "op"): guardar, apagar, inscrever, cancelar_inscricao,
   part_atualizar, part_remover, part_adicionar.
   Tabelas usadas: atividade, participacao_atividade, atividade_equipamento, atividade_ficheiro, utilizador.
   ========================================================================== */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/blocos.php';
require_once __DIR__ . '/includes/ficheiros.php';

$u = exigir_login();
$staff = eh_staff($u);
$meuId = (int)$u['id_utilizador'];
const ESTADOS_ATIVIDADE = ['agendada', 'realizada', 'cancelada'];
const ESTADOS_INSCRICAO = ['inscrito', 'confirmado', 'cancelado', 'presente', 'faltou'];
const PAPEIS = ['participante', 'organizador', 'orador'];

/* ------------------------- Tratar formulários (POST) ---------------------- */
$erros = [];
$dados = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $op = post('op');
    $id = pedido_int('id');
    $volta = 'atividades.php?acao=ver&id=' . $id;

    /* --- Qualquer utilizador: inscrever-se / cancelar a própria inscrição --- */
    if ($op === 'inscrever') {
        $a = linha('SELECT estado, data_hora FROM atividade WHERE id_atividade = ?', [$id]);
        if (!$a)                                          flash('Atividade não encontrada.', 'erro');
        elseif ($a['estado'] !== 'agendada' || strtotime($a['data_hora']) < time())
                                                          flash('Já não é possível inscrever-te nesta atividade.', 'erro');
        else {
            executa("INSERT INTO participacao_atividade (id_atividade, id_utilizador, estado_inscricao, papel)
                     VALUES (?, ?, 'inscrito', 'participante')
                     ON DUPLICATE KEY UPDATE estado_inscricao = IF(estado_inscricao = 'cancelado', 'inscrito', estado_inscricao)", [$id, $meuId]);
            flash('Inscrição feita.');
        }
        redirecionar($volta);
    }
    if ($op === 'cancelar_inscricao') {
        executa("UPDATE participacao_atividade SET estado_inscricao = 'cancelado'
                 WHERE id_atividade = ? AND id_utilizador = ? AND estado_inscricao IN ('inscrito','confirmado')", [$id, $meuId]);
        flash('Inscrição cancelada.');
        redirecionar($volta);
    }

    /* --- Daqui para baixo, só professores e administradores ---------------- */
    exigir_staff();

    if ($op === 'apagar') {
        $fs = array_column(consulta('SELECT id_ficheiro FROM atividade_ficheiro WHERE id_atividade = ?', [$id]), 'id_ficheiro');
        $pdo = db(); $pdo->beginTransaction();
        executa('DELETE FROM participacao_atividade WHERE id_atividade = ?', [$id]);
        executa('DELETE FROM atividade_equipamento WHERE id_atividade = ?', [$id]);
        executa('DELETE FROM atividade_ficheiro WHERE id_atividade = ?', [$id]);
        executa('DELETE FROM atividade WHERE id_atividade = ?', [$id]);
        $pdo->commit();
        foreach ($fs as $idF) ficheiro_limpar_se_orfao((int)$idF);
        flash('Atividade apagada.');
        redirecionar('atividades.php');
    }

    if ($op === 'part_atualizar') {
        executa('UPDATE participacao_atividade SET estado_inscricao = ?, papel = ? WHERE id_atividade = ? AND id_utilizador = ?',
                [escolha(post('estado_inscricao'), ESTADOS_INSCRICAO, 'inscrito'), escolha(post('papel'), PAPEIS, 'participante'), $id, pedido_int('id_utilizador')]);
        flash('Participante atualizado.');
        redirecionar($volta);
    }
    if ($op === 'part_remover') {
        executa('DELETE FROM participacao_atividade WHERE id_atividade = ? AND id_utilizador = ?', [$id, pedido_int('id_utilizador')]);
        flash('Participante removido.');
        redirecionar($volta);
    }
    if ($op === 'part_adicionar') {
        if (!linha('SELECT 1 FROM atividade WHERE id_atividade = ?', [$id])) {
            flash('Atividade não encontrada.', 'erro');
            redirecionar('atividades.php');
        }
        $idU = pedido_int('id_utilizador');
        if (!linha('SELECT 1 FROM utilizador WHERE id_utilizador = ? AND ativo = 1', [$idU])) {
            flash('Utilizador inválido.', 'erro');
        } else {
            try {
                executa("INSERT INTO participacao_atividade (id_atividade, id_utilizador, estado_inscricao, papel) VALUES (?, ?, 'confirmado', ?)
                         ON DUPLICATE KEY UPDATE papel = VALUES(papel)", [$id, $idU, escolha(post('papel'), PAPEIS, 'participante')]);
                flash('Participante adicionado.');
            } catch (Throwable $t) {
                error_log('part_adicionar: ' . $t->getMessage());
                flash('Não foi possível adicionar o participante.', 'erro');
            }
        }
        redirecionar($volta);
    }

    if ($op === 'guardar') {
        $dados = [
            'titulo' => post('titulo'), 'descricao' => post('descricao'), 'data_hora' => post('data_hora'),
            'local' => post('local'), 'estado' => escolha(post('estado'), ESTADOS_ATIVIDADE, 'agendada'),
            'id_responsavel' => pedido_int('id_responsavel'),
        ];
        if ($dados['titulo'] === '' || mb_strlen($dados['titulo']) > 150) $erros[] = 'O título é obrigatório (até 150 letras).';
        if (mb_strlen($dados['descricao']) > 5000)                        $erros[] = 'A descrição é demasiado longa.';
        if (mb_strlen($dados['local']) > 120)                             $erros[] = 'O local é demasiado longo (máx. 120).';
        $quando = valida_data($dados['data_hora'], 'Y-m-d\TH:i');
        if (!$quando)                                                     $erros[] = 'Data e hora inválidas.';
        if (!linha("SELECT 1 FROM utilizador WHERE id_utilizador = ? AND ativo = 1 AND perfil IN ('administrador','professor')", [$dados['id_responsavel']]))
                                                                          $erros[] = 'Escolhe um responsável válido.';
        if (!$erros) {
            $params = [$dados['titulo'], $dados['descricao'] ?: null, $quando->format('Y-m-d H:i:00'), $dados['local'] ?: null, $dados['estado'], $dados['id_responsavel']];
            if ($id) {
                executa('UPDATE atividade SET titulo=?, descricao=?, data_hora=?, local=?, estado=?, id_responsavel=? WHERE id_atividade=?', [...$params, $id]);
                flash('Atividade guardada.');
            } else {
                executa('INSERT INTO atividade (titulo, descricao, data_hora, local, estado, id_responsavel) VALUES (?,?,?,?,?,?)', $params);
                $id = (int)db()->lastInsertId();
                flash('Atividade criada.');
            }
            redirecionar('atividades.php?acao=ver&id=' . $id);
        }
        $_GET['acao'] = $id ? 'editar' : 'novo';
        $_GET['id'] = $id;
    }
}

$acao = $_GET['acao'] ?? 'lista';

/* ------------------------------ Formulário -------------------------------- */
if (in_array($acao, ['novo', 'editar'], true)) {
    exigir_staff();
    $id = $acao === 'editar' ? pedido_int('id') : 0;
    if (!$dados) {
        $dados = ['titulo' => '', 'descricao' => '', 'data_hora' => date('Y-m-d\TH:00', strtotime('+1 day')), 'local' => '', 'estado' => 'agendada', 'id_responsavel' => $meuId];
        if ($id) {
            $a = linha('SELECT * FROM atividade WHERE id_atividade = ?', [$id]);
            if (!$a) erro_pagina(404, 'Atividade não encontrada.');
            $dados = array_intersect_key($a, $dados);
            $dados['data_hora'] = date('Y-m-d\TH:i', strtotime($a['data_hora']));
            $dados['descricao'] = (string)$dados['descricao']; $dados['local'] = (string)$dados['local'];
        }
    }
    $resp = consulta("SELECT id_utilizador, nome FROM utilizador WHERE ativo = 1 AND perfil IN ('administrador','professor') ORDER BY nome");
    cabecalho($id ? 'Editar atividade' : 'Nova atividade', 'atividades');
    /* CSS deste bloco: caixa (.doc) na secção `cartoes`; formulário (.stack, label, input, select, textarea, .btn)
       na secção `formularios`; botões à direita (.linha-fim) na secção `extras` — tudo em `css/style.css`. */
    ?>
    <section class="doc wide fade-in">
      <h1><?= $id ? 'Editar atividade' : 'Nova atividade' ?></h1>
      <?php foreach ($erros as $er): ?><p class="flash" role="alert"><?= e($er) ?></p><?php endforeach; ?>
      <form class="stack" method="post" action="<?= e(BASE_URL) ?>/atividades.php">
        <?= csrf_campo() ?><input type="hidden" name="op" value="guardar"><input type="hidden" name="id" value="<?= $id ?>">
        <label>Título<input name="titulo" required maxlength="150" value="<?= e($dados['titulo']) ?>"></label>
        <label>Descrição<textarea name="descricao" rows="4" maxlength="5000"><?= e($dados['descricao']) ?></textarea></label>
        <label>Data e hora<input type="datetime-local" name="data_hora" required value="<?= e($dados['data_hora']) ?>"></label>
        <label>Local<input name="local" maxlength="120" value="<?= e($dados['local']) ?>"></label>
        <label>Estado
          <select name="estado"><?php foreach (ESTADOS_ATIVIDADE as $s): ?>
            <option value="<?= e($s) ?>"<?= $dados['estado'] === $s ? ' selected' : '' ?>><?= e(rotulo($s)) ?></option><?php endforeach; ?>
          </select></label>
        <label>Responsável
          <select name="id_responsavel"><?php foreach ($resp as $r): ?>
            <option value="<?= (int)$r['id_utilizador'] ?>"<?= (int)$dados['id_responsavel'] === (int)$r['id_utilizador'] ? ' selected' : '' ?>><?= e($r['nome']) ?></option><?php endforeach; ?>
          </select></label>
        <div class="linha-fim"><a class="btn sec" href="<?= e(BASE_URL) ?>/atividades.php">Cancelar</a><button class="btn">Guardar</button></div>
      </form>
    </section>
    <?php rodape(); exit;
}

/* ------------------------------ Detalhe ----------------------------------- */
if ($acao === 'ver') {
    $id = pedido_int('id');
    $a = linha('SELECT a.*, u.nome AS responsavel FROM atividade a LEFT JOIN utilizador u ON u.id_utilizador = a.id_responsavel WHERE a.id_atividade = ?', [$id]);
    if (!$a) erro_pagina(404, 'Atividade não encontrada.');
    $minha = linha('SELECT estado_inscricao FROM participacao_atividade WHERE id_atividade = ? AND id_utilizador = ?', [$id, $meuId]);
    $aberta = $a['estado'] === 'agendada' && strtotime($a['data_hora']) >= time();
    $parts = consulta('SELECT p.id_utilizador, p.estado_inscricao, p.papel, u.nome, u.email
                       FROM participacao_atividade p JOIN utilizador u ON u.id_utilizador = p.id_utilizador
                       WHERE p.id_atividade = ? ORDER BY u.nome', [$id]);
    cabecalho($a['titulo'], 'atividades');
    /* CSS deste bloco: caixa (.doc, .lead) na secção `cartoes`; tabela, etiquetas (.tag) e ações (.acts) na
       secção `tabelas`; botões e formulários (.btn, .row, select) na secção `formularios` — tudo em `css/style.css`. */
    ?>
    <section class="doc wide fade-in">
      <h1><?= e($a['titulo']) ?></h1>
      <p class="lead"><?= tag($a['estado']) ?> · <?= e(data_pt($a['data_hora'], true)) ?><?= $a['local'] ? ' · ' . e($a['local']) : '' ?> · Responsável: <?= e($a['responsavel']) ?></p>
      <p><?= nl2br(e($a['descricao'])) ?></p>

      <div class="row">
        <?php if ($aberta && (!$minha || $minha['estado_inscricao'] === 'cancelado')): ?>
          <form method="post" action="<?= e(BASE_URL) ?>/atividades.php"><?= csrf_campo() ?>
            <input type="hidden" name="op" value="inscrever"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn">Inscrever-me</button></form>
        <?php elseif ($minha && $minha['estado_inscricao'] !== 'cancelado'): ?>
          <span><?= tag($minha['estado_inscricao']) ?> A tua inscrição.</span>
          <?php if (in_array($minha['estado_inscricao'], ['inscrito', 'confirmado'], true)): ?>
          <form method="post" action="<?= e(BASE_URL) ?>/atividades.php" data-confirm="Cancelar a tua inscrição?"><?= csrf_campo() ?>
            <input type="hidden" name="op" value="cancelar_inscricao"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn sec">Cancelar inscrição</button></form>
          <?php endif; ?>
        <?php elseif (!$aberta): ?><span class="hint">Inscrições encerradas.</span><?php endif; ?>
        <?php if ($staff): ?>
          <a class="btn" href="<?= e(BASE_URL) ?>/atividades.php?acao=editar&amp;id=<?= $id ?>">Editar</a>
          <form method="post" action="<?= e(BASE_URL) ?>/atividades.php" data-confirm="Apagar esta atividade e as suas associações?"><?= csrf_campo() ?>
            <input type="hidden" name="op" value="apagar"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn sec">Apagar</button></form>
        <?php endif; ?>
      </div>

      <?php if ($staff): /* ---- Participantes (gestão) ---- */ ?>
      <h2>Participantes</h2>
      <?php if ($parts): ?>
      <div class="scroll"><table>
        <thead><tr><th>Nome</th><th>E-mail</th><th>Estado</th><th>Papel</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($parts as $p): ?>
          <tr>
            <td><?= e($p['nome']) ?></td><td><?= e($p['email']) ?></td>
            <td colspan="3">
              <form class="acts" method="post" action="<?= e(BASE_URL) ?>/atividades.php"><?= csrf_campo() ?>
                <input type="hidden" name="op" value="part_atualizar"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="id_utilizador" value="<?= (int)$p['id_utilizador'] ?>">
                <select name="estado_inscricao" aria-label="Estado"><?php foreach (ESTADOS_INSCRICAO as $s): ?><option value="<?= e($s) ?>"<?= $p['estado_inscricao'] === $s ? ' selected' : '' ?>><?= e(rotulo($s)) ?></option><?php endforeach; ?></select>
                <select name="papel" aria-label="Papel"><?php foreach (PAPEIS as $s): ?><option value="<?= e($s) ?>"<?= $p['papel'] === $s ? ' selected' : '' ?>><?= e(rotulo($s)) ?></option><?php endforeach; ?></select>
                <button class="link">Guardar</button>
              </form>
              <form method="post" action="<?= e(BASE_URL) ?>/atividades.php" data-confirm="Remover este participante?"><?= csrf_campo() ?>
                <input type="hidden" name="op" value="part_remover"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="id_utilizador" value="<?= (int)$p['id_utilizador'] ?>">
                <button class="link danger">Remover</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php else: ?><p class="hint">Ainda ninguém se inscreveu.</p><?php endif; ?>
      <?php $todos = consulta('SELECT id_utilizador, nome FROM utilizador WHERE ativo = 1 ORDER BY nome'); ?>
      <form class="row" method="post" action="<?= e(BASE_URL) ?>/atividades.php"><?= csrf_campo() ?>
        <input type="hidden" name="op" value="part_adicionar"><input type="hidden" name="id" value="<?= $id ?>">
        <select name="id_utilizador" aria-label="Utilizador"><?php foreach ($todos as $t): ?><option value="<?= (int)$t['id_utilizador'] ?>"><?= e($t['nome']) ?></option><?php endforeach; ?></select>
        <select name="papel" aria-label="Papel"><?php foreach (PAPEIS as $s): ?><option value="<?= e($s) ?>"><?= e(rotulo($s)) ?></option><?php endforeach; ?></select>
        <button class="btn">Adicionar participante</button>
      </form>
      <?php else: /* alunos só veem quantos participam */ ?>
        <p class="hint"><?= count(array_filter($parts, fn($p) => $p['estado_inscricao'] !== 'cancelado')) ?> participante(s) inscrito(s).</p>
      <?php endif; ?>

      <?php bloco_equipamentos('atividade', $id, $staff); ?>
      <?php bloco_ficheiros('atividade', $id, $staff); ?>
      <p><a class="link" href="<?= e(BASE_URL) ?>/atividades.php">← Voltar à lista</a></p>
    </section>
    <?php rodape(); exit;
}

/* ------------------------------- Lista ------------------------------------ */
$filtro = $_GET['estado'] ?? '';
$q = pesquisa_get();
$where = [];
$params = [$meuId];
if (in_array($filtro, ESTADOS_ATIVIDADE, true)) { $where[] = 'a.estado = ?'; $params[] = $filtro; }
if ($q !== '') { $where[] = pesquisa_sql(['a.titulo', 'a.descricao', 'a.local', 'u.nome'], $q, $params); }
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$total = (int)valor('SELECT COUNT(*) FROM atividade a
        LEFT JOIN participacao_atividade p ON p.id_atividade = a.id_atividade AND p.id_utilizador = ?
        LEFT JOIN utilizador u ON u.id_utilizador = a.id_responsavel' . $whereSql, $params);
$pag = paginacao_calcular($total);
$atividades = consulta('SELECT a.id_atividade, a.titulo, a.data_hora, a.local, a.estado, p.estado_inscricao
        FROM atividade a
        LEFT JOIN participacao_atividade p ON p.id_atividade = a.id_atividade AND p.id_utilizador = ?
        LEFT JOIN utilizador u ON u.id_utilizador = a.id_responsavel'
        . $whereSql
        . ' ORDER BY a.data_hora DESC LIMIT ' . (int)$pag['por'] . ' OFFSET ' . (int)$pag['offset'], $params);

cabecalho('Atividades', 'atividades');
/* CSS deste bloco: caixa (.doc, .lead) na secção `cartoes`; tabela e etiquetas (.tag) na secção `tabelas`;
   filtro e botão (.row, select, .btn) na secção `formularios` — tudo em `css/style.css`. */
?>
<section class="doc wide fade-in">
  <h1>Atividades</h1>
  <p class="lead">Workshops, sessões e outras atividades do clube.</p>
  <form class="row" method="get" action="">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Pesquisar por título, local ou responsável" aria-label="Pesquisar atividades">
    <button class="btn sec">Pesquisar</button>
    <select name="estado" aria-label="Filtrar por estado" data-auto-submit>
      <option value="">Todos os estados</option>
      <?php foreach (ESTADOS_ATIVIDADE as $s): ?><option value="<?= e($s) ?>"<?= $filtro === $s ? ' selected' : '' ?>><?= e(rotulo($s)) ?></option><?php endforeach; ?>
    </select>
    <?php if ($staff): ?><a class="btn" href="<?= e(BASE_URL) ?>/atividades.php?acao=novo">Nova atividade</a><?php endif; ?>
  </form>
  <?php if ($atividades): ?>
  <div class="scroll"><table>
    <thead><tr><th>Atividade</th><th>Quando</th><th>Local</th><th>Estado</th><th>A minha inscrição</th></tr></thead>
    <tbody>
    <?php foreach ($atividades as $a): ?>
      <tr>
        <td><a class="link" href="<?= e(BASE_URL) ?>/atividades.php?acao=ver&amp;id=<?= (int)$a['id_atividade'] ?>"><?= e($a['titulo']) ?></a></td>
        <td><?= e(data_pt($a['data_hora'], true)) ?></td><td><?= e($a['local']) ?></td>
        <td><?= tag($a['estado']) ?></td>
        <td><?= $a['estado_inscricao'] ? tag($a['estado_inscricao']) : '<span class="hint">Não inscrito</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= paginacao_links($pag) ?>
  <?php else: aviso_lista_vazia('atividades.php', $q, $filtro, 'atividades', 'Não há atividades neste estado.'); endif; ?>
</section>
<?php rodape(); ?>
