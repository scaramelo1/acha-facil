<?php
// Cortar o acesso de um dono ao painel (o link para de funcionar na hora)
require __DIR__ . '/../../inc/admin.php';
$admin = exigir_admin();
exigir_post();
$d = linha('SELECT id, profissional_id, nome FROM acessos_dono WHERE id = ? AND ativo = 1', [(int)($_POST['id'] ?? 0)]);
if ($d) {
    q('UPDATE acessos_dono SET ativo = 0 WHERE id = ?', [$d['id']]);
    registrar((int)$admin['id'], 'cortou_acesso', (int)$d['profissional_id'], $d['nome']);
    flash('Acesso de ' . $d['nome'] . ' cortado.');
}
ir('admin/');
