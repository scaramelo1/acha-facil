<?php
require __DIR__ . '/../../inc/api.php';
metodo('POST');
if (login_bloqueado()) erro('Muitas tentativas. Espere 15 minutos.', 429);
$u = linha('SELECT * FROM usuarios WHERE email = ? AND ativo = 1', [mb_strtolower(campo('email', 160))]);
if (!$u || !password_verify((string)(corpo()['senha'] ?? ''), $u['senha_hash'])) { login_falhou(); erro('E-mail ou senha incorretos.', 401); }
responder(['token' => criar_sessao((int)$u['id']), 'usuario' => usuario_publico($u)]);
