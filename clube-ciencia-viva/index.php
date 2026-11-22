<?php
/* ==========================================================================
   FICHEIRO: index.php
   Para que serve: a página Início (painel). Mostra números do clube,
   dois gráficos (Chart.js) e as próximas atividades. Tudo vem da base de dados.
   ========================================================================== */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/reservas.php';
$u = exigir_login();
$staff = eh_staff($u);

/* --- Números do topo ---------------------------------------------------- */
$nProjetos   = (int)valor("SELECT COUNT(*) FROM projeto WHERE estado = 'em_curso'");
$nAtividades = (int)valor("SELECT COUNT(*) FROM atividade WHERE estado = 'agendada' AND data_hora >= NOW()");

/* Unidades livres AGORA: quantidade_total dos equipamentos disponíveis menos
   as unidades de reservas APROVADAS que estão a decorrer neste momento. */
$agora = date('Y-m-d H:i:s');
$unidadesLivres = disponibilidade($agora, $agora);
$nEquip = array_sum($unidadesLivres);
if ($staff) {
    $nReservas = (int)valor("SELECT COUNT(*) FROM reserva WHERE estado = 'pendente'");
    $textoReservas = 'reservas à espera de aprovação';
} else {
    $nReservas = (int)valor("SELECT COUNT(*) FROM reserva WHERE id_utilizador = ? AND estado IN ('pendente','aprovada')", [$u['id_utilizador']]);
    $textoReservas = 'reservas minhas em aberto';
}

/* --- Dados dos gráficos ------------------------------------------------- */
if ($staff) {
    $rs = consulta('SELECT estado, COUNT(*) n FROM reserva GROUP BY estado');
} else {
    $rs = consulta('SELECT estado, COUNT(*) n FROM reserva WHERE id_utilizador = ? GROUP BY estado', [$u['id_utilizador']]);
}
$grRes = ['labels' => array_map(fn($r) => rotulo($r['estado']), $rs), 'valores' => array_map(fn($r) => (int)$r['n'], $rs)];

/* Gráfico por categoria: usa as mesmas unidades livres calculadas acima.
   Uma categoria sem equipamentos aparece com 0. */
$cs = consulta('SELECT c.id_categoria, c.nome, e.id_equipamento
                FROM categoria_equipamento c
                LEFT JOIN equipamento e ON e.id_categoria = c.id_categoria
                ORDER BY c.nome, e.id_equipamento');
$grCat = ['labels' => [], 'valores' => []];
$catAtual = null;
$nomeCat = '';
$somaCat = 0;
foreach ($cs as $r) {
    if ($catAtual !== (int)$r['id_categoria']) {
        if ($catAtual !== null) {
            $grCat['labels'][] = $nomeCat;
            $grCat['valores'][] = $somaCat;
        }
        $catAtual = (int)$r['id_categoria'];
        $nomeCat = $r['nome'];
        $somaCat = 0;
    }
    if ($r['id_equipamento'] !== null) {
        $somaCat += $unidadesLivres[(int)$r['id_equipamento']] ?? 0;
    }
}
if ($catAtual !== null) {
    $grCat['labels'][] = $nomeCat;
    $grCat['valores'][] = $somaCat;
}

$proximas = consulta("SELECT id_atividade, titulo, data_hora, local FROM atividade
                      WHERE estado = 'agendada' AND data_hora >= NOW() ORDER BY data_hora LIMIT 5");

cabecalho('Início', 'inicio');
?>
<section class="doc wide fade-in">
  <h1>Olá, <?= e(explode(' ', $u['nome'])[0]) ?></h1>
  <p class="lead">Clube de Ciência Viva · Agrupamento de Escolas Manuel Teixeira Gomes</p>

  <?php /* Atalhos: as ações mais comuns, para não ser preciso procurar no menu. */ ?>
  <div class="atalhos">
    <a class="btn sec" href="<?= e(BASE_URL) ?>/projetos.php">Ver projetos</a>
    <a class="btn sec" href="<?= e(BASE_URL) ?>/atividades.php">Ver atividades</a>
    <a class="btn" href="<?= e(BASE_URL) ?>/reservas.php?acao=nova">Pedir uma reserva</a>
    <?php if ($staff): ?><a class="btn sec" href="<?= e(BASE_URL) ?>/projetos.php?acao=novo">Criar projeto</a><?php endif; ?>
  </div>

  <?php /* CSS deste bloco: Encontras o CSS dos quadrados com números (.stats, .stat) no ficheiro `css/style.css`, na secção `estatisticas`. */ ?>
  <div class="stats">
    <div class="stat"><b data-count="<?= $nProjetos ?>"><?= $nProjetos ?></b><span>projetos em curso</span></div>
    <div class="stat"><b data-count="<?= $nAtividades ?>"><?= $nAtividades ?></b><span>atividades agendadas</span></div>
    <div class="stat"><b data-count="<?= $nEquip ?>"><?= $nEquip ?></b><span>unidades de equipamento disponíveis</span></div>
    <div class="stat"><b data-count="<?= $nReservas ?>"><?= $nReservas ?></b><span><?= e($textoReservas) ?></span></div>
  </div>

  <?php /* CSS deste bloco: Encontras o CSS dos gráficos (.graficos, .grafico) no ficheiro `css/style.css`, na secção `extras`. */ ?>
  <div class="graficos">
    <div class="grafico">
      <canvas id="gr-reservas" data-tipo="doughnut" data-titulo="<?= $staff ? 'Reservas por estado' : 'As minhas reservas por estado' ?>"
              data-labels="<?= e(json_encode($grRes['labels'], JSON_UNESCAPED_UNICODE)) ?>"
              data-valores="<?= e(json_encode($grRes['valores'])) ?>"></canvas>
    </div>
    <div class="grafico">
      <canvas id="gr-categorias" data-tipo="bar" data-titulo="Unidades disponíveis por categoria"
              data-labels="<?= e(json_encode($grCat['labels'], JSON_UNESCAPED_UNICODE)) ?>"
              data-valores="<?= e(json_encode($grCat['valores'])) ?>"></canvas>
    </div>
  </div>

  <?php /* CSS deste bloco: tabela (.scroll, table) na secção `tabelas`; texto pequeno (.hint) na secção `avisos` — ficheiro `css/style.css`. */ ?>
  <h2>Próximas atividades</h2>
  <?php if ($proximas): ?>
    <div class="scroll"><table>
      <thead><tr><th>Atividade</th><th>Quando</th><th>Local</th></tr></thead>
      <tbody>
      <?php foreach ($proximas as $a): ?>
        <tr>
          <td><a class="link" href="<?= e(BASE_URL) ?>/atividades.php?acao=ver&amp;id=<?= (int)$a['id_atividade'] ?>"><?= e($a['titulo']) ?></a></td>
          <td><?= e(data_pt($a['data_hora'], true)) ?></td>
          <td><?= e($a['local']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php else: ?>
    <p class="hint">Não há atividades agendadas.</p>
  <?php endif; ?>
</section>
<?php rodape(['vendor/chart.umd.js', 'graficos.js']); ?>
