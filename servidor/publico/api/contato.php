<?php
// O app avisa quando a pessoa toca em WhatsApp, ligar, Instagram ou mapa (estatística para o profissional)
require __DIR__ . '/../../inc/api.php';
metodo('POST');
$p = profissional_visivel((int)(corpo()['id'] ?? 0)) ?? erro('Profissional não encontrado.', 404);
$origem = campo('origem', 12) ?: 'app';
// toque no site (passo 38): vale a origem de quando a pessoa chegou ao site (cookie de 30 min)
if ($origem === 'web' && in_array($_COOKIE['af_origem'] ?? '', ORIGENS_SITE, true)) $origem = (string)$_COOKIE['af_origem'];
contar_contato((int)$p['id'], campo('tipo', 20), $origem);
responder(['ok' => true]);
