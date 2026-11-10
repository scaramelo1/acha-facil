<?php
require __DIR__ . '/../../inc/api.php';
metodo('GET');
$id = (int)($_GET['id'] ?? 0);
$c = linha('SELECT id, nome, icone, cor FROM categorias WHERE id = ? AND ativa = 1', [$id]) ?? erro('Área não encontrada.', 404);
$lista = da_categoria($id, cidade_pedida());
contar_aparicoes($lista, 'categoria');
responder([
    'categoria' => ['id' => (int)$c['id'], 'nome' => $c['nome'], 'icone' => $c['icone'], 'cor' => $c['cor']],
    'tipos' => tipos_da_categoria($id, cidade_pedida()),
    'profissionais' => $lista,
]);
