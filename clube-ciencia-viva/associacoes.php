<?php
/* ==========================================================================
   FICHEIRO: associacoes.php
   Para que serve: trata os formulários dos blocos "Equipamentos" e
   "Ficheiros" dos Projetos e das Atividades (só POST, só professores e
   administradores). Ações: assoc_equip, rem_equip, upload, apagar_ficheiro.
   Não mostra nada: faz o trabalho e volta à página do projeto/atividade.
   ========================================================================== */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/blocos.php';
require_once __DIR__ . '/includes/ficheiros.php';

$u = exigir_staff();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar('index.php');
csrf_verificar();

$tipo = post('tipo');
$c = assoc_cfg($tipo);
if (!$c) erro_pagina(400, 'Tipo inválido.');
$id = pedido_int('id');
if (!linha("SELECT 1 FROM {$c['tabela']} WHERE {$c['col']} = ?", [$id])) erro_pagina(404, 'Não encontrado.');
$volta = $c['pagina'] . '?acao=ver&id=' . $id;

switch (post('acao')) {

    case 'assoc_equip':                                   // ligar um equipamento (com quantidade)
        $idEq = pedido_int('id_equipamento');
        $qtd  = pedido_int('quantidade');
        $eq = linha('SELECT quantidade_total FROM equipamento WHERE id_equipamento = ?', [$idEq]);
        if (!$eq)                              flash('Equipamento inválido.', 'erro');
        elseif ($qtd < 1 || $qtd > $eq['quantidade_total'])
                                               flash('A quantidade tem de estar entre 1 e ' . (int)$eq['quantidade_total'] . '.', 'erro');
        else {
            executa("INSERT INTO {$c['t_eq']} ({$c['col']}, id_equipamento, quantidade) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE quantidade = VALUES(quantidade)", [$id, $idEq, $qtd]);
            flash('Equipamento associado.');
        }
        break;

    case 'rem_equip':
        executa("DELETE FROM {$c['t_eq']} WHERE {$c['col']} = ? AND id_equipamento = ?", [$id, pedido_int('id_equipamento')]);
        flash('Equipamento removido.');
        break;

    case 'upload':                                        // enviar e ligar um ficheiro
        /* O registo em `ficheiro` e a associação têm de entrar juntos. Se o
           segundo INSERT falhar, a transação desfaz o primeiro e o ficheiro
           físico é apagado, para não ficar órfão. */
        $pdo = db();
        $caminhoGuardado = null;
        try {
            $pdo->beginTransaction();
            [$idF, $erro] = guardar_upload($_FILES['ficheiro'] ?? [], (int)$u['id_utilizador'], $caminhoGuardado);
            if ($erro) {
                $pdo->rollBack();
                flash($erro, 'erro');
                break;
            }
            executa("INSERT INTO {$c['t_fi']} ({$c['col']}, id_ficheiro) VALUES (?, ?)", [$id, $idF]);
            $pdo->commit();
            flash('Ficheiro enviado.');
        } catch (Throwable $t) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($caminhoGuardado !== null && is_file($caminhoGuardado)) @unlink($caminhoGuardado);
            error_log('associacoes upload: ' . $t->getMessage());
            flash('Não foi possível guardar o ficheiro. Tenta outra vez.', 'erro');
        }
        break;

    case 'apagar_ficheiro':                               // desligar e, se ficar solto, apagar
        $idF = pedido_int('id_ficheiro');
        executa("DELETE FROM {$c['t_fi']} WHERE {$c['col']} = ? AND id_ficheiro = ?", [$id, $idF]);
        ficheiro_limpar_se_orfao($idF);
        flash('Ficheiro removido.');
        break;

    default:
        flash('Ação desconhecida.', 'erro');
}
redirecionar($volta);
