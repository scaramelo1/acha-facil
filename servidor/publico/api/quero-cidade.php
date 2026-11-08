<?php
// "Quero minha Cidade!": a pessoa pede o Acha Fácil na cidade dela (não precisa de conta). {cidade, uf}
// O estado e a cidade precisam existir na lista do IBGE (dados/municipios.json); grava o nome oficial.
require __DIR__ . '/../../inc/api.php';
require_once __DIR__ . '/../../inc/guia.php';
metodo('POST');
$uf = strtoupper(trim(campo('uf', 10)));
if (!isset(municipios()['estados'][$uf])) erro('Escolha o estado.');
$cidade = municipio_oficial($uf, campo('cidade', 80)) ?? erro('Escolha a cidade na lista.');
if ((int)valor('SELECT COUNT(*) FROM cidades_pedidas WHERE ip = ? AND criado_em > NOW() - INTERVAL 1 DAY', [ip()]) >= 5)
    responder(['ok' => true]);   // não conta de novo, mas não incomoda a pessoa
inserir('cidades_pedidas', ['cidade' => $cidade, 'uf' => $uf, 'ip' => ip()]);
require_once __DIR__ . '/../../inc/avisos.php';
$total = (int)valor('SELECT COUNT(*) FROM cidades_pedidas WHERE cidade = ? AND uf = ?', [$cidade, $uf]);
avisar_admins('Quero minha Cidade!', "Pediram o Acha Fácil em $cidade ($uf)" . ($total > 1 ? " ({$total}º pedido)" : '') . '.');
responder(['ok' => true]);
