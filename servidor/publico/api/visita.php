<?php
// 16/11: a ficha que o Chrome pré-carregou foi aberta de verdade (o sinal só roda quando a pessoa vê a página).
// Conta a visita agora e marca a entrada como vista. O sinal (assinado, vale no dia) impede contar ficha/origem inventadas.
require __DIR__ . '/../../inc/api.php';
require_once __DIR__ . '/../../inc/numeros.php';
metodo('POST');
$id = (int)(corpo()['id'] ?? 0);
$origem = campo('origem', 12);
$caminho = (string)(corpo()['caminho'] ?? '');
if (!hash_equals(visita_adiada_sinal($id, $origem), (string)(corpo()['sinal'] ?? ''))) erro('Sinal inválido.', 400);
if (!profissional_visivel($id)) erro('Comércio não encontrado.', 404);
contar_visita_adiada($id, $origem, $caminho);
responder(['ok' => true]);
