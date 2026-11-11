<?php
// Endereço de aviso (Expo Push) do aparelho. POST {token}: guarda, com a cidade escolhida (?cidade= que o app manda
// em toda chamada). Desde o app 1.6.0 vale para todos (notificações dos admins, inc/notificacoes.php), com ou sem conta;
// conta de admin também entra em avisos_aparelhos (pagamentos, cidades pedidas, alterações).
// DELETE {token}: ao sair da conta — para os avisos de admin e solta o aparelho da conta (as novidades continuam).
require __DIR__ . '/../../inc/api.php';
require_once __DIR__ . '/../../inc/avisos.php';
require_once __DIR__ . '/../../inc/notificacoes.php';
$m = metodo('POST', 'DELETE');
$u = usuario_app();
$token = campo('token', 200);
if (!preg_match('/^Expo(nent)?PushToken\[[A-Za-z0-9_-]{10,}\]$/', $token)) erro('Endereço de aviso inválido.');
if ($m === 'DELETE') {
    if ($u) {
        q('DELETE FROM avisos_aparelhos WHERE token = ? AND usuario_id = ?', [$token, $u['id']]);
        soltar_aparelho($token, (int)$u['id']);
    }
    responder(['ok' => true]);
}
$cidade = isset($_GET['cidade']) ? (string)$_GET['cidade'] : null;
guardar_aparelho($token, $u ? (int)$u['id'] : null, $cidade);
if (!$u || !admin_do_usuario($u)) responder(['ok' => true, 'admin' => false]);
q('INSERT INTO avisos_aparelhos (usuario_id, token) VALUES (?, ?) ON DUPLICATE KEY UPDATE usuario_id = VALUES(usuario_id), atualizado_em = NOW()', [$u['id'], $token]);
responder(['ok' => true, 'admin' => true]);
