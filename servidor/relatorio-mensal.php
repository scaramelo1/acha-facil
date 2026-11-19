<?php
// Relatório mensal automático (19/11; regras em inc/relatorio-mensal.php). Roda pelo cron no dia 1 (/etc/cron.d/acha-facil):
//   php servidor/relatorio-mensal.php                 → gera o do mês passado e avisa os donos no celular
//   php servidor/relatorio-mensal.php --ver           → só mostra o que mandaria (nada é gravado nem enviado)
//   php servidor/relatorio-mensal.php --mes=2026-10   → outro mês
//   php servidor/relatorio-mensal.php --sem-aviso     → só gera (aparece no painel), sem aviso no celular
declare(strict_types=1);
require __DIR__ . '/inc/base.php';
require_once __DIR__ . '/inc/relatorio-mensal.php';
if (PHP_SAPI !== 'cli') exit("Rode pelo terminal.\n");
$ver = in_array('--ver', $argv, true);
$mes = date('Y-m', strtotime(date('Y-m-01') . ' -1 month'));
foreach ($argv as $a) if (preg_match('/^--mes=(\d{4}-\d{2})$/', $a, $m) && checkdate((int)substr($m[1], 5), 1, (int)substr($m[1], 0, 4))) $mes = $m[1];
if ($mes >= date('Y-m')) exit("O mês $mes ainda não terminou.\n");
if (!(bool)valor("SELECT 1 FROM migracoes WHERE nome = '031-ofertas-relatorio.sql'")) exit("Falta a mudança 031 (bash servidor/instalar.sh).\n");

[$gerados, $entregues, $lista] = relatorios_do_mes($mes, !in_array('--sem-aviso', $argv, true), $ver);
echo ($ver ? 'Mandaria' : 'Gerados') . ' ' . count($lista) . " relatórios de $mes" . ($ver ? '' : " ($entregues avisos entregues)") . ":\n";
foreach ($lista as $l) echo "  #{$l['id']} {$l['nome']} ({$l['contas']} conta" . ($l['contas'] === 1 ? '' : 's') . "): {$l['frase']}\n";
