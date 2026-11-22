<?php
/* ==========================================================================
   FICHEIRO: includes/reservas.php
   Para que serve: a lógica das reservas de equipamento: quantas unidades
   estão livres num período, criar um pedido e aprová-lo.
   REGRA: só as reservas APROVADAS ocupam unidades. Um pedido pendente ainda
   não bloqueia nada; a verificação final faz-se na aprovação.
   Dois períodos sobrepõem-se quando: início_A < fim_B  E  fim_A > início_B.
   ========================================================================== */

/* disponibilidade(início, fim, [ids]) devolve [id_equipamento => unidades livres]
   (só equipamentos com estado "disponível"). Datas no formato 'Y-m-d H:i:s'. */
function disponibilidade(string $ini, string $fim, ?array $ids = null): array
{
    $sql = "SELECT e.id_equipamento,
                   CAST(e.quantidade_total AS SIGNED) - CAST(COALESCE(SUM(
                       CASE WHEN r.estado = 'aprovada' AND r.data_inicio < ? AND r.data_fim > ?
                            THEN re.quantidade ELSE 0 END), 0) AS SIGNED) AS livre
            FROM equipamento e
            LEFT JOIN reserva_equipamento re ON re.id_equipamento = e.id_equipamento
            LEFT JOIN reserva r ON r.id_reserva = re.id_reserva
            WHERE e.estado = 'disponivel'";
    $params = [$fim, $ini];
    if ($ids !== null) {
        if (!$ids) return [];
        $sql .= ' AND e.id_equipamento IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        array_push($params, ...$ids);
    }
    $sql .= ' GROUP BY e.id_equipamento, e.quantidade_total';
    $out = [];
    foreach (consulta($sql, $params) as $r) $out[(int)$r['id_equipamento']] = max(0, (int)$r['livre']);
    return $out;
}

/* Bloqueia as linhas dos equipamentos até ao fim da transação, para dois
   pedidos ao mesmo tempo não reservarem a mesma unidade. */
function bloquear_equipamentos(array $ids): void
{
    if ($ids) consulta('SELECT id_equipamento FROM equipamento WHERE id_equipamento IN ('
                     . implode(',', array_fill(0, count($ids), '?')) . ') FOR UPDATE', $ids);
}

/* criar_reserva(): $quantidades = [id_equipamento => quantidade].
   Devolve null se correu bem, ou o texto do erro. */
function criar_reserva(int $idUtilizador, string $ini, string $fim, array $quantidades): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $ids = array_keys($quantidades);
        bloquear_equipamentos($ids);
        $livre = disponibilidade($ini, $fim, $ids);
        foreach ($quantidades as $id => $q) {
            $nome = (string)valor('SELECT nome FROM equipamento WHERE id_equipamento = ?', [$id]);
            if (!isset($livre[$id])) { $pdo->rollBack(); return '"' . $nome . '" não está disponível para reserva.'; }
            if ($q > $livre[$id])    { $pdo->rollBack(); return 'Só há ' . $livre[$id] . ' unidade(s) livre(s) de "' . $nome . '" nesse período.'; }
        }
        executa("INSERT INTO reserva (data_inicio, data_fim, estado, id_utilizador) VALUES (?, ?, 'pendente', ?)",
                [$ini, $fim, $idUtilizador]);
        $idReserva = (int)$pdo->lastInsertId();
        foreach ($quantidades as $id => $q) {
            executa('INSERT INTO reserva_equipamento (id_reserva, id_equipamento, quantidade) VALUES (?, ?, ?)', [$idReserva, $id, $q]);
        }
        $pdo->commit();
        return null;
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('criar_reserva: ' . $t->getMessage());
        return 'Não foi possível guardar a reserva. Tenta outra vez.';
    }
}

/* aprovar_reserva(): confirma de novo a disponibilidade e só depois aprova. */
function aprovar_reserva(int $idReserva): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $r = linha("SELECT data_inicio, data_fim, estado FROM reserva WHERE id_reserva = ? FOR UPDATE", [$idReserva]);
        if (!$r || $r['estado'] !== 'pendente') { $pdo->rollBack(); return 'Só se podem aprovar reservas pendentes.'; }
        $itens = consulta('SELECT id_equipamento, quantidade FROM reserva_equipamento WHERE id_reserva = ?', [$idReserva]);
        $ids = array_map(fn($i) => (int)$i['id_equipamento'], $itens);
        bloquear_equipamentos($ids);
        $livre = disponibilidade($r['data_inicio'], $r['data_fim'], $ids);
        foreach ($itens as $i) {
            $id = (int)$i['id_equipamento'];
            if (!isset($livre[$id]) || $i['quantidade'] > $livre[$id]) {
                $nome = (string)valor('SELECT nome FROM equipamento WHERE id_equipamento = ?', [$id]);
                $pdo->rollBack();
                return 'Já não há unidades livres de "' . $nome . '" nesse período. Recusa ou pede para alterar.';
            }
        }
        executa("UPDATE reserva SET estado = 'aprovada' WHERE id_reserva = ?", [$idReserva]);
        $pdo->commit();
        return null;
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('aprovar_reserva: ' . $t->getMessage());
        return 'Não foi possível aprovar a reserva.';
    }
}
