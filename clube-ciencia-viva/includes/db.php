<?php
/* ==========================================================================
   FICHEIRO: includes/db.php
   Para que serve: a LIGAÇÃO entre o PHP e a base de dados MySQL. Qualquer
   ficheiro que precise da base de dados chama db() e recebe o objeto PDO.
   Todas as consultas usam "prepared statements" (?) para evitar SQL injection.
   ========================================================================== */
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;                       // guarda a ligação para só abrir uma vez
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // erros viram exceções
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,                    // prepared statements a sério
                ]
            );
        } catch (PDOException $ex) {
            error_log('Ligação à BD falhou: ' . $ex->getMessage());
            http_response_code(500);
            exit('Não foi possível ligar à base de dados. Confirma que o WampServer está verde '
               . 'e que a base "' . DB_NAME . '" foi importada no phpMyAdmin.'
               . (DEBUG ? ' (' . htmlspecialchars($ex->getMessage()) . ')' : ''));
        }
    }
    return $pdo;
}

/* Atalhos para consultas: devolvem logo as linhas (ou uma linha, ou um valor). */
function consulta(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}
function linha(string $sql, array $params = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $r = $st->fetch();
    return $r === false ? null : $r;
}
function valor(string $sql, array $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}
function executa(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}
