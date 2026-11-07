<?php
// Endereço de aviso (Expo Push) do aparelho de um admin. POST {token}: guarda. DELETE {token}: tira (ao sair da conta).
// Contas que não são admin não guardam nada (não há aviso para elas).
require __DIR__ . '/../../inc/api.php';
require_once __DIR__ . '/../../inc/avisos.php';
$m = metodo('POST', 'DELETE');
$u = exigir_usuario();
$token = campo('token', 200);
if (!preg_match('/^Expo(nent)?PushToken\[[A-Za-z0-9_-]{10,}\]$/', $token)) erro('Endereço de aviso inválido.');
if ($m === 'DELETE') { q('DELETE FROM avisos_aparelhos WHERE token = ? AND usuario_id = ?', [$token, $u['id']]); responder(['ok' => true]); }
if (!admin_do_usuario($u)) responder(['ok' => true, 'admin' => false]);
q('INSERT INTO avisos_aparelhos (usuario_id, token) VALUES (?, ?) ON DUPLICATE KEY UPDATE usuario_id = VALUES(usuario_id), atualizado_em = NOW()', [$u['id'], $token]);
responder(['ok' => true, 'admin' => true]);
