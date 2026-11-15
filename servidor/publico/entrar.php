<?php
// Entrar no site com o Google (passo 44): a mesma conta do app. Cria a entrada em logins_google com volta "web:/página",
// guarda o "estado" num cookie deste navegador (google/retorno.php confere, para ninguém entrar no lugar de outro)
// e segue para o Google pelo mesmo caminho do app (google/entrar.php).
require __DIR__ . '/../inc/google.php';
require_once __DIR__ . '/../inc/conta-web.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
$volta = volta_web($_GET['volta'] ?? '/');
if (usuario_web()) { header('Location: ' . url(ltrim($volta, '/'))); exit; }
if (!google_configurado()) { pagina_google('Entrar está desligado', 'O login com o Google ainda não foi ligado no servidor.', $volta); exit; }
if (login_bloqueado()) { pagina_google('Muitas tentativas', 'Espere 15 minutos e tente de novo.', $volta); exit; }
q('DELETE FROM logins_google WHERE criado_em < NOW() - INTERVAL 1 HOUR');
$estado = bin2hex(random_bytes(32));
q('INSERT INTO logins_google (estado, volta) VALUES (?, ?)', [$estado, 'web:' . $volta]);
conta_cookie('af_estado', $estado, time() + 900);
header('Location: ' . url('google/entrar.php?estado=' . $estado));
