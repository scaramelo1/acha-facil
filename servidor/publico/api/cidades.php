<?php
// Cidades atendidas, com quantos comércios cada uma tem (tela de escolha da cidade no app)
require __DIR__ . '/../../inc/api.php';
metodo('GET');
$totais = [];
foreach (linhas('SELECT p.cidade, COUNT(*) AS total FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE ' . visivel() . ' GROUP BY p.cidade') as $t)
    $totais[$t['cidade']] = (int)$t['total'];
responder(['cidades' => array_map(fn($slug) => [
    'slug' => $slug, 'nome' => nome_cidade($slug), 'curto' => nome_cidade($slug, true), 'total' => $totais[$slug] ?? 0,
], array_keys(CIDADES))]);
