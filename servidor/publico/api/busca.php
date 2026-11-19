<?php
require __DIR__ . '/../../inc/api.php';
metodo('GET');
// 19/11: ?aberto=1 (app 1.12.0) = só quem informou o horário e está aberto agora
$aberto = !empty($_GET['aberto']);
$lista = buscar((string)($_GET['q'] ?? ''), cidade_pedida(), 50, $aberto);
if ($aberto) $lista = array_values(array_filter($lista, fn($p) => $p['abertoAgora'] === true));
contar_aparicoes($lista, 'busca');
responder(['profissionais' => $lista]);
