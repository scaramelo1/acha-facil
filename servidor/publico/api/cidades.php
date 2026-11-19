<?php
// Estados e cidades atendidas (tabela cidades, só as ativas), com o centro de cada uma e quantos comércios tem.
// O app guarda esta lista: cidade nova cadastrada em /admin/cidades.php aparece sem APK novo.
require __DIR__ . '/../../inc/api.php';
metodo('GET');
$totais = [];
foreach (linhas('SELECT p.cidade, COUNT(*) AS total FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE ' . visivel() . ' GROUP BY p.cidade') as $t)
    $totais[$t['cidade']] = (int)$t['total'];
// 19/11 (pedido do usuário): cidades em ordem alfabética (Á junto do A); o app separa por estado
$slugs = array_keys(CIDADES);
$chave = fn(string $s) => strtr(mb_strtolower(nome_cidade($s)), ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e',
    'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);
usort($slugs, fn($a, $b) => strcmp($chave($a), $chave($b)));
responder([
    'estados' => array_map(fn($uf, $nome) => ['uf' => $uf, 'nome' => $nome], array_keys(ESTADOS), ESTADOS),
    'cidades' => array_map(fn($slug) => [
        'slug' => $slug, 'nome' => nome_cidade($slug), 'curto' => nome_cidade($slug, true), 'estado' => uf_cidade($slug),
        'lat' => CIDADES[$slug][3], 'lng' => CIDADES[$slug][4], 'total' => $totais[$slug] ?? 0,
    ], $slugs),
]);
