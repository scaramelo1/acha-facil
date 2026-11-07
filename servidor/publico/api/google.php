<?php
// Entrar com o Google, sem depender de o celular devolver um link para o app:
//  1. {acao: 'iniciar', volta} -> o servidor cria a entrada e devolve o endereço para abrir no navegador e uma
//     chave secreta (só o app que pediu conhece).
//  2. a pessoa entra no Google; google/retorno.php grava a conta nessa entrada.
//  3. {acao: 'verificar', chave} -> enquanto não terminou: {pendente: true}; terminou: sessão (token) + conta,
//     uma vez só. Entrada que deu erro ou expirou: 404.
require __DIR__ . '/../../inc/api.php';
require_once __DIR__ . '/../../inc/google.php';
metodo('POST');
$acao = campo('acao', 20);

if ($acao === 'iniciar') {
    if (login_bloqueado()) erro('Muitas tentativas. Espere 15 minutos.', 429);
    if (!google_configurado()) erro('O login com o Google ainda não foi ligado no servidor.', 503);
    $volta = campo('volta', 300);
    if (!volta_valida($volta)) erro('Endereço de volta inválido.');
    q('DELETE FROM logins_google WHERE criado_em < NOW() - INTERVAL 1 HOUR');
    $estado = bin2hex(random_bytes(32));
    $chave = bin2hex(random_bytes(32));
    q('INSERT INTO logins_google (estado, volta, codigo_hash) VALUES (?, ?, ?)', [$estado, $volta, hash('sha256', $chave)]);
    responder(['endereco' => url_publica('google/entrar.php?estado=' . $estado), 'chave' => $chave]);
}

if ($acao === 'verificar') {
    $chave = campo('chave', 64);
    if (!preg_match('/^[a-f0-9]{64}$/', $chave)) erro('Chave inválida.');
    $l = linha('SELECT usuario_id FROM logins_google WHERE codigo_hash = ? AND criado_em > NOW() - INTERVAL 15 MINUTE', [hash('sha256', $chave)]);
    if (!$l) erro('Esta entrada não deu certo ou expirou. Tente de novo.', 404);
    if (!$l['usuario_id']) responder(['pendente' => true]);
    q('DELETE FROM logins_google WHERE codigo_hash = ?', [hash('sha256', $chave)]);   // uso único
    $u = linha('SELECT * FROM usuarios WHERE id = ? AND ativo = 1', [$l['usuario_id']]) ?? erro('Conta não encontrada.', 404);
    responder(['token' => criar_sessao((int)$u['id']), 'usuario' => usuario_publico($u)]);
}

erro('Ação inválida.');
