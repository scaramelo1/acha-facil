<?php
// "Perto de você": comércios da cidade escolhida mais perto do celular (lat/lng mandados pelo app), com atalho opcional (restaurantes, floriculturas…).
// A posição do usuário não é guardada: só serve para calcular a distância nesta resposta.
require __DIR__ . '/../../inc/api.php';
metodo('GET');
$lat = filter_var($_GET['lat'] ?? null, FILTER_VALIDATE_FLOAT);
$lng = filter_var($_GET['lng'] ?? null, FILTER_VALIDATE_FLOAT);
if ($lat === false || $lng === false || $lat === null || abs($lat) > 90 || abs($lng) > 180) erro('Localização inválida.');
$atalho = isset($_GET['atalho']) && $_GET['atalho'] !== '' ? (string)$_GET['atalho'] : null;
$cidade = cidade_pedida();
$lista = perto($lat, $lng, $atalho, $cidade);
// longe = nenhum comércio da cidade escolhida a até 15 km: a pessoa está em outra cidade (o app mostra os conhecidos dela)
$longe = !$lista && !($atalho !== null && perto($lat, $lng, null, $cidade, 15, 1));
responder(['atalhos' => atalhos_perto(), 'profissionais' => $lista, 'longe' => $longe]);
