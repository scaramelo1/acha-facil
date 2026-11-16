<?php
// Passo 59: a pessoa tocou em "Quero saber o horário", "Quero ver fotos", "Quero saber mais" ou "Quero o WhatsApp"
// numa ficha incompleta. Conta 1 por pessoa, ficha e tipo no dia (inc/numeros.php pedir_info).
require __DIR__ . '/../../inc/api.php';
require_once __DIR__ . '/../../inc/numeros.php';
metodo('POST');
$p = profissional_visivel((int)(corpo()['id'] ?? 0)) ?? erro('Comércio não encontrado.', 404);
$tipo = campo('tipo', 12);
if (!isset(TIPOS_PEDIDO_INFO[$tipo])) erro('Pedido inválido.');
// passo 68: com conta (X-Token no app, cookie + X-AF-Web no site) guarda quem pediu; sem conta fica anônimo
$u = usuario_app();
pedir_info((int)$p['id'], $tipo, campo('origem', 12) === 'web' ? 'web' : 'app', $u ? (int)$u['id'] : null);
responder(['ok' => true]);
