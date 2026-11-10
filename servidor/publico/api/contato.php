<?php
// O app avisa quando a pessoa toca em WhatsApp, ligar, Instagram ou mapa (estatística para o profissional)
require __DIR__ . '/../../inc/api.php';
metodo('POST');
$p = profissional_visivel((int)(corpo()['id'] ?? 0)) ?? erro('Profissional não encontrado.', 404);
contar_contato((int)$p['id'], campo('tipo', 20), campo('origem', 12) ?: 'app');
responder(['ok' => true]);
