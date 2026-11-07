<?php
require __DIR__ . '/../../inc/api.php';
metodo('GET');
$id = (int)($_GET['id'] ?? 0);
$c = linha('SELECT id, nome, icone FROM categorias WHERE id = ? AND ativa = 1', [$id]) ?? erro('Categoria não encontrada.', 404);
responder(['categoria' => ['id' => (int)$c['id'], 'nome' => $c['nome'], 'icone' => $c['icone']], 'profissionais' => da_categoria($id)]);
