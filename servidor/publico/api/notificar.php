<?php
// Notificações para os usuários, pela tela Administração do app (só contas de admin). As mesmas regras do
// /admin/notificacoes.php do site (inc/notificacoes.php).
// GET: quantos aparelhos recebem (total, por cidade, com conta) e os últimos envios.
// POST {titulo, texto, cidade ('' = todas), publico ('todos' | 'contas'), profissional (nº ou link da ficha, opcional)}: manda.
require __DIR__ . '/../../inc/api.php';
require_once __DIR__ . '/../../inc/notificacoes.php';
$m = metodo('GET', 'POST');
$u = exigir_usuario();
$admin_id = admin_do_usuario($u) ?? erro('Só para administradores.', 403);

if ($m === 'POST') {
    $cid = campo('cidade', 40);
    try {
        $r = enviar_notificacao(campo('titulo', 100), campo('texto', 300), $cid === '' ? null : $cid, campo('publico', 10),
            notif_id_comercio(campo('profissional', 300)), $admin_id, 'app');
    } catch (RuntimeException $e) {
        erro($e->getMessage(), 409);
    }
    responder(['ok' => true] + $r);
}

$t = notif_totais();
$cidades = [];
foreach (TODAS_CIDADES as $slug => $c) {
    if (!($c[5] ?? true) && !isset($t['cidades'][$slug])) continue;   // desligada e sem ninguém: não mostra
    $cidades[] = ['slug' => $slug, 'nome' => $c[1], 'uf' => $c[2], 'aparelhos' => $t['cidades'][$slug]['total'] ?? 0,
        'contas' => $t['cidades'][$slug]['contas'] ?? 0];
}
responder(['pronto' => notificacoes_prontas(), 'total' => $t['total'], 'contas' => $t['contas'], 'cidades' => $cidades,
    'historico' => notif_historico(20)]);
