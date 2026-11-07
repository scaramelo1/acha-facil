<?php
// Tela inicial do app (da cidade escolhida): comércios em destaque, áreas, conhecidos na cidade e total
require __DIR__ . '/../../inc/api.php';
metodo('GET');
$cidade = cidade_pedida();
$vitrine = vitrine_inicio($cidade);
responder([
    'cidade' => ['slug' => $cidade, 'nome' => nome_cidade($cidade), 'curto' => nome_cidade($cidade, true)],
    'destaques' => $vitrine,
    'categorias' => categorias_ativas($cidade),
    'populares' => populares($cidade, 12, array_column($vitrine, 'id')),
    'total' => (int)valor('SELECT COUNT(*) FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE ' . visivel($cidade)),
]);
