<?php
require __DIR__ . '/../../inc/api.php';
metodo('GET');
$p = profissional_visivel((int)($_GET['id'] ?? 0)) ?? erro('Profissional não encontrado.', 404);
contar_contato((int)$p['id'], 'perfil');
responder(['profissional' => prof_completo($p, ($u = usuario_app()) ? (int)$u['id'] : null)]);
