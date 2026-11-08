<?php
// Estados e cidades do Brasil (IBGE) para o "Quero minha Cidade!": sem ?uf= devolve os estados; com ?uf=SP, as cidades.
require __DIR__ . '/../../inc/api.php';
require_once __DIR__ . '/../../inc/guia.php';
header('Cache-Control: public, max-age=86400');
$m = municipios();
$uf = strtoupper((string)($_GET['uf'] ?? ''));
if ($uf === '') responder(['estados' => array_map(fn($u, $n) => ['uf' => $u, 'nome' => $n], array_keys($m['estados']), $m['estados'])]);
if (!isset($m['cidades'][$uf])) erro('Estado desconhecido.', 404);
responder(['uf' => $uf, 'cidades' => $m['cidades'][$uf]]);
