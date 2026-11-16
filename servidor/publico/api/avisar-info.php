<?php
// Passo 65. POST {id, tipo}: "me avise quando o comércio colocar ..." (precisa entrar na conta).
// GET: os pedidos de aviso da pessoa.
require __DIR__ . '/../../inc/api.php';
require_once __DIR__ . '/../../inc/avisos-info.php';
$m = metodo('GET', 'POST');
$u = exigir_usuario();
if (!avisos_info_prontos()) erro('Os avisos ainda não estão ligados. Tente mais tarde.', 503);
if ($m === 'POST') {
    $p = profissional_visivel((int)(corpo()['id'] ?? 0)) ?? erro('Comércio não encontrado.', 404);
    $tipo = campo('tipo', 12);
    if (!isset(TIPOS_PEDIDO_INFO[$tipo])) erro('Pedido inválido.');
    pedir_aviso_info((int)$u['id'], (int)$p['id'], $tipo);
    responder(['ok' => true, 'jaTem' => ficha_tem_info($p, $tipo)]);
}
responder(['avisos' => array_map(fn($a) => ['id' => (int)$a['id'], 'nome' => $a['nome'], 'tipo' => $a['tipo'],
    'texto' => AVISO_INFO_TEXTO[$a['tipo']] ?? $a['tipo'], 'avisado' => $a['avisado_em'] !== null], avisos_info_do_usuario((int)$u['id']))]);
