<?php
// GET: dados da conta. DELETE: exclui a conta e tudo dela (exigência das lojas de apps)
require __DIR__ . '/../../inc/api.php';
$m = metodo('GET', 'DELETE');
$u = exigir_usuario();
if ($m === 'DELETE') {
    $profs = array_column(linhas('SELECT profissional_id FROM avaliacoes WHERE usuario_id = ?', [$u['id']]), 'profissional_id');
    q('DELETE FROM usuarios WHERE id = ?', [$u['id']]);   // sessões, avaliações e salvos saem junto (ON DELETE CASCADE)
    foreach ($profs as $pid) recalcular_nota((int)$pid);
    responder(['ok' => true]);
}
responder(['usuario' => usuario_publico($u)]);
