<?php
// Sugestões da tela de busca do app (19/11, passo 107): o que mais procuram na cidade e acharam (tabela buscas),
// completado com uma lista fixa enquanto ainda tem poucos dados.
require __DIR__ . '/../../inc/api.php';
require_once __DIR__ . '/../../inc/busca.php';
metodo('GET');
$l = buscas_populares(cidade_pedida(), 8);
foreach (['pizzaria', 'farmácia', 'mecânica', 'supermercado', 'dentista', 'barbearia', 'padaria', 'pet shop'] as $s)
    if (count($l) < 8 && !in_array($s, array_map('mb_strtolower', $l), true)) $l[] = $s;
header('Cache-Control: public, max-age=600');
responder(['populares' => array_values($l)]);
