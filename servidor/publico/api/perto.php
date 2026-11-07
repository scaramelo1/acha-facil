<?php
// "Perto de você": comércios mais perto do celular (lat/lng mandados pelo app), com atalho opcional (restaurantes, floriculturas…).
// A posição do usuário não é guardada: só serve para calcular a distância nesta resposta.
require __DIR__ . '/../../inc/api.php';
metodo('GET');
$lat = filter_var($_GET['lat'] ?? null, FILTER_VALIDATE_FLOAT);
$lng = filter_var($_GET['lng'] ?? null, FILTER_VALIDATE_FLOAT);
if ($lat === false || $lng === false || $lat === null || abs($lat) > 90 || abs($lng) > 180) erro('Localização inválida.');
$atalho = isset($_GET['atalho']) && $_GET['atalho'] !== '' ? (string)$_GET['atalho'] : null;
responder(['atalhos' => atalhos_perto(), 'profissionais' => perto($lat, $lng, $atalho)]);
