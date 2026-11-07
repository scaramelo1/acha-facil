<?php
// Avisos do Mercado Pago (configurar em Suas integrações > a aplicação > Webhooks, eventos "Planos e assinaturas":
// https://fsadesigners.com.br/acha-facil/mp-webhook.php). Cada aviso é conferido direto na API (inc/mercadopago.php).
require __DIR__ . '/../inc/base.php';
require __DIR__ . '/../inc/guia.php';
require __DIR__ . '/../inc/mercadopago.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$corpo = json_decode((string)file_get_contents('php://input'), true) ?: [];
$tipo = (string)($_GET['type'] ?? $_GET['topic'] ?? $corpo['type'] ?? $corpo['topic'] ?? '');
$id = (string)($_GET['data_id'] ?? $corpo['data']['id'] ?? $_GET['id'] ?? '');   // "data.id" na URL chega como data_id no PHP

if (mp_modo() === 'desligado' || $tipo === '' || $id === '') { echo '{"ok":true}'; exit; }
if (!mp_aviso_valido($id)) { http_response_code(401); echo '{"erro":"assinatura inválida"}'; exit; }

try {
    if ($tipo === 'subscription_preapproval') mp_conferir_assinatura($id);
    elseif ($tipo === 'subscription_authorized_payment') mp_conferir_mensalidade($id);
    // outros tipos (ex.: payment) não mudam nada: as mensalidades chegam como subscription_authorized_payment
} catch (Throwable $e) {
    error_log("acha-facil mp-webhook $tipo $id: " . $e->getMessage());
    http_response_code(500);   // o Mercado Pago tenta de novo mais tarde
    echo '{"erro":"falha ao conferir"}';
    exit;
}
echo '{"ok":true}';
