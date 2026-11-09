<?php
// Cupons de desconto (mudança 014). O dono digita o cupom na página do pagamento (assinatura.php) antes de pagar; o
// pedido guarda o cupom e o desconto em % e preco_pedido() é o valor que vai ao Mercado Pago (e o que é conferido
// quando ele avisa). No Destaque o desconto vale em todas as mensalidades. 100% = cortesia, sem Mercado Pago.
declare(strict_types=1);
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/guia.php';

const VALOR_MINIMO_MP = 1.00;   // abaixo disso o Mercado Pago não cobra; cupom que daria menos (sem ser 100%) não vale

/** A mudança 014 (cupons) já foi aplicada? */
function cupons_prontos(): bool {
    static $ok = null;
    return $ok ??= (bool)valor("SELECT 1 FROM migracoes WHERE nome = '014-cupons.sql'");
}

/** Valor do plano com o desconto do pedido (o que o Mercado Pago cobra, todo mês no Destaque). */
function preco_pedido(array $pedido): float {
    $cheio = PLANOS_PRECO[$pedido['plano']] ?? throw new RuntimeException('Plano inválido.');
    $d = (int)($pedido['desconto'] ?? 0);
    return $d > 0 ? round($cheio * (100 - $d) / 100, 2) : $cheio;
}
function reais(float $v): string { return 'R$ ' . number_format($v, 2, ',', '.'); }

function normalizar_cupom(string $c): string { return strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', $c)); }

/** Pedidos pagos (ou cortesia) que usaram o cupom. */
function usos_cupom(int $cupom_id): int {
    return (int)valor("SELECT COUNT(*) FROM pedidos WHERE cupom_id = ? AND mp_status IN ('authorized','approved')", [$cupom_id]);
}

/** Confere o cupom para o plano do pedido. Devolve o cupom ou lança RuntimeException com o motivo para o dono. */
function cupom_valido(string $codigo, string $plano): array {
    $c = linha('SELECT * FROM cupons WHERE codigo = ?', [normalizar_cupom($codigo)]);
    if (!$c || !(int)$c['ativo']) throw new RuntimeException('Cupom não encontrado. Confira as letras e os números.');
    if ($c['valido_ate'] && $c['valido_ate'] < date('Y-m-d')) throw new RuntimeException('Este cupom já venceu.');
    if (!in_array($plano, explode(',', $c['planos']), true)) throw new RuntimeException('Este cupom não vale para o plano ' . PLANOS_NOME[$plano] . '.');
    if ($c['max_usos'] !== null && usos_cupom((int)$c['id']) >= (int)$c['max_usos']) throw new RuntimeException('Este cupom já foi usado o máximo de vezes.');
    $d = (int)$c['percentual'];
    if ($d < 100 && round(PLANOS_PRECO[$plano] * (100 - $d) / 100, 2) < VALOR_MINIMO_MP) throw new RuntimeException('Este cupom não vale para o plano ' . PLANOS_NOME[$plano] . '.');
    return $c;
}

/**
 * Põe (ou tira, com $codigo vazio) o cupom num pedido ainda não pago. Com 100%, já liga o plano como cortesia.
 * Devolve o pedido atualizado.
 */
function aplicar_cupom(array $pedido, string $codigo): array {
    if (in_array($pedido['mp_status'] ?? '', ['authorized', 'approved'], true)) throw new RuntimeException('Este pedido já está pago.');
    if (trim($codigo) === '') {
        q('UPDATE pedidos SET cupom_id = NULL, desconto = 0, valor = ?, atualizado_em = NOW() WHERE id = ?', [PLANOS_PRECO[$pedido['plano']], $pedido['id']]);
        return linha('SELECT * FROM pedidos WHERE id = ?', [$pedido['id']]);
    }
    $c = cupom_valido($codigo, (string)$pedido['plano']);
    $pedido['desconto'] = (int)$c['percentual'];
    $valor = preco_pedido($pedido);
    q('UPDATE pedidos SET cupom_id = ?, desconto = ?, valor = ?, atualizado_em = NOW() WHERE id = ?', [$c['id'], $c['percentual'], $valor, $pedido['id']]);
    if ((int)$c['percentual'] === 100) ligar_cortesia((int)$pedido['id'], (string)$c['codigo']);
    return linha('SELECT * FROM pedidos WHERE id = ?', [$pedido['id']]);
}

/** Cupom de 100%: marca o pedido como pago sem Mercado Pago e liga o plano (Básico para sempre ou Destaque cortesia). */
function ligar_cortesia(int $pedido_id, string $codigo): void {
    $marcou = q("UPDATE pedidos SET mp_status = 'approved', atualizado_em = NOW()
        WHERE id = ? AND desconto = 100 AND (mp_status IS NULL OR mp_status NOT IN ('authorized','approved'))", [$pedido_id])->rowCount();
    if ($marcou !== 1) return;
    $pedido = linha('SELECT * FROM pedidos WHERE id = ?', [$pedido_id]);
    $pid = (int)($pedido['profissional_id'] ?? 0);
    if (!$pid) return;
    if (plano_unico((string)$pedido['plano'])) {
        q('UPDATE profissionais SET basico_pago_em = COALESCE(basico_pago_em, NOW()) WHERE id = ?', [$pid]);
    } else {
        q("UPDATE profissionais SET plano = 'destaque', assinatura = 'cortesia' WHERE id = ?", [$pid]);
    }
    require_once __DIR__ . '/avisos.php';
    avisar_admins('Cupom de cortesia usado', $pedido['nome'] . ' usou o cupom ' . $codigo . ' (100%) no plano ' . (PLANOS_NOME[$pedido['plano']] ?? '')
        . ' de ' . (string)valor('SELECT nome FROM profissionais WHERE id = ?', [$pid]) . '.', ['pedido' => $pedido_id]);
}
