<?php
// Conversão da abordagem e projeção de ganhos (20/11, passo 118, pedido do usuário: "refazer a projeção com a taxa real de
// conversão do primeiro mês de abordagem, desconsiderando os pagamentos deste mês, começando do zero").
// Tudo conta a partir de CONVERSAO_INICIO: pagamentos anteriores (os de 08–17/11) e a chamada de 19/11 ficam fora.
// Abordagem = registro 'chamou_comercio' (admin/chamar.php); "Desfazer" grava 'desfez_chamada' e tira da conta.
// Sem migração: lê registro_admin, pedidos e profissionais.
declare(strict_types=1);

const CONVERSAO_INICIO = '2026-11-20 00:00:00';
const CONVERSAO_TAXA_MP = 0.05;          // taxa aproximada do Mercado Pago
const CONVERSAO_MIN_ABORDADOS = 30;      // antes disso a projeção aparece com aviso de "poucos dados"
const CONVERSAO_CANCEL_PADRAO = [0.25, 0.15];   // [pessimista, normal] enquanto não houver Destaque com 35+ dias

/** Comércios abordados desde o início: [id => primeira chamada], sem os desfeitos depois da última chamada. */
function conversao_abordados(): array {
    $r = [];
    foreach (linhas("SELECT profissional_id AS id, acao, criado_em FROM registro_admin
            WHERE acao IN ('chamou_comercio','desfez_chamada') AND profissional_id IS NOT NULL AND criado_em >= ? ORDER BY id",
            [CONVERSAO_INICIO]) as $l) {
        $id = (int)$l['id'];
        if ($l['acao'] === 'desfez_chamada') unset($r[$id]);
        elseif (!isset($r[$id])) $r[$id] = (string)$l['criado_em'];
    }
    return $r;
}

/** Pedidos desde o início (assumir grátis ou pago), com o que foi pago de verdade (cortesia/valor 0 não conta). */
function conversao_pedidos(): array {
    return linhas("SELECT id, profissional_id, plano, valor, mp_status, status, criado_em FROM pedidos
        WHERE tipo = 'assumir' AND criado_em >= ? AND status <> 'recusado' ORDER BY id", [CONVERSAO_INICIO]);
}

function conversao_pago(array $p): bool { return pedido_pago($p) && (float)$p['valor'] > 0; }

/** Limite de baixo (Wilson, ~80%) da taxa: o "pessimista" honesto com poucos dados. */
function conversao_wilson_baixo(int $sim, int $n, float $z = 1.28): float {
    if ($n <= 0) return 0.0;
    $p = $sim / $n;
    $den = 1 + $z * $z / $n;
    $c = $p + $z * $z / (2 * $n);
    $m = $z * sqrt($p * (1 - $p) / $n + $z * $z / (4 * $n * $n));
    return max(0.0, ($c - $m) / $den);
}

/** Junta tudo: funil da abordagem, pagamentos orgânicos e Destaques que renovaram. */
function conversao_dados(): array {
    $ab = conversao_abordados();
    $dias = max(1, (int)ceil((time() - strtotime(CONVERSAO_INICIO)) / 86400));
    $f = ['abordados' => count($ab), 'assumiram' => [], 'basico' => [], 'destaque' => [], 'site' => []];
    $org = ['assumiram' => [], 'basico' => [], 'destaque' => [], 'site' => []];
    $destaques = [];   // pedidos de Destaque/Site pagos, para medir a renovação
    foreach (conversao_pedidos() as $p) {
        $id = (int)$p['profissional_id'];
        $veio = isset($ab[$id]) && $p['criado_em'] >= $ab[$id];   // pedido depois da chamada = conversão da abordagem
        $alvo = &$f; if (!$veio) $alvo = &$org;
        $alvo['assumiram'][$id] = true;
        if (conversao_pago($p)) {
            $plano = in_array($p['plano'], ['destaque', 'site'], true) ? $p['plano'] : 'basico';
            $alvo[$plano][$id] = true;
            if ($plano !== 'basico') $destaques[$id] = (string)$p['criado_em'];
        }
        unset($alvo);
    }
    foreach (['assumiram', 'basico', 'destaque', 'site'] as $k) { $f[$k] = count($f[$k]); $org[$k] = count($org[$k]); }

    // renovação: Destaques pagos há 35+ dias que continuam com a assinatura em dia
    $velhos = array_keys(array_filter($destaques, fn($d) => strtotime($d) <= strtotime('-35 days')));
    $renovou = 0;
    if ($velhos) $renovou = (int)valor('SELECT COUNT(*) FROM profissionais p WHERE p.id IN (' . implode(',', array_map('intval', $velhos))
        . ") AND p.assinatura = 'ativa' AND p.assinatura_ate >= CURDATE()");
    return ['dias' => $dias, 'funil' => $f, 'organico' => $org, 'destaques_35' => count($velhos), 'renovaram' => $renovou];
}

/**
 * Projeção de 12 meses nos dois cenários. Normal = taxa medida; pessimista = limite de baixo (Wilson) da taxa,
 * metade do orgânico e o cancelamento mais alto. $ritmo = abordagens por mês; $ads = AdSense por mês hoje (R$).
 */
function conversao_projecao(array $d, int $ritmo, float $ads): array {
    $n = $d['funil']['abordados'];
    $taxa = fn(string $k, bool $pess) => $pess ? conversao_wilson_baixo($d['funil'][$k], $n) : ($n ? $d['funil'][$k] / $n : 0.0);
    $por_mes = 30 / max(1, $d['dias']);
    if ($d['destaques_35'] >= 3) {
        $cancel_n = 1 - $d['renovaram'] / $d['destaques_35'];
        $cancel = [min(1.0, 1 - conversao_wilson_baixo($d['renovaram'], $d['destaques_35'])), $cancel_n];
        $cancel_medido = true;
    } else { $cancel = CONVERSAO_CANCEL_PADRAO; $cancel_medido = false; }
    $preco = ['basico' => (float)PLANOS_PRECO['basico'], 'destaque' => (float)PLANOS_PRECO['destaque'], 'site' => (float)(PLANOS_PRECO['site'] ?? 89.90)];

    $saida = ['cancel_medido' => $cancel_medido, 'cancel' => $cancel];
    foreach (['pessimista' => true, 'normal' => false] as $nome => $pess) {
        $org = fn(string $k) => $d['organico'][$k] * $por_mes * ($pess ? 0.5 : 1);
        $novo = [];
        foreach (['basico', 'destaque', 'site'] as $k) $novo[$k] = $ritmo * $taxa($k, $pess) + $org($k);
        $c = $pess ? $cancel[0] : $cancel[1];
        $dest = $site = 0.0; $total = 0.0; $meses = [];
        for ($m = 1; $m <= 12; $m++) {
            $dest = $dest * (1 - $c) + $novo['destaque'];
            $site = $site * (1 - $c / 2) + $novo['site'];   // quem tem site cancela menos (tem domínio e página)
            $planos = $novo['basico'] * $preco['basico'] + $dest * $preco['destaque'] + $site * $preco['site'];
            $liq = $planos * (1 - CONVERSAO_TAXA_MP) + $ads;
            $total += $liq;
            $meses[] = ['mes' => $m, 'basico' => $novo['basico'], 'destaque' => $dest, 'site' => $site, 'liquido' => $liq, 'acumulado' => $total];
        }
        $saida[$nome] = ['taxas' => ['assumiram' => $taxa('assumiram', $pess), 'basico' => $taxa('basico', $pess),
            'destaque' => $taxa('destaque', $pess), 'site' => $taxa('site', $pess)], 'novos' => $novo, 'cancel' => $c, 'meses' => $meses];
    }
    return $saida;
}
