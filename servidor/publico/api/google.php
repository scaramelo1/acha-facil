<?php
// O app manda o código de uso único que voltou do Google e recebe a sessão (token) e os dados da conta.
require __DIR__ . '/../../inc/api.php';
metodo('POST');
if (login_bloqueado()) erro('Muitas tentativas. Espere 15 minutos.', 429);
$codigo = campo('codigo', 64);
$l = preg_match('/^[a-f0-9]{64}$/', $codigo)
    ? linha('SELECT usuario_id FROM logins_google WHERE codigo_hash = ? AND criado_em > NOW() - INTERVAL 5 MINUTE', [hash('sha256', $codigo)])
    : null;
if (!$l || !$l['usuario_id']) { login_falhou(); erro('Esta entrada expirou. Tente de novo.', 401); }
q('DELETE FROM logins_google WHERE codigo_hash = ?', [hash('sha256', $codigo)]);   // uso único
$u = linha('SELECT * FROM usuarios WHERE id = ? AND ativo = 1', [$l['usuario_id']]) ?? erro('Conta não encontrada.', 401);
responder(['token' => criar_sessao((int)$u['id']), 'usuario' => usuario_publico($u)]);
