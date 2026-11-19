<?php
require __DIR__ . '/../../inc/api.php';
require_once __DIR__ . '/../../inc/busca.php';
metodo('GET');
// 19/11: ?aberto=1 (app 1.12.0) = só quem informou o horário e está aberto agora
// 19/11 (passo 106): palavras separadas, sinônimos e correção (inc/busca.php); a busca fica guardada para o admin
$aberto = !empty($_GET['aberto']);
$termo = (string)($_GET['q'] ?? '');
$cidade = cidade_pedida();
$r = busca_executar($termo, $cidade, 50, $aberto);
$lista = array_map('prof_resumo', $r['lista']);
if ($aberto) $lista = array_values(array_filter($lista, fn($p) => $p['abertoAgora'] === true));
contar_aparicoes($lista, 'busca');
registrar_busca($termo, $cidade, count($lista), $r['corrigido'], 'app');
responder(['profissionais' => $lista, 'corrigido' => $r['corrigido'], 'parcial' => $r['parcial']]);
