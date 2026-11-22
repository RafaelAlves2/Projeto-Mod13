<?php
/* ==========================================================================
   FICHEIRO: includes/ficheiros.php
   Para que serve: guardar e apagar ficheiros enviados (upload). Os ficheiros
   ficam na pasta uploads/ (bloqueada ao público pelo .htaccess) com um nome
   aleatório; na tabela `ficheiro` fica o nome original, o tipo e o tamanho.
   Só se descarregam através de download.php (que verifica o login).
   ========================================================================== */

/* guardar_upload($_FILES['ficheiro'], id do utilizador, &$caminhoGuardado)
   Devolve [id_ficheiro, null] se correu bem, ou [null, 'texto do erro'].
   $caminhoGuardado recebe o caminho físico do ficheiro depois de move_uploaded_file(),
   para o chamador o poder apagar se o resto da operação falhar. */
function guardar_upload(array $f, int $idUtilizador, ?string &$caminhoGuardado = null): array
{
    $caminhoGuardado = null;
    if (!isset($f['error']) || is_array($f['error']))   return [null, 'Envio inválido.'];
    if ($f['error'] === UPLOAD_ERR_NO_FILE)             return [null, 'Escolhe um ficheiro.'];
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE)
                                                        return [null, 'O ficheiro é demasiado grande.'];
    if ($f['error'] !== UPLOAD_ERR_OK)                  return [null, 'Falhou o envio do ficheiro (código ' . (int)$f['error'] . ').'];
    if ($f['size'] > UPLOAD_MAX_BYTES)                  return [null, 'O ficheiro é demasiado grande (máximo ' . tamanho_legivel(UPLOAD_MAX_BYTES) . ').'];

    $nomeOriginal = basename(str_replace('\\', '/', (string)$f['name']));
    $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
    if (!isset(UPLOAD_TIPOS[$ext])) {
        return [null, 'Tipo de ficheiro não permitido. Aceites: ' . implode(', ', array_keys(UPLOAD_TIPOS)) . '.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);     // lê o conteúdo real
    if (!in_array($mime, UPLOAD_TIPOS[$ext], true)) {
        return [null, 'O conteúdo do ficheiro não corresponde à extensão .' . $ext . '.'];
    }
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);

    $guardado = bin2hex(random_bytes(16)) . '.' . $ext;                // nome aleatório
    $destino = UPLOAD_DIR . $guardado;
    if (!move_uploaded_file($f['tmp_name'], $destino)) {
        return [null, 'Não consegui guardar o ficheiro no servidor.'];
    }
    $caminhoGuardado = $destino;
    $nomeLimpo = mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $nomeOriginal), 0, 150);
    try {
        executa('INSERT INTO ficheiro (nome, caminho, tipo, tamanho, id_utilizador) VALUES (?, ?, ?, ?, ?)',
                [$nomeLimpo, $guardado, $mime, (int)$f['size'], $idUtilizador]);
    } catch (Throwable $t) {
        @unlink($destino);                    // não deixar o ficheiro sem registo na BD
        $caminhoGuardado = null;
        throw $t;
    }
    return [(int)db()->lastInsertId(), null];
}

/* Depois de desligar um ficheiro de um projeto/atividade: se já não estiver
   ligado a nada, apaga-o da base de dados e da pasta uploads/. */
function ficheiro_limpar_se_orfao(int $id): void
{
    $n = (int)valor('SELECT (SELECT COUNT(*) FROM projeto_ficheiro WHERE id_ficheiro = ?)
                          + (SELECT COUNT(*) FROM atividade_ficheiro WHERE id_ficheiro = ?)', [$id, $id]);
    if ($n > 0) return;
    $f = linha('SELECT caminho FROM ficheiro WHERE id_ficheiro = ?', [$id]);
    if ($f) {
        $caminho = UPLOAD_DIR . basename($f['caminho']);
        if (is_file($caminho)) @unlink($caminho);
        executa('DELETE FROM ficheiro WHERE id_ficheiro = ?', [$id]);
    }
}

function tamanho_legivel(int $bytes): string
{
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1, ',', '') . ' MB';
    if ($bytes >= 1024)    return number_format($bytes / 1024, 0, ',', '') . ' KB';
    return $bytes . ' B';
}
