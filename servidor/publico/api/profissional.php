<?php
require __DIR__ . '/../../inc/api.php';
metodo('GET');
$p = profissional_visivel((int)($_GET['id'] ?? 0)) ?? erro('Profissional não encontrado.', 404);
// origem = de onde a pessoa abriu (app 1.5.0+); 'nenhuma' = recarga da mesma tela ou tela de avaliar (não conta).
// Sem origem = app antigo: conta como 'app'.
$origem = isset($_GET['origem']) ? (string)$_GET['origem'] : null;
if ($origem !== 'nenhuma') contar_visita((int)$p['id'], origem_valida($origem));
responder(['profissional' => prof_completo($p, ($u = usuario_app()) ? (int)$u['id'] : null)]);
