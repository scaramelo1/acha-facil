<?php
// Dono do comércio no app (17/11, passo 93).
// GET: {comercio: {id, nome} | null} — o comércio que esta conta cuida (pago, cortesia, Grátis ou incluído).
// POST {acao: 'link_painel'}: link de uso único (2 min) que abre o painel no navegador já entrado.
require __DIR__ . '/../../inc/api.php';
require_once __DIR__ . '/../../inc/moderacao.php';
$m = metodo('GET', 'POST');
$u = exigir_usuario();
$acesso = acesso_da_conta((int)$u['id']);
$c = $acesso ? linha('SELECT p.id, p.nome FROM acessos_dono a JOIN profissionais p ON p.id = a.profissional_id WHERE a.id = ?', [$acesso]) : null;
if ($m === 'POST') {
    if (campo('acao', 20) !== 'link_painel') erro('Ação desconhecida.');
    if (!$c) erro('Esta conta não cuida de nenhum comércio no Acha Fácil.', 404);
    responder(['url' => link_painel_app((int)$u['id'])]);
}
responder(['comercio' => $c ? ['id' => (int)$c['id'], 'nome' => $c['nome']] : null]);
