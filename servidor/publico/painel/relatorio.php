<?php
// Relatório do comércio para o dono (19/11): o mesmo de 1 folha do admin (/admin/relatorio.php). Abre pelo link assinado
// do aviso mensal (?id=&mes=&k=, sem precisar entrar; só aquele mês) ou com o painel aberto (qualquer mês do próprio comércio).
require __DIR__ . '/../../inc/moderacao.php';
require_once __DIR__ . '/../../inc/relatorio-mensal.php';
require_once __DIR__ . '/../../inc/conta-web.php';
iniciar_sessao();
header('X-Robots-Tag: noindex');
$id = (int)($_GET['id'] ?? 0);
$mes = (string)($_GET['mes'] ?? '');
$k = (string)($_GET['k'] ?? '');
if (empty($_SESSION['dono_acesso']) && empty($_SESSION['dono_saiu']) && ($conta = usuario_web()) && ($ja = acesso_da_conta((int)$conta['id']))) {
    session_regenerate_id(true);
    $_SESSION['dono_acesso'] = $ja;
}
$dono = dono_atual();
if ($dono && (int)$dono['id'] === $id) define('RELATORIO_DONO', 'painel');
elseif (preg_match('/^\d{4}-\d{2}$/', $mes) && $k !== '' && hash_equals(relatorio_chave($id, $mes), $k)) define('RELATORIO_DONO', 'link');
elseif ($dono && !$id) { header('Location: ' . url('painel/relatorio.php?id=' . (int)$dono['id'])); exit; }
else { flash('Entre no painel do seu comércio para ver o relatório.', 'erro'); ir('painel/'); }
require __DIR__ . '/../admin/relatorio.php';
