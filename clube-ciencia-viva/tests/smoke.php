<?php
/* ==========================================================================
   FICHEIRO: tests/smoke.php
   Para que serve: teste de fumo não destrutivo do Clube de Ciência Viva.
   Verifica a instalação, a base de dados, as funções auxiliares, a proteção
   dos ficheiros e, opcionalmente, os cabeçalhos HTTP da página de login.

   Como usar (a partir da raiz do projeto):
     php tests/smoke.php
     php tests/smoke.php --url=http://localhost/meu-projeto/clube-ciencia-viva

   O teste não cria, altera nem apaga registos da base de dados. O único
   ficheiro temporário é criado e removido na pasta uploads para confirmar
   que ela é gravável.
   ========================================================================== */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

$raiz = dirname(__DIR__);
require_once $raiz . '/includes/config.php';
require_once $raiz . '/includes/db.php';
require_once $raiz . '/includes/funcoes.php';

$GLOBALS['smoke_total'] = 0;
$GLOBALS['smoke_falhas'] = 0;
$GLOBALS['smoke_avisos'] = 0;

function smoke_ok(string $nome): void
{
    $GLOBALS['smoke_total']++;
    echo '[OK]    ' . $nome . "\n";
}

function smoke_falha(string $nome, string $detalhe): void
{
    $GLOBALS['smoke_total']++;
    $GLOBALS['smoke_falhas']++;
    echo '[FALHA] ' . $nome . ' -> ' . $detalhe . "\n";
}

function smoke_aviso(string $nome, string $detalhe): void
{
    $GLOBALS['smoke_avisos']++;
    echo '[AVISO] ' . $nome . ' -> ' . $detalhe . "\n";
}

function smoke_teste(string $nome, callable $teste): void
{
    try {
        $resultado = $teste();
        if ($resultado === true || $resultado === null) {
            smoke_ok($nome);
        } else {
            smoke_falha($nome, is_string($resultado) ? $resultado : 'resultado inesperado');
        }
    } catch (Throwable $t) {
        smoke_falha($nome, $t->getMessage());
    }
}

/* Converte 2M, 512K ou 1G para bytes. */
function smoke_bytes(string $valor): int
{
    $valor = trim($valor);
    if ($valor === '') return 0;
    $unidade = strtolower(substr($valor, -1));
    $numero = (int)$valor;
    return match ($unidade) {
        'g' => $numero * 1024 * 1024 * 1024,
        'm' => $numero * 1024 * 1024,
        'k' => $numero * 1024,
        default => (int)$valor,
    };
}

/* Pedido HTTP simples, sem seguir redirecionamentos. */
function smoke_http(string $url): array
{
    $contexto = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'ignore_errors' => true,
            'follow_location' => 0,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);
    $corpo = @file_get_contents($url, false, $contexto);
    return [$corpo === false ? '' : $corpo, $http_response_header ?? []];
}

echo "Teste de fumo - Clube de Ciência Viva\n";
echo 'Data: ' . date('d/m/Y H:i:s') . "\n";
echo 'Projeto: ' . $raiz . "\n";
echo 'Base de dados: ' . DB_NAME . "\n\n";

/* ------------------------- Ambiente e configuração ------------------------ */

smoke_teste('Versão do PHP >= 8.1', function (): bool|string {
    return version_compare(PHP_VERSION, '8.1.0', '>=')
        ? true
        : 'PHP ' . PHP_VERSION;
});

foreach (['pdo', 'pdo_mysql', 'mbstring', 'session', 'json', 'fileinfo'] as $extensao) {
    smoke_teste('Extensão PHP: ' . $extensao, function () use ($extensao): bool|string {
        return extension_loaded($extensao) ? true : 'não carregada';
    });
}

smoke_teste('Constantes de configuração', function (): bool|string {
    foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'UPLOAD_DIR', 'UPLOAD_MAX_BYTES'] as $constante) {
        if (!defined($constante)) return 'falta ' . $constante;
    }
    return true;
});

smoke_teste('expose_php desligado', function (): bool|string {
    $valor = strtolower(trim((string)ini_get('expose_php')));
    return ($valor === '' || $valor === '0' || $valor === 'off')
        ? true
        : 'expose_php=' . $valor;
});

/* ------------------------------ Base de dados ----------------------------- */

smoke_teste('Ligação à base de dados', function (): bool|string {
    $pdo = db();
    $nome = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    return $nome === DB_NAME ? true : 'ligado a "' . $nome . '" em vez de "' . DB_NAME . '"';
});

smoke_teste('17 tabelas do esquema', function (): bool|string {
    $esperadas = [
        'atividade', 'atividade_equipamento', 'atividade_ficheiro',
        'categoria_cientifica', 'categoria_equipamento', 'equipamento',
        'experiencia', 'ficheiro', 'participacao_atividade', 'pergunta_ia',
        'projeto', 'projeto_equipamento', 'projeto_ficheiro', 'reserva',
        'reserva_equipamento', 'tentativas_login', 'utilizador',
    ];
    sort($esperadas);

    $linhas = consulta(
        'SELECT table_name AS nome FROM information_schema.tables WHERE table_schema = ? ORDER BY table_name',
        [DB_NAME]
    );
    $encontradas = array_map(static fn(array $linha): string => (string)$linha['nome'], $linhas);
    sort($encontradas);

    if ($encontradas === $esperadas) return true;

    $faltam = array_diff($esperadas, $encontradas);
    $extras = array_diff($encontradas, $esperadas);
    $detalhe = [];
    if ($faltam) $detalhe[] = 'faltam: ' . implode(', ', $faltam);
    if ($extras) $detalhe[] = 'extras: ' . implode(', ', $extras);
    return $detalhe ? implode('; ', $detalhe) : 'lista de tabelas diferente';
});

smoke_teste('22 chaves estrangeiras', function (): bool|string {
    $total = (int)valor(
        'SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = ?',
        [DB_NAME]
    );
    return $total === 22 ? true : $total . ' chaves encontradas';
});

smoke_teste('Administrador ativo', function (): bool|string {
    $total = (int)valor("SELECT COUNT(*) FROM utilizador WHERE perfil = 'administrador' AND ativo = 1");
    return $total >= 1 ? true : 'não existe administrador ativo';
});

smoke_teste('Hashes de palavra-passe válidos', function (): bool|string {
    foreach (consulta('SELECT id_utilizador, password_hash FROM utilizador') as $utilizador) {
        $info = password_get_info((string)$utilizador['password_hash']);
        if (($info['algoName'] ?? 'unknown') === 'unknown') {
            return 'hash inválido no utilizador ' . (int)$utilizador['id_utilizador'];
        }
    }
    return true;
});

smoke_teste('Pasta uploads gravável', function () use ($raiz): bool|string {
    if (!is_dir(UPLOAD_DIR)) return 'não existe: ' . UPLOAD_DIR;
    $ficheiro = UPLOAD_DIR . '.smoke_write_test';
    if (@file_put_contents($ficheiro, 'teste') === false) return 'sem permissão de escrita';
    @unlink($ficheiro);
    return true;
});

smoke_teste('Limite de upload coerente com o php.ini', function (): bool|string {
    $limite = smoke_bytes((string)ini_get('upload_max_filesize'));
    if ($limite <= 0) return 'upload_max_filesize inválido';
    return UPLOAD_MAX_BYTES <= $limite
        ? true
        : 'aplicação ' . UPLOAD_MAX_BYTES . ' bytes > php.ini ' . $limite . ' bytes';
});

/* --------------------------- Funções auxiliares --------------------------- */

smoke_teste('e() escapa HTML', function (): bool|string {
    $entrada = '<b>"x" & \'y\'</b>';
    $esperado = htmlspecialchars($entrada, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return e($entrada) === $esperado ? true : 'resultado diferente';
});

smoke_teste('pesquisa_get() limpa e limita o texto', function (): bool|string {
    $antes = $_GET;
    try {
        $_GET = ['q' => '  microscopio  '];
        $normal = pesquisa_get();
        $_GET = ['q' => str_repeat('a', 120)];
        $longo = pesquisa_get();
        if ($normal !== 'microscopio') return 'trim inesperado: [' . $normal . ']';
        if (mb_strlen($longo) !== 100) return 'limite inesperado: ' . mb_strlen($longo);
        return true;
    } finally {
        $_GET = $antes;
    }
});

smoke_teste('pagina_get() nunca é inferior a 1', function (): bool|string {
    $antes = $_GET;
    try {
        $_GET = ['pagina' => '0'];
        $zero = pagina_get();
        $_GET = ['pagina' => '-5'];
        $negativa = pagina_get();
        $_GET = ['pagina' => '3'];
        $tres = pagina_get();
        return ($zero === 1 && $negativa === 1 && $tres === 3)
            ? true
            : $zero . '/' . $negativa . '/' . $tres;
    } finally {
        $_GET = $antes;
    }
});

smoke_teste('paginacao_calcular() calcula páginas e offset', function (): bool|string {
    $antes = $_GET;
    try {
        $_GET = ['pagina' => '2'];
        $p = paginacao_calcular(45, 20);
        if ($p['total'] !== 45 || $p['paginas'] !== 3 || $p['pagina'] !== 2 || $p['offset'] !== 20) {
            return json_encode($p);
        }

        $_GET = ['pagina' => '999'];
        $p = paginacao_calcular(45, 20);
        if ($p['pagina'] !== 3 || $p['offset'] !== 40) {
            return 'página 999: ' . json_encode($p);
        }

        $_GET = [];
        $p = paginacao_calcular(0, 20);
        if ($p['paginas'] !== 1 || $p['pagina'] !== 1 || $p['offset'] !== 0) {
            return 'total 0: ' . json_encode($p);
        }
        return true;
    } finally {
        $_GET = $antes;
    }
});

smoke_teste('pesquisa_sql() usa parâmetros', function (): bool|string {
    $params = [];
    $sql = pesquisa_sql(['p.titulo', 'p.descricao'], 'abc', $params);
    if ($sql !== '(p.titulo LIKE ? OR p.descricao LIKE ?)') return 'SQL: ' . $sql;
    if ($params !== ['%abc%', '%abc%']) return 'parâmetros: ' . json_encode($params);

    $params = [];
    if (pesquisa_sql(['x'], '', $params) !== '' || $params !== []) return 'pesquisa vazia';
    return true;
});

smoke_teste('paginacao_links() preserva a pesquisa', function (): bool|string {
    $antesGet = $_GET;
    $antesScript = $_SERVER['SCRIPT_NAME'] ?? null;
    try {
        $_GET = ['q' => 'abc', 'pagina' => '2'];
        $_SERVER['SCRIPT_NAME'] = '/x.php';
        $html = paginacao_links([
            'total' => 45, 'por' => 20, 'pagina' => 2, 'paginas' => 3, 'offset' => 20,
        ]);
        if (!str_contains($html, 'Página 2 de 3')) return 'texto da página em falta';
        if (!str_contains($html, '45 registo(s)')) return 'total em falta';
        if (!str_contains($html, 'q=abc')) return 'pesquisa não preservada';
        if (!str_contains($html, 'pagina=1') || !str_contains($html, 'pagina=3')) return 'links em falta';

        $vazio = paginacao_links([
            'total' => 0, 'por' => 20, 'pagina' => 1, 'paginas' => 1, 'offset' => 0,
        ]);
        if ($vazio !== '') return 'uma página devia devolver vazio';
        return true;
    } finally {
        $_GET = $antesGet;
        if ($antesScript === null) {
            unset($_SERVER['SCRIPT_NAME']);
        } else {
            $_SERVER['SCRIPT_NAME'] = $antesScript;
        }
    }
});

smoke_teste('Funções de apresentação', function (): bool|string {
    if (rotulo('em_curso') !== 'Em curso') return 'rotulo()';
    if (!str_contains(tag('aprovada'), 'Aprovada')) return 'tag()';
    if (data_pt('2026-01-02 15:04:05', true) !== '02/01/2026 15:04') return 'data_pt()';
    if (formatar_duracao(90) !== '1 h 30 min') return 'formatar_duracao()';
    if (iniciais('Rafael Alves') !== 'RA') return 'iniciais()';
    return true;
});

/* ----------------------------- Ficheiros web ------------------------------ */

smoke_teste('Ficheiros .htaccess de proteção', function () use ($raiz): bool|string {
    $ficheiros = [
        '.htaccess',
        'includes/.htaccess',
        'database/.htaccess',
        'uploads/.htaccess',
        'tests/.htaccess',
        'tools/.htaccess',
        'backups/.htaccess',
    ];
    foreach ($ficheiros as $relativo) {
        if (!is_file($raiz . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativo))) {
            return 'falta ' . $relativo;
        }
    }

    $raizHtaccess = (string)file_get_contents($raiz . '/.htaccess');
    if (!str_contains($raizHtaccess, 'Options -Indexes')) return 'raiz sem Options -Indexes';
    if (!str_contains($raizHtaccess, 'Require all denied')) return 'raiz sem Require all denied';

    foreach (['database/.htaccess', 'includes/.htaccess', 'uploads/.htaccess', 'tests/.htaccess', 'tools/.htaccess', 'backups/.htaccess'] as $relativo) {
        $conteudo = (string)file_get_contents($raiz . '/' . $relativo);
        if (!str_contains($conteudo, 'Require all denied')) return $relativo . ' sem Require all denied';
    }
    return true;
});

/* ------------------------------- Teste HTTP ------------------------------- */

$url = '';
foreach (array_slice($argv, 1) as $argumento) {
    if (str_starts_with($argumento, '--url=')) {
        $url = rtrim(substr($argumento, 6), '/');
    }
}

if ($url === '') {
    smoke_aviso('Teste HTTP', 'não executado; usa --url=http://localhost/... para o ativar');
} else {
    smoke_teste('HTTP login.php responde 200', function () use ($url): bool|string {
        [$corpo, $cabecalhos] = smoke_http($url . '/login.php');
        $estado = 0;
        $mapa = [];
        foreach ($cabecalhos as $cabecalho) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $cabecalho, $m)) {
                $estado = (int)$m[1];
                continue;
            }
            if (str_contains($cabecalho, ':')) {
                [$nome, $valor] = explode(':', $cabecalho, 2);
                $mapa[strtolower(trim($nome))] = trim($valor);
            }
        }

        if ($estado !== 200) return 'HTTP ' . $estado;
        foreach (['x-content-type-options', 'x-frame-options', 'referrer-policy', 'content-security-policy'] as $necessario) {
            if (empty($mapa[$necessario])) return 'falta o cabeçalho ' . $necessario;
        }
        if (isset($mapa['x-powered-by'])) return 'X-Powered-By presente';
        if (!str_contains($corpo, 'name="csrf"')) return 'token CSRF em falta no HTML';

        $cookie = $mapa['set-cookie'] ?? '';
        if (!str_contains($cookie, 'ccv_sessao=')) return 'cookie de sessão em falta';
        if (!str_contains(strtolower($cookie), 'httponly')) return 'cookie sem HttpOnly';
        if (!str_contains(strtolower($cookie), 'samesite=lax')) return 'cookie sem SameSite=Lax';
        return true;
    });
}

/* -------------------------------- Resultado ------------------------------- */

echo "\n";
if ($GLOBALS['smoke_falhas'] > 0) {
    echo 'RESULTADO: ' . $GLOBALS['smoke_falhas'] . ' falha(s) em ' . $GLOBALS['smoke_total'] . " teste(s).\n";
    if ($GLOBALS['smoke_avisos'] > 0) {
        echo $GLOBALS['smoke_avisos'] . " aviso(s).\n";
    }
    exit(1);
}

echo 'RESULTADO: ' . $GLOBALS['smoke_total'] . " teste(s) passaram.\n";
if ($GLOBALS['smoke_avisos'] > 0) {
    echo $GLOBALS['smoke_avisos'] . " aviso(s).\n";
}
exit(0);
