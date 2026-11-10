<?php
require __DIR__ . '/../../inc/api.php';
metodo('GET');
$lista = buscar((string)($_GET['q'] ?? ''), cidade_pedida());
contar_aparicoes($lista, 'busca');
responder(['profissionais' => $lista]);
