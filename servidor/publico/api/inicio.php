<?php
// Tela inicial do app (da cidade escolhida): comércios em destaque, áreas, recomendados (Básico e Destaque) e total
require __DIR__ . '/../../inc/api.php';
require_once __DIR__ . '/../../inc/ofertas.php';
metodo('GET');
$cidade = cidade_pedida();
$vitrine = vitrine_inicio($cidade);
$populares = recomendados($cidade);   // a chave continua 'populares' para os apps já instalados
contar_aparicoes(array_merge($vitrine, $populares), 'inicio');
responder([
    'cidade' => ['slug' => $cidade, 'nome' => nome_cidade($cidade), 'curto' => nome_cidade($cidade, true)],
    'destaques' => $vitrine,
    'categorias' => categorias_ativas($cidade),
    'populares' => $populares,
    // 19/11: Ofertas da semana da cidade (o app ainda não mostra; fica pronto para a próxima versão)
    'ofertas' => array_map(fn($o) => ['id' => (int)$o['id'], 'nome' => $o['nome'], 'titulo' => $o['oferta_titulo'],
        'precoDe' => $o['oferta_de'] !== null ? (float)$o['oferta_de'] : null, 'precoPor' => $o['oferta_por'] !== null ? (float)$o['oferta_por'] : null,
        'foto' => foto_url($o['oferta_foto']) ?? foto_url($o['foto']), 'validade' => 'Oferta da semana'], ofertas_da_cidade($cidade)),
    'total' => (int)valor('SELECT COUNT(*) FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE ' . visivel($cidade)),
]);
