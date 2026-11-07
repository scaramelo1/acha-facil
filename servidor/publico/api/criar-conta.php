<?php
require __DIR__ . '/../../inc/api.php';
metodo('POST');
if (login_bloqueado()) erro('Muitas tentativas. Espere 15 minutos.', 429);
$nome = campo('nome', 80);
$email = email_valido(campo('email', 160)) ?? erro('Digite um e-mail válido.');
$senha = (string)(corpo()['senha'] ?? '');
if (mb_strlen($nome) < 2) erro('Digite seu nome.');
if (mb_strlen($senha) < 6) erro('A senha precisa ter pelo menos 6 letras ou números.');
if (valor('SELECT 1 FROM usuarios WHERE email = ?', [$email])) { login_falhou(); erro('Este e-mail já tem conta. Toque em "Já tenho conta".', 409); }
$id = inserir('usuarios', ['nome' => $nome, 'email' => $email, 'senha_hash' => password_hash($senha, PASSWORD_DEFAULT)]);
responder(['token' => criar_sessao($id), 'usuario' => usuario_publico(linha('SELECT * FROM usuarios WHERE id = ?', [$id]))], 201);
