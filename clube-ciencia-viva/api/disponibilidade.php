<?php
/* ==========================================================================
   FICHEIRO: api/disponibilidade.php
   Para que serve: responde em JSON a "quantas unidades de cada equipamento
   estão livres entre INICIO e FIM?". É chamado pelo js/reservas.js quando
   escolhes as datas no formulário de nova reserva.
   Pedido:   api/disponibilidade.php?inicio=2026-11-05T10:00&fim=2026-11-05T12:00
   Resposta: {"ok":true,"livres":{"1":3,"2":0}}
   ========================================================================== */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reservas.php';
header('Content-Type: application/json; charset=utf-8');

if (!utilizador_atual()) { http_response_code(401); exit(json_encode(['ok' => false, 'erro' => 'Sessão expirada.'])); }

$ini = valida_data((string)($_GET['inicio'] ?? ''), 'Y-m-d\TH:i');
$fim = valida_data((string)($_GET['fim'] ?? ''), 'Y-m-d\TH:i');
if (!$ini || !$fim || $fim <= $ini) { http_response_code(400); exit(json_encode(['ok' => false, 'erro' => 'Período inválido.'])); }

echo json_encode(['ok' => true, 'livres' => (object)disponibilidade($ini->format('Y-m-d H:i:00'), $fim->format('Y-m-d H:i:00'))]);
