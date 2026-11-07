<?php
// Foto do profissional (guardada fora da pasta pública)
require __DIR__ . '/../inc/base.php';
$f = (string)($_GET['f'] ?? '');
if (!preg_match('/^[a-z0-9-]{8,80}\.(jpg|png|webp)$/', $f) || !is_file($arq = config()['dados_dir'] . '/fotos/' . $f)) { http_response_code(404); exit; }
header('Content-Type: ' . ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][pathinfo($f, PATHINFO_EXTENSION)]);
header('Cache-Control: public, max-age=86400');
readfile($arq);
