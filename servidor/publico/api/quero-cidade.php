<?php
// "Quero minha Cidade!": a pessoa pede o Acha Fácil na cidade dela (não precisa de conta). {cidade, uf}
require __DIR__ . '/../../inc/api.php';
metodo('POST');
$cidade = trim(preg_replace('/\s+/', ' ', campo('cidade', 80)));
$uf = strtoupper(trim(campo('uf', 10)));
const UFS = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
if (mb_strlen($cidade) < 2 || !preg_match('/\p{L}/u', $cidade)) erro('Digite o nome da sua cidade.');
if ($uf !== '' && !in_array($uf, UFS, true)) erro('Estado: só a sigla, como SP ou MG.');
if ((int)valor('SELECT COUNT(*) FROM cidades_pedidas WHERE ip = ? AND criado_em > NOW() - INTERVAL 1 DAY', [ip()]) >= 5)
    responder(['ok' => true]);   // não conta de novo, mas não incomoda a pessoa
inserir('cidades_pedidas', ['cidade' => $cidade, 'uf' => $uf ?: null, 'ip' => ip()]);
require_once __DIR__ . '/../../inc/avisos.php';
$total = (int)valor('SELECT COUNT(*) FROM cidades_pedidas WHERE LOWER(cidade) = LOWER(?)', [$cidade]);
avisar_admins('Quero minha Cidade!', "Pediram o Acha Fácil em $cidade" . ($uf ? " ($uf)" : '') . ($total > 1 ? " ({$total}º pedido)" : '') . '.');
responder(['ok' => true]);
