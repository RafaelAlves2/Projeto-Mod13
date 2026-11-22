<?php
/* ==========================================================================
   FICHEIRO: includes/funcoes.php
   Para que serve: pequenas ferramentas usadas em todo o projeto: proteger
   texto (e), redirecionar, mensagens amarelas (flash), etiquetas de estado,
   datas em português, validações simples, e helpers de duração/avatar.
   ========================================================================== */

/* e(): protege texto antes de o mostrar no HTML (evita XSS). Usa SEMPRE. */
function e($texto): string
{
    return htmlspecialchars((string)$texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* redirecionar(): vai para uma página do projeto, ex.: redirecionar('projetos.php'). */
function redirecionar(string $pagina)
{
    header('Location: ' . BASE_URL . '/' . ltrim($pagina, '/'));
    exit;
}

/* erro_pagina(): mostra uma página de erro e termina.
   Fica aqui (e não em layout.php) para poder ser usada por ficheiros que não
   carregam a moldura visual, como logout.php ou APIs. Se a moldura estiver
   carregada, usa-a; caso contrário, devolve uma página mínima. */
function erro_pagina(int $codigo, string $mensagem): void
{
    http_response_code($codigo);
    if (function_exists('cabecalho') && function_exists('rodape')) {
        cabecalho('Erro ' . $codigo, '');
        echo '<section class="doc"><h1>Erro ' . (int)$codigo . '</h1><p>' . e($mensagem) . '</p>'
           . '<p><a class="btn" href="' . e(BASE_URL) . '/index.php">Voltar ao início</a></p></section>';
        rodape();
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="pt-PT"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Erro ' . (int)$codigo . ' · Clube de Ciência Viva</title></head><body>'
       . '<h1>Erro ' . (int)$codigo . '</h1><p>' . e($mensagem) . '</p>'
       . '<p><a href="' . e(BASE_URL) . '/index.php">Voltar ao início</a></p></body></html>';
    exit;
}

/* flash(): guarda uma mensagem para aparecer (uma vez) na página seguinte. */
function flash(string $mensagem, string $tipo = 'ok'): void
{
    $_SESSION['flash'][] = ['m' => $mensagem, 't' => $tipo];
}
/* CSS deste bloco: css/style.css, secção `avisos` (.flash). */
function mostrar_flash(): void
{
    foreach ($_SESSION['flash'] ?? [] as $f) {
        echo '<p class="flash" role="' . ($f['t'] === 'erro' ? 'alert' : 'status') . '">' . e($f['m']) . '</p>';
    }
    unset($_SESSION['flash']);
}

/* Texto bonito para os valores guardados na base de dados (com acentos). */
function rotulo(string $valor): string
{
    static $mapa = [
        'administrador' => 'Administrador', 'professor' => 'Professor', 'aluno' => 'Aluno',
        'planeado' => 'Planeado', 'em_curso' => 'Em curso', 'concluido' => 'Concluído', 'cancelado' => 'Cancelado',
        'agendada' => 'Agendada', 'realizada' => 'Realizada', 'cancelada' => 'Cancelada',
        'pendente' => 'Pendente', 'aprovada' => 'Aprovada', 'recusada' => 'Recusada', 'concluida' => 'Concluída',
        'disponivel' => 'Disponível', 'em_manutencao' => 'Em manutenção', 'avariado' => 'Avariado', 'abatido' => 'Abatido',
        'inscrito' => 'Inscrito', 'confirmado' => 'Confirmado', 'presente' => 'Presente', 'faltou' => 'Faltou',
        'participante' => 'Participante', 'organizador' => 'Organizador', 'orador' => 'Orador',
    ];
    return $mapa[$valor] ?? ucfirst(str_replace('_', ' ', $valor));
}

/* tag(): etiqueta colorida para um estado. CSS: secção `tabelas` (.tag) e `extras` (.warn, .info). */
function tag(string $estado): string
{
    $verdes   = ['em_curso', 'aprovada', 'disponivel', 'confirmado', 'presente', 'agendada'];
    $amarelas = ['planeado', 'pendente', 'inscrito', 'em_manutencao'];
    $azuis    = ['concluido', 'concluida', 'realizada'];
    $classe   = in_array($estado, $verdes, true) ? 'on'
              : (in_array($estado, $amarelas, true) ? 'warn'
              : (in_array($estado, $azuis, true) ? 'info' : 'off'));
    return '<span class="tag ' . $classe . '">' . e(rotulo($estado)) . '</span>';
}

/* Datas: a BD guarda 2026-10-20 14:30:00, nós mostramos 20/10/2026 14:30. */
function data_pt(?string $data, bool $comHora = false): string
{
    if (!$data) return '';
    $t = strtotime($data);
    return $t ? date($comHora ? 'd/m/Y H:i' : 'd/m/Y', $t) : '';
}

/* Valida "2026-10-20" (formato Y-m-d) ou "2026-10-20T14:30" (datetime-local). */
function valida_data(string $texto, string $formato = 'Y-m-d'): ?DateTime
{
    $d = DateTime::createFromFormat($formato, $texto);
    $erros = DateTime::getLastErrors();
    if (!$d || ($erros && ($erros['warning_count'] || $erros['error_count']))) return null;
    return $d;
}

/* Ler valores enviados por formulário (POST) ou pelo endereço (GET). */
function post(string $campo): string { return trim((string)($_POST[$campo] ?? '')); }
function pedido_int(string $campo): int { return (int)($_GET[$campo] ?? $_POST[$campo] ?? 0); }

/* email_valido(): formato e comprimento compatíveis com a coluna
   utilizador.email (VARCHAR(150)). */
function email_valido(string $email): bool
{
    return $email !== ''
        && mb_strlen($email) <= 150
        && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/* Se o valor não estiver na lista permitida, devolve o por omissão. */
function escolha(string $valor, array $permitidos, string $omissao): string
{
    return in_array($valor, $permitidos, true) ? $valor : $omissao;
}

/* --------------------------------------------------------------------------
   Helpers trazidos do projeto da clínica (Fase 1). Não alteram nada do que
   já existe; são só funções novas que podes usar quando precisares.
   -------------------------------------------------------------------------- */

/* formatar_duracao(90) -> "1 h 30 min"; formatar_duracao(60) -> "1 h";
   formatar_duracao(45) -> "45 min". Útil para reservas e atividades. */
function formatar_duracao(int $minutos): string
{
    if ($minutos < 0) { $minutos = 0; }
    $h = intdiv($minutos, 60);
    $m = $minutos % 60;
    if ($h > 0 && $m > 0) { return $h . ' h ' . $m . ' min'; }
    if ($h > 0)           { return $h . ' h'; }
    return $m . ' min';
}

/* iniciais("Rafael Alves") -> "RA"; iniciais("Ana") -> "A".
   Usado pelo avatar() quando não há fotografia. */
function iniciais(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome));
    if (!$partes || $partes[0] === '') { return '?'; }
    $a = mb_substr($partes[0], 0, 1);
    $b = count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '';
    return mb_strtoupper($a . $b);
}

/* avatar(['nome' => '...', 'fotografia' => 'caminho/opcional']) -> HTML.
   Se houver fotografia, devolve <img class="avatar">; senão, um círculo
   com as iniciais. A classe .avatar está definida no css/style.css. */
function avatar(array $pessoa): string
{
    $nome = (string)($pessoa['nome'] ?? $pessoa['nome_completo'] ?? '');
    $foto = (string)($pessoa['fotografia'] ?? '');
    if ($foto !== '') {
        $url = preg_match('~^(https?:)?//|^/~', $foto) ? $foto : BASE_URL . '/' . ltrim($foto, '/');
        return '<img class="avatar" src="' . e($url) . '" alt="Fotografia de ' . e($nome) . '">';
    }
    return '<div class="avatar" aria-hidden="true">' . e(iniciais($nome)) . '</div>';
}

/* --------------------------------------------------------------------------
   Pesquisa e paginação das listas (R05).
   -------------------------------------------------------------------------- */

/* Texto pesquisado (?q=), limitado a 100 caracteres. */
function pesquisa_get(): string
{
    $q = trim((string)($_GET['q'] ?? ''));
    return mb_substr($q, 0, 100);
}

/* Número da página (?pagina=), nunca inferior a 1. */
function pagina_get(): int
{
    return max(1, (int)($_GET['pagina'] ?? 1));
}

/* Calcula página, total de páginas e offset para um LIMIT/OFFSET. */
function paginacao_calcular(int $total, int $porPagina = 20): array
{
    $porPagina = max(1, min(100, $porPagina));
    $paginas = max(1, (int)ceil($total / $porPagina));
    $pagina = min(pagina_get(), $paginas);
    return [
        'total'   => $total,
        'por'     => $porPagina,
        'pagina'  => $pagina,
        'paginas' => $paginas,
        'offset'  => ($pagina - 1) * $porPagina,
    ];
}

/* Acrescenta à cláusula WHERE uma pesquisa por vários campos.
   Os valores entram sempre por parâmetro (LIKE ?), nunca por concatenação. */
function pesquisa_sql(array $campos, string $q, array &$params): string
{
    if ($q === '') return '';
    $partes = [];
    foreach ($campos as $campo) {
        $partes[] = $campo . ' LIKE ?';
        $params[] = '%' . $q . '%';
    }
    return '(' . implode(' OR ', $partes) . ')';
}

/* Links Anterior/Seguinte, preservando os filtros e a pesquisa atuais. */
function paginacao_links(array $p): string
{
    if ($p['paginas'] <= 1) return '';
    $params = $_GET;
    unset($params['pagina']);
    $url = static function (int $n) use ($params): string {
        $params['pagina'] = $n;
        return e(($_SERVER['SCRIPT_NAME'] ?? '') . '?' . http_build_query($params));
    };
    $out = '<nav class="paginacao" aria-label="Paginação">';
    if ($p['pagina'] > 1) {
        $out .= '<a class="btn sec" href="' . $url($p['pagina'] - 1) . '">← Anterior</a>';
    }
    $out .= '<span>Página ' . (int)$p['pagina'] . ' de ' . (int)$p['paginas']
          . ' · ' . (int)$p['total'] . ' registo(s)</span>';
    if ($p['pagina'] < $p['paginas']) {
        $out .= '<a class="btn sec" href="' . $url($p['pagina'] + 1) . '">Seguinte →</a>';
    }
    return $out . '</nav>';
}

/* Aviso quando uma lista não tem resultados. Se houver pesquisa ou filtro,
   mostra também um botão para voltar à lista completa. */
function aviso_lista_vazia(string $pagina, string $q, $filtro, string $plural, string $msgFiltro): void
{
    if ($q !== '') {
        echo '<p class="hint">Não há resultados para esta pesquisa.</p>';
    } elseif ((string)$filtro !== '') {
        echo '<p class="hint">' . e($msgFiltro) . '</p>';
    } else {
        echo '<p class="hint">Ainda não há ' . e($plural) . '.</p>';
    }
    if ($q !== '' || (string)$filtro !== '') {
        echo '<p><a class="btn sec" href="' . e(BASE_URL . '/' . $pagina) . '">Limpar pesquisa e filtros</a></p>';
    }
}