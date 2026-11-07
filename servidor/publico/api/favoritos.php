<?php
// GET: profissionais salvos. POST {id, salvar: true|false}: salva ou tira.
require __DIR__ . '/../../inc/api.php';
$m = metodo('GET', 'POST');
$u = exigir_usuario();
if ($m === 'POST') {
    $p = profissional_visivel((int)(corpo()['id'] ?? 0)) ?? erro('Profissional não encontrado.', 404);
    if (!empty(corpo()['salvar'])) q('INSERT IGNORE INTO favoritos (usuario_id, profissional_id) VALUES (?,?)', [$u['id'], $p['id']]);
    else q('DELETE FROM favoritos WHERE usuario_id = ? AND profissional_id = ?', [$u['id'], $p['id']]);
    responder(['favorito' => !empty(corpo()['salvar'])]);
}
responder(['profissionais' => array_map('prof_resumo', linhas('SELECT ' . CAMPOS_RESUMO . ' FROM favoritos f
    JOIN profissionais p ON p.id = f.profissional_id JOIN categorias c ON c.id = p.categoria_id
    WHERE f.usuario_id = ? AND ' . VISIVEL . ' ORDER BY f.criado_em DESC', [$u['id']]))]);
