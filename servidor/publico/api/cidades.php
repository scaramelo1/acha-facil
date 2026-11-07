<?php
// Estados e cidades atendidas, com quantos comércios cada cidade tem (tela de escolha da cidade no app)
require __DIR__ . '/../../inc/api.php';
metodo('GET');
$totais = [];
foreach (linhas('SELECT p.cidade, COUNT(*) AS total FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE ' . visivel() . ' GROUP BY p.cidade') as $t)
    $totais[$t['cidade']] = (int)$t['total'];
responder([
    'estados' => array_map(fn($uf, $nome) => ['uf' => $uf, 'nome' => $nome], array_keys(ESTADOS), ESTADOS),
    'cidades' => array_map(fn($slug) => [
        'slug' => $slug, 'nome' => nome_cidade($slug), 'curto' => nome_cidade($slug, true), 'estado' => uf_cidade($slug), 'total' => $totais[$slug] ?? 0,
    ], array_keys(CIDADES)),
]);
