<?php
/* ==========================================================================
   FICHEIRO: includes/blocos.php
   Para que serve: dois pedaços de página usados tanto nos Projetos como nas
   Atividades: a lista de EQUIPAMENTOS associados e a lista de FICHEIROS
   associados (com os formulários para acrescentar e remover).
   As ações destes formulários são tratadas em associacoes.php.
   ========================================================================== */

/* Dados de cada tipo. Os nomes de tabelas/colunas vêm SEMPRE desta lista
   (nunca do utilizador), por isso é seguro usá-los nas consultas SQL. */
function assoc_cfg(string $tipo): ?array
{
    static $c = [
        'projeto'   => ['tabela' => 'projeto',   'col' => 'id_projeto',   'pagina' => 'projetos.php',
                        't_eq' => 'projeto_equipamento',   't_fi' => 'projeto_ficheiro'],
        'atividade' => ['tabela' => 'atividade', 'col' => 'id_atividade', 'pagina' => 'atividades.php',
                        't_eq' => 'atividade_equipamento', 't_fi' => 'atividade_ficheiro'],
    ];
    return $c[$tipo] ?? null;
}

/* bloco_equipamentos('projeto', 3, true)
   CSS deste bloco:
     Encontras o CSS da tabela (.scroll, table) no ficheiro `css/style.css`, na secção `tabelas`.
     Encontras o CSS do formulário numa linha (.row, select, input, .btn) no ficheiro `css/style.css`, na secção `formularios`.
     Encontras o CSS do texto pequeno (.hint) no ficheiro `css/style.css`, na secção `avisos`. */
function bloco_equipamentos(string $tipo, int $id, bool $podeEditar): void
{
    $c = assoc_cfg($tipo);
    $itens = consulta("SELECT e.id_equipamento, e.nome, e.quantidade_total, cat.nome AS categoria, a.quantidade
                       FROM {$c['t_eq']} a
                       JOIN equipamento e ON e.id_equipamento = a.id_equipamento
                       LEFT JOIN categoria_equipamento cat ON cat.id_categoria = e.id_categoria
                       WHERE a.{$c['col']} = ? ORDER BY e.nome", [$id]);
    echo '<h2>Equipamentos</h2>';
    if ($itens) {
        echo '<div class="scroll"><table><thead><tr><th>Equipamento</th><th>Categoria</th><th>Quantidade</th><th></th></tr></thead><tbody>';
        foreach ($itens as $i) {
            echo '<tr><td>' . e($i['nome']) . '</td><td>' . e($i['categoria']) . '</td><td>' . (int)$i['quantidade'] . '</td><td>';
            if ($podeEditar) {
                echo '<form method="post" action="' . e(BASE_URL) . '/associacoes.php" data-confirm="Remover este equipamento?">'
                   . csrf_campo()
                   . '<input type="hidden" name="acao" value="rem_equip"><input type="hidden" name="tipo" value="' . e($tipo) . '">'
                   . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="id_equipamento" value="' . (int)$i['id_equipamento'] . '">'
                   . '<button class="link danger">Remover</button></form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    } else {
        echo '<p class="hint">Sem equipamentos associados.</p>';
    }
    if ($podeEditar) {
        $todos = consulta("SELECT id_equipamento, nome, quantidade_total FROM equipamento ORDER BY nome");
        if ($todos) {
            echo '<form class="row" method="post" action="' . e(BASE_URL) . '/associacoes.php">' . csrf_campo()
               . '<input type="hidden" name="acao" value="assoc_equip"><input type="hidden" name="tipo" value="' . e($tipo) . '">'
               . '<input type="hidden" name="id" value="' . $id . '"><select name="id_equipamento" required aria-label="Equipamento">';
            foreach ($todos as $t) echo '<option value="' . (int)$t['id_equipamento'] . '">' . e($t['nome']) . ' (total ' . (int)$t['quantidade_total'] . ')</option>';
            echo '</select><input type="number" name="quantidade" min="1" value="1" required aria-label="Quantidade">'
               . '<button class="btn">Associar equipamento</button></form>';
        } else {
            echo '<p class="hint">Ainda não há equipamentos registados.</p>';
        }
    }
}

/* bloco_ficheiros('projeto', 3, true)
   CSS deste bloco: tabela (.scroll, table) na secção `tabelas`; formulário (.row, input, .btn, .link)
   na secção `formularios`; texto pequeno (.hint) na secção `avisos` — tudo em `css/style.css`. */
function bloco_ficheiros(string $tipo, int $id, bool $podeEditar): void
{
    $c = assoc_cfg($tipo);
    $fs = consulta("SELECT f.id_ficheiro, f.nome, f.tipo, f.tamanho, f.data_upload, u.nome AS autor
                    FROM {$c['t_fi']} a
                    JOIN ficheiro f ON f.id_ficheiro = a.id_ficheiro
                    LEFT JOIN utilizador u ON u.id_utilizador = f.id_utilizador
                    WHERE a.{$c['col']} = ? ORDER BY f.data_upload DESC", [$id]);
    echo '<h2>Ficheiros</h2>';
    if ($fs) {
        echo '<div class="scroll"><table><thead><tr><th>Nome</th><th>Tamanho</th><th>Enviado</th><th>Por</th><th></th></tr></thead><tbody>';
        foreach ($fs as $f) {
            echo '<tr><td><a class="link" href="' . e(BASE_URL) . '/download.php?id=' . (int)$f['id_ficheiro'] . '">' . e($f['nome']) . '</a></td>'
               . '<td>' . e(tamanho_legivel((int)$f['tamanho'])) . '</td><td>' . e(data_pt($f['data_upload'], true)) . '</td><td>' . e($f['autor']) . '</td><td>';
            if ($podeEditar) {
                echo '<form method="post" action="' . e(BASE_URL) . '/associacoes.php" data-confirm="Remover este ficheiro?">' . csrf_campo()
                   . '<input type="hidden" name="acao" value="apagar_ficheiro"><input type="hidden" name="tipo" value="' . e($tipo) . '">'
                   . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="id_ficheiro" value="' . (int)$f['id_ficheiro'] . '">'
                   . '<button class="link danger">Remover</button></form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    } else {
        echo '<p class="hint">Sem ficheiros.</p>';
    }
    if ($podeEditar) {
        echo '<form class="row" method="post" enctype="multipart/form-data" action="' . e(BASE_URL) . '/associacoes.php">' . csrf_campo()
           . '<input type="hidden" name="acao" value="upload"><input type="hidden" name="tipo" value="' . e($tipo) . '">'
           . '<input type="hidden" name="id" value="' . $id . '"><input type="file" name="ficheiro" required aria-label="Ficheiro">'
           . '<button class="btn">Enviar ficheiro</button></form>'
           . '<p class="hint">Máximo ' . e(tamanho_legivel(UPLOAD_MAX_BYTES)) . '. Aceites: ' . e(implode(', ', array_keys(UPLOAD_TIPOS))) . '.</p>';
    }
}
