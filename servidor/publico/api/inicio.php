<?php
// Tela inicial do app (da cidade escolhida): vitrine de destaques, áreas, conhecidos na cidade e total
require __DIR__ . '/../../inc/api.php';
metodo('GET');
$cidade = cidade_pedida();
responder([
    'cidade' => ['slug' => $cidade, 'nome' => nome_cidade($cidade), 'curto' => nome_cidade($cidade, true)],
    'destaques' => destaques($cidade),
    'categorias' => categorias_ativas($cidade),
    'populares' => populares($cidade),
    'total' => (int)valor('SELECT COUNT(*) FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE ' . visivel($cidade)),
]);
