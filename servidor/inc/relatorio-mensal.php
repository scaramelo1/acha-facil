<?php
// Relatório mensal automático para quem paga (19/11, pedido do usuário; mudança 031, tabela relatorios_mensais).
// No dia 1 de cada mês (cron, servidor/relatorio-mensal.php) cada comércio com Básico pago ou Destaque em dia ganha o
// resumo do mês anterior e o dono recebe um aviso no celular: "Sua ficha foi vista 230 vezes e teve 18 toques no WhatsApp".
// Tocar abre o relatório de 1 folha (o mesmo do admin, /painel/relatorio.php) por um link assinado, sem precisar entrar.
declare(strict_types=1);
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/guia.php';
require_once __DIR__ . '/numeros.php';

const MESES_NOME = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

function mes_nome(string $mes): string { return MESES_NOME[(int)substr($mes, 5, 2)] ?? $mes; }

/** Chave do link do relatório de um mês (não dá para trocar o número do comércio ou o mês no endereço). */
function relatorio_chave(int $profissional_id, string $mes): string {
    return substr(hash_hmac('sha256', "relatorio|$profissional_id|$mes", sal_visitas()), 0, 24);
}
function relatorio_link(int $profissional_id, string $mes): string {
    return url_publica('painel/relatorio.php?id=' . $profissional_id . '&mes=' . $mes . '&k=' . relatorio_chave($profissional_id, $mes));
}

/** Contas Google ligadas ao comércio: quem pagou, assumiu no Grátis, incluiu o negócio ou tem acesso ao painel. */
function contas_do_comercio(int $profissional_id): array {
    return array_map('intval', array_column(linhas("SELECT DISTINCT pe.usuario_id FROM pedidos pe
        LEFT JOIN acessos_dono a ON a.pedido_id = pe.id AND a.ativo = 1
        WHERE pe.profissional_id = ? AND pe.usuario_id IS NOT NULL AND (a.id IS NOT NULL OR pe.mp_status IN ('authorized','approved')
            OR (pe.tipo = 'incluir' AND pe.status = 'feito'))", [$profissional_id]), 'usuario_id'));
}

/** A frase do aviso: "Sua ficha foi vista 230 vezes e teve 18 toques no WhatsApp." */
function relatorio_frase(array $r): string {
    $vis = (int)$r['visualizacoes'];
    $vezes = fn(int $n) => num($n) . ($n === 1 ? ' vez' : ' vezes');
    if ($vis === 0) return 'Sua ficha ainda não teve visitas em ' . mes_nome($r['mes']) . '. Veja como aparecer mais.';
    $w = (int)$r['whatsapp'];
    $outros = (int)$r['ligar'] + (int)$r['mapa'];
    $toques = $w ? ' e teve ' . num($w) . ($w === 1 ? ' toque' : ' toques') . ' no WhatsApp'
        : ($outros ? ' e teve ' . num($outros) . ($outros === 1 ? ' toque' : ' toques') . ' para ligar ou ir até você' : '');
    return 'Sua ficha foi vista ' . $vezes($vis) . $toques . '. Toque para ver o relatório.';
}

/**
 * Gera (e, com $avisar, manda) o relatório de um mês para todos os pagantes. Cada comércio uma vez por mês.
 * Devolve [gerados, avisos entregues, lista para conferência].
 */
function relatorios_do_mes(string $mes, bool $avisar, bool $so_ver = false): array {
    $desde = "$mes-01";
    $ate = date('Y-m-t', strtotime($desde));
    $ja = array_flip(array_map('intval', array_column(linhas('SELECT profissional_id FROM relatorios_mensais WHERE mes = ?', [$mes]), 'profissional_id')));
    $pagantes = linhas("SELECT p.id, p.nome FROM profissionais p WHERE p.status = 'ativo' AND " . SQL_EM_DIA);
    require_once __DIR__ . '/avisos.php';
    $gerados = $entregues = 0;
    $lista = [];
    foreach ($pagantes as $p) {
        $pid = (int)$p['id'];
        if (isset($ja[$pid])) continue;
        $n = numeros_ficha($pid, $desde, $ate);
        $r = ['mes' => $mes, 'visualizacoes' => $n['visualizacoes'], 'pessoas' => $n['pessoas'], 'aparicoes' => $n['aparicoes'],
            'whatsapp' => $n['por_tipo']['whatsapp'] ?? 0, 'ligar' => $n['por_tipo']['ligar'] ?? 0, 'mapa' => $n['por_tipo']['mapa'] ?? 0];
        $contas = contas_do_comercio($pid);
        $lista[] = ['id' => $pid, 'nome' => $p['nome'], 'contas' => count($contas), 'frase' => relatorio_frase($r)];
        if ($so_ver) continue;
        $avisados = 0;
        if ($avisar) foreach ($contas as $u) {
            $avisados += avisar_usuario($u, '📊 Seu ' . mes_nome($mes) . ' no Acha Fácil', mb_substr($p['nome'] . ': ' . relatorio_frase($r), 0, 178),
                // app antigo abre a ficha; o novo abre o relatório (url)
                ['tela' => 'profissional', 'id' => $pid, 'url' => relatorio_link($pid, $mes)]);
        }
        inserir('relatorios_mensais', ['profissional_id' => $pid] + $r + ['avisados' => $avisados]);
        $gerados++;
        $entregues += $avisados;
    }
    return [$gerados, $entregues, $lista];
}
