<?php
// Tela inicial do app: vitrine de destaques, áreas, conhecidos na cidade e total
require __DIR__ . '/../../inc/api.php';
metodo('GET');
responder([
    'destaques' => destaques(),
    'categorias' => categorias_ativas(),
    'populares' => populares(),
    'total' => (int)valor('SELECT COUNT(*) FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE ' . VISIVEL),
]);
