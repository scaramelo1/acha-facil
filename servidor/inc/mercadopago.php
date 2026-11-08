<?php
// Pagamento dos planos pelo Mercado Pago:
//   Básico: pagamento único pelo Checkout do Mercado Pago (cartão, Pix ou boleto); aprovado = Básico para sempre.
//   Destaque: assinatura mensal no cartão ("preapproval"); o dono autoriza no Mercado Pago e ele cobra sozinho todo mês.
// O Mercado Pago avisa o servidor
// (mp-webhook.php) e cada aviso é SEMPRE conferido direto na API antes de mudar algo (nunca confia no navegador).
// Cada mensalidade paga estende a assinatura do comércio em 1 mês, uma vez só (eventos_mp.aplicado).
//
// Configuração em /etc/acha-facil/config.php (fora do git):
//   'mp_modo' => 'desligado' | 'sandbox' | 'producao'
//      desligado: o dono vê "pagamento on-line em configuração" e o pedido segue como antes (a equipe chama)
//      sandbox:   testes do Mercado Pago, com o Access Token de TESTE (nada é cobrado)
//      producao:  cobrança de verdade
//   'mp_access_token' => 'APP_USR-...' (Mercado Pago Developers > Suas integrações > a aplicação > Credenciais)
//   'mp_webhook_secret' => assinatura secreta dos avisos (Suas integrações > a aplicação > Webhooks)
//   'mp_public_key' => 'APP_USR-...' (mesma página das credenciais): liga o formulário do cartão no próprio site para o
//      Destaque, sem o dono precisar de conta no Mercado Pago (o cartão vai direto para o Mercado Pago, nunca ao servidor)
declare(strict_types=1);
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/guia.php';

const MP_API = 'https://api.mercadopago.com';

/** A mudança 007 do banco (pedidos com e-mail e assinatura, eventos_mp) já foi aplicada? */
function mp_pronto(): bool {
    static $ok = null;
    return $ok ??= (bool)valor("SELECT 1 FROM migracoes WHERE nome = '007-mercado-pago.sql'");
}
/** Public Key da aplicação (para o formulário do cartão no próprio site). Sem ela, o Destaque abre o Mercado Pago (pede conta). */
function mp_public_key(): string { return trim((string)(config()['mp_public_key'] ?? '')); }
function mp_token(): string { return trim((string)(config()['mp_access_token'] ?? '')); }
function mp_modo(): string {
    $m = (string)(config()['mp_modo'] ?? 'desligado');
    if (!mp_pronto() || mp_token() === '') return 'desligado';
    return in_array($m, ['sandbox', 'producao'], true) ? $m : 'desligado';
}

function mp_api(string $metodo, string $caminho, ?array $corpo = null, array $cabecalhos = []): array {
    if (mp_token() === '') throw new RuntimeException('Mercado Pago sem Access Token configurado.');
    $ch = curl_init(MP_API . $caminho);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => array_merge(['Authorization: Bearer ' . mp_token(), 'Content-Type: application/json'], $cabecalhos),
    ]);
    if ($corpo !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo, JSON_UNESCAPED_UNICODE));
    $resp = curl_exec($ch);
    $codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro = curl_error($ch);
    curl_close($ch);
    if ($resp === false) throw new RuntimeException('Sem resposta do Mercado Pago: ' . $erro);
    $dados = json_decode((string)$resp, true) ?: [];
    if ($codigo >= 400) throw new RuntimeException('Mercado Pago respondeu ' . $codigo . ': ' . ($dados['message'] ?? mb_substr((string)$resp, 0, 200)));
    return $dados;
}

function pedido_por_codigo(string $codigo): ?array {
    if (!mp_pronto() || !preg_match('/^[a-f0-9]{64}$/', $codigo)) return null;
    return linha("SELECT * FROM pedidos WHERE codigo = ? AND tipo = 'assumir' AND plano IS NOT NULL", [$codigo]);
}

/** A mudança 011 do banco (Básico com pagamento único) já foi aplicada? */
function mp_pronto_unico(): bool {
    static $ok = null;
    return $ok ??= (bool)valor("SELECT 1 FROM migracoes WHERE nome = '011-basico-pagamento-unico.sql'");
}

/** Pedido pago: assinatura do Destaque autorizada ou pagamento único do Básico aprovado. */
function pedido_pago(?array $pedido): bool {
    return in_array($pedido['mp_status'] ?? '', ['authorized', 'approved'], true);
}
const SQL_PEDIDO_PAGO = "mp_status IN ('authorized','approved')";

/** Devolve o endereço do Mercado Pago para o dono pagar: Checkout (Básico, uma vez só) ou assinatura (Destaque). */
function mp_assinar(array $pedido): string {
    $modo = mp_modo();
    if ($modo === 'desligado') throw new RuntimeException('O pagamento on-line ainda está sendo configurado.');
    if (!email_valido((string)$pedido['email'])) throw new RuntimeException('O pedido está sem um e-mail válido.');
    $plano = (string)$pedido['plano'];
    if (plano_unico($plano)) return mp_pagar_unico($pedido);
    $valor = PLANOS_PRECO[$plano] ?? throw new RuntimeException('Plano inválido.');

    // já existe uma assinatura esperando o dono concluir: usa o mesmo endereço (não cria outra)
    if ($pedido['mp_assinatura'] && $pedido['mp_status'] === 'pending') {
        $a = mp_api('GET', '/preapproval/' . rawurlencode((string)$pedido['mp_assinatura']));
        if (($a['status'] ?? '') === 'pending' && !empty($a['init_point'])) return (string)$a['init_point'];
    }

    $comercio = $pedido['profissional_id'] ? (string)valor('SELECT nome FROM profissionais WHERE id = ?', [$pedido['profissional_id']]) : '';
    $a = mp_api('POST', '/preapproval', [
        'reason' => mb_substr('Acha Fácil - Plano ' . PLANOS_NOME[$plano] . ($comercio !== '' ? ' - ' . $comercio : ''), 0, 250),
        'external_reference' => 'pedido:' . $pedido['id'],
        'payer_email' => $pedido['email'],
        'auto_recurring' => ['frequency' => 1, 'frequency_type' => 'months', 'transaction_amount' => $valor, 'currency_id' => 'BRL'],
        'back_url' => url_publica('assinatura.php?c=' . $pedido['codigo']),
        'status' => 'pending',
    ], ['X-Idempotency-Key: achafacil-assinatura-' . $pedido['id'] . '-' . time()]);
    if (empty($a['id']) || empty($a['init_point'])) throw new RuntimeException('O Mercado Pago não devolveu o endereço da assinatura.');
    q('UPDATE pedidos SET mp_assinatura = ?, mp_status = ?, valor = ?, atualizado_em = NOW() WHERE id = ?', [$a['id'], $a['status'] ?? 'pending', $valor, $pedido['id']]);
    mp_evento((int)$pedido['id'], 'assinatura', (string)$a['id'], (string)($a['status'] ?? 'pending'), $valor);
    return (string)$a['init_point'];
}

/**
 * Destaque com o cartão digitado no próprio site (Card Payment Brick): o navegador manda o cartão direto ao Mercado Pago e
 * recebe um token de uso único; aqui a assinatura é criada já autorizada com esse token, sem o dono ter conta no Mercado Pago.
 * Devolve o pedido atualizado; erro do Mercado Pago (cartão recusado etc.) sobe como exceção.
 */
function mp_assinar_cartao(array $pedido, array $form): array {
    if (mp_modo() === 'desligado' || mp_public_key() === '') throw new RuntimeException('Pagamento com cartão no site desligado.');
    $plano = (string)$pedido['plano'];
    if (plano_unico($plano)) throw new RuntimeException('O Básico é pago pelo Checkout, não por assinatura.');
    $valor = PLANOS_PRECO[$plano] ?? throw new RuntimeException('Plano inválido.');
    $token = (string)($form['token'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_-]{8,100}$/', $token)) throw new RuntimeException('Token do cartão inválido.');
    $email = email_valido((string)($form['payer']['email'] ?? '')) ?: email_valido((string)$pedido['email']);
    if (!$email) throw new RuntimeException('E-mail inválido.');
    // no teste, o vendedor é uma conta de teste e o Mercado Pago exige comprador de teste: usa um e-mail @testuser.com
    // (alguns e-mails inventados o Mercado Pago recusa com "User bad request"; este foi conferido em 08/11)
    if (mp_modo() === 'sandbox') $email = 'test_user_123456789@testuser.com';

    $comercio = $pedido['profissional_id'] ? (string)valor('SELECT nome FROM profissionais WHERE id = ?', [$pedido['profissional_id']]) : '';
    $a = mp_api('POST', '/preapproval', [
        'reason' => mb_substr('Acha Fácil - Plano ' . PLANOS_NOME[$plano] . ($comercio !== '' ? ' - ' . $comercio : ''), 0, 250),
        'external_reference' => 'pedido:' . $pedido['id'],
        'payer_email' => $email,
        'card_token_id' => $token,
        'auto_recurring' => ['frequency' => 1, 'frequency_type' => 'months', 'transaction_amount' => $valor, 'currency_id' => 'BRL'],
        'back_url' => url_publica('assinatura.php?c=' . $pedido['codigo']),
        'status' => 'authorized',
    ], ['X-Idempotency-Key: achafacil-cartao-' . $pedido['id'] . '-' . hash('sha256', $token)]);
    if (empty($a['id'])) throw new RuntimeException('O Mercado Pago não devolveu a assinatura.');
    // esta passa a ser a assinatura do pedido (uma tentativa antiga pelo Mercado Pago fica de lado)
    q('UPDATE pedidos SET mp_assinatura = ?, mp_status = ?, valor = ?, atualizado_em = NOW() WHERE id = ?',
        [$a['id'], ($a['status'] ?? '') === 'authorized' ? 'pending' : ($a['status'] ?? 'pending'), $valor, $pedido['id']]);
    return mp_conferir_assinatura((string)$a['id']) ?? $pedido;
}

/** Mensagem para o dono a partir de um erro do Mercado Pago no pagamento. */
function mp_erro_para_dono(Throwable $e): string {
    $m = $e->getMessage();
    if (str_contains($m, 'real or test users')) return 'Credencial de teste com comprador real (ou o contrário): confira o mp_modo e as chaves do Mercado Pago.';
    if (str_contains($m, 'Mercado Pago respondeu 4')) return 'O cartão não foi aceito. Confira os dados ou use outro cartão de crédito.';
    return 'Não foi possível falar com o Mercado Pago agora. Tente de novo em alguns minutos.';
}

/** Cria a cobrança única (Checkout do Mercado Pago) do plano Básico e devolve o endereço para o dono pagar. */
function mp_pagar_unico(array $pedido): string {
    if (!mp_pronto_unico()) throw new RuntimeException('Falta aplicar a mudança 011 do banco (bash servidor/instalar.sh).');
    $plano = (string)$pedido['plano'];
    $valor = PLANOS_PRECO[$plano] ?? throw new RuntimeException('Plano inválido.');
    $comercio = $pedido['profissional_id'] ? (string)valor('SELECT nome FROM profissionais WHERE id = ?', [$pedido['profissional_id']]) : '';
    $volta = url_publica('assinatura.php?c=' . $pedido['codigo']);
    $pref = mp_api('POST', '/checkout/preferences', [
        'items' => [[
            'id' => 'plano-' . $plano,
            'title' => mb_substr('Acha Fácil - Plano ' . PLANOS_NOME[$plano] . ($comercio !== '' ? ' - ' . $comercio : ''), 0, 250),
            'quantity' => 1, 'unit_price' => $valor, 'currency_id' => 'BRL',
        ]],
        // no teste, o Mercado Pago exige comprador de teste (mesmo e-mail usado na assinatura do Destaque)
        'payer' => ['email' => mp_modo() === 'sandbox' ? 'test_user_123456789@testuser.com' : $pedido['email']],
        'external_reference' => 'pedido:' . $pedido['id'],
        'back_urls' => ['success' => $volta, 'pending' => $volta, 'failure' => $volta],
        'auto_return' => 'approved',
        'notification_url' => url_publica('mp-webhook.php?source_news=webhooks'),
        'statement_descriptor' => 'ACHAFACIL',
    ], ['X-Idempotency-Key: achafacil-unico-' . $pedido['id'] . '-' . time()]);
    // sandbox_init_point só serve para token TEST-; com o token de um vendedor de teste (APP_USR-) é o init_point normal
    $endereco = (string)(str_starts_with(mp_token(), 'TEST-') ? ($pref['sandbox_init_point'] ?? $pref['init_point'] ?? '') : ($pref['init_point'] ?? ''));
    if ($endereco === '') throw new RuntimeException('O Mercado Pago não devolveu o endereço do pagamento.');
    q('UPDATE pedidos SET valor = ?, mp_status = IF(mp_status IS NULL, ?, mp_status), atualizado_em = NOW() WHERE id = ?', [$valor, 'pending', $pedido['id']]);
    return $endereco;
}

/**
 * Confere um pagamento direto na API. Só mexe se for o pagamento único do Básico (as mensalidades do Destaque chegam
 * pela assinatura): aprovado, em reais e no valor do plano = Básico ligado para sempre, uma vez só.
 */
function mp_conferir_pagamento(string $mp_id): ?array {
    if (!preg_match('/^\d{1,30}$/', $mp_id) || !mp_pronto_unico()) return null;
    $pg = mp_api('GET', '/v1/payments/' . $mp_id);
    if (!preg_match('/^pedido:(\d+)$/', (string)($pg['external_reference'] ?? ''), $m)) return null;
    $pedido = linha("SELECT * FROM pedidos WHERE id = ? AND tipo = 'assumir' AND plano IS NOT NULL", [(int)$m[1]]);
    if (!$pedido || !plano_unico((string)$pedido['plano'])) return $pedido;

    $status = (string)($pg['status'] ?? 'desconhecido');
    $valor = (float)($pg['transaction_amount'] ?? 0);
    mp_evento((int)$pedido['id'], 'pagamento', $mp_id, $status, $valor);
    if (pedido_pago($pedido)) return $pedido;   // já pago antes (aviso repetido ou outro pagamento do mesmo pedido)

    $certo = ($pg['currency_id'] ?? '') === 'BRL' && $valor + 0.009 >= (PLANOS_PRECO[$pedido['plano']] ?? INF);
    if ($status !== 'approved') {
        q('UPDATE pedidos SET mp_pagamento = ?, mp_status = ?, atualizado_em = NOW() WHERE id = ?', [$mp_id, $status, $pedido['id']]);
    } elseif (!$certo) {
        error_log("acha-facil mp: pagamento $mp_id com valor ou moeda diferente do plano (pedido {$pedido['id']})");
    } else {
        // só quem conseguir marcar o pedido como pago liga o plano (webhook e volta da página podem chegar juntos)
        $marcou = q("UPDATE pedidos SET mp_pagamento = ?, mp_status = 'approved', atualizado_em = NOW()
            WHERE id = ? AND (mp_status IS NULL OR mp_status NOT IN ('authorized','approved'))", [$mp_id, $pedido['id']])->rowCount();
        $pid = (int)($pedido['profissional_id'] ?? 0);
        if ($marcou === 1 && $pid) {
            q('UPDATE profissionais SET basico_pago_em = COALESCE(basico_pago_em, NOW()) WHERE id = ?', [$pid]);
            require_once __DIR__ . '/avisos.php';
            avisar_admins('Novo plano pago', $pedido['nome'] . ' pagou o plano ' . (PLANOS_NOME[$pedido['plano']] ?? '') . ' de '
                . (string)valor('SELECT nome FROM profissionais WHERE id = ?', [$pid]) . '.', ['pedido' => (int)$pedido['id']]);
        }
    }
    return linha('SELECT * FROM pedidos WHERE id = ?', [$pedido['id']]);
}

/** Registra um aviso do Mercado Pago (cada tipo + id + status uma vez só). */
function mp_evento(?int $pedido_id, string $tipo, string $mp_id, string $status, ?float $valor): void {
    q('INSERT IGNORE INTO eventos_mp (pedido_id, tipo, mp_id, status, valor) VALUES (?, ?, ?, ?, ?)', [$pedido_id, $tipo, $mp_id, $status, $valor]);
}

/** Confere uma assinatura direto na API e aplica: autorizada = plano ligado; pausada/cancelada = não renova. */
function mp_conferir_assinatura(string $mp_id): ?array {
    if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $mp_id)) return null;
    $a = mp_api('GET', '/preapproval/' . rawurlencode($mp_id));
    if (!preg_match('/^pedido:(\d+)$/', (string)($a['external_reference'] ?? ''), $m)) return null;
    $pedido = linha("SELECT * FROM pedidos WHERE id = ? AND tipo = 'assumir' AND plano IS NOT NULL", [(int)$m[1]]);
    if (!$pedido || plano_unico((string)$pedido['plano'])) return $pedido;   // o Básico não tem assinatura
    // uma assinatura antiga do mesmo pedido (ex.: a pessoa assinou de novo) não mexe na atual
    if ($pedido['mp_assinatura'] && $pedido['mp_assinatura'] !== $mp_id) return $pedido;

    $status = (string)($a['status'] ?? '');
    $valor = (float)($a['auto_recurring']['transaction_amount'] ?? 0);
    $certo = ($a['auto_recurring']['currency_id'] ?? '') === 'BRL' && $valor + 0.009 >= (PLANOS_PRECO[$pedido['plano']] ?? INF);
    q('UPDATE pedidos SET mp_assinatura = ?, mp_status = ?, atualizado_em = NOW() WHERE id = ?', [$mp_id, $status, $pedido['id']]);
    mp_evento((int)$pedido['id'], 'assinatura', $mp_id, $status, $valor);

    $pid = (int)($pedido['profissional_id'] ?? 0);
    if ($pid && $status === 'authorized' && $certo) {
        // liga o plano já; os primeiros 3 dias valem até a 1ª mensalidade ser confirmada (ela estende 1 mês)
        q("UPDATE profissionais SET plano = ?, mp_assinatura = ?, assinatura = 'ativa',
            assinatura_ate = GREATEST(COALESCE(assinatura_ate, CURDATE()), CURDATE() + INTERVAL 3 DAY) WHERE id = ?", [$pedido['plano'], $mp_id, $pid]);
        if ($pedido['mp_status'] !== 'authorized') {   // só na 1ª confirmação (webhook e volta da página chamam os dois)
            require_once __DIR__ . '/avisos.php';
            avisar_admins('Novo plano pago', $pedido['nome'] . ' assinou o plano ' . (PLANOS_NOME[$pedido['plano']] ?? '') . ' de '
                . (string)valor('SELECT nome FROM profissionais WHERE id = ?', [$pid]) . '.', ['pedido' => (int)$pedido['id']]);
        }
    } elseif ($pid && in_array($status, ['paused', 'cancelled'], true)) {
        // não renova mais; continua valendo até o fim do mês já pago
        q("UPDATE profissionais SET assinatura = 'cancelada' WHERE id = ? AND mp_assinatura = ? AND assinatura = 'ativa'", [$pid, $mp_id]);
    } elseif ($status === 'authorized' && !$certo) {
        error_log("acha-facil mp: assinatura $mp_id com valor ou moeda diferente do plano (pedido {$pedido['id']})");
    }
    return linha('SELECT * FROM pedidos WHERE id = ?', [$pedido['id']]);
}

/** Confere uma mensalidade (cobrança da assinatura) e, se paga, estende a assinatura do comércio em 1 mês, uma vez só. */
function mp_conferir_mensalidade(string $mp_id): void {
    if (!preg_match('/^\d{1,30}$/', $mp_id)) return;
    $c = mp_api('GET', '/authorized_payments/' . $mp_id);
    $assinatura = (string)($c['preapproval_id'] ?? '');
    if ($assinatura === '') return;
    $pedido = linha('SELECT * FROM pedidos WHERE mp_assinatura = ?', [$assinatura]);
    if (!$pedido) {
        mp_conferir_assinatura($assinatura);   // primeiro aviso desta assinatura: liga ao pedido
        $pedido = linha('SELECT * FROM pedidos WHERE mp_assinatura = ?', [$assinatura]);
        if (!$pedido) return;
    }
    $pago = ($c['payment']['status'] ?? '') === 'approved';
    $valor = (float)($c['transaction_amount'] ?? 0);
    $status = $pago ? 'approved' : (string)($c['payment']['status'] ?? $c['status'] ?? 'desconhecido');
    mp_evento((int)$pedido['id'], 'mensalidade', $mp_id, $status, $valor);
    if (!$pago) return;
    if ($valor + 0.009 < (PLANOS_PRECO[$pedido['plano']] ?? INF)) {
        error_log("acha-facil mp: mensalidade $mp_id paga com valor menor que o plano (pedido {$pedido['id']})");
        return;
    }
    // marca como aplicada; só quem conseguir marcar estende (o aviso pode chegar repetido)
    $aplicou = q("UPDATE eventos_mp SET aplicado = 1 WHERE tipo = 'mensalidade' AND mp_id = ? AND status = 'approved' AND aplicado = 0", [$mp_id])->rowCount();
    $pid = (int)($pedido['profissional_id'] ?? 0);
    if ($aplicou === 1 && $pid) {
        q("UPDATE profissionais SET plano = ?, mp_assinatura = ?, assinatura = IF(assinatura = 'cancelada', 'cancelada', 'ativa'),
            assinatura_ate = GREATEST(COALESCE(assinatura_ate, CURDATE()), CURDATE()) + INTERVAL 1 MONTH WHERE id = ?", [$pedido['plano'], $assinatura, $pid]);
    }
}

/** Assinatura (x-signature) dos avisos do Mercado Pago, quando a chave secreta está configurada. */
function mp_aviso_valido(string $data_id): bool {
    $segredo = trim((string)(config()['mp_webhook_secret'] ?? ''));
    if ($segredo === '') return true;
    $partes = [];
    foreach (explode(',', $_SERVER['HTTP_X_SIGNATURE'] ?? '') as $par) {
        [$k, $v] = array_pad(explode('=', trim($par), 2), 2, '');
        $partes[$k] = $v;
    }
    if (empty($partes['ts']) || empty($partes['v1'])) return false;
    $manifesto = 'id:' . strtolower($data_id) . ';request-id:' . ($_SERVER['HTTP_X_REQUEST_ID'] ?? '') . ';ts:' . $partes['ts'] . ';';
    return hash_equals(hash_hmac('sha256', $manifesto, $segredo), $partes['v1']);
}

function mp_status_nome(?string $s): string {
    return ['pending' => 'aguardando você concluir no Mercado Pago', 'authorized' => 'ativa', 'approved' => 'paga',
        'in_process' => 'em análise no Mercado Pago', 'rejected' => 'recusada', 'cancelled' => 'cancelada', 'paused' => 'pausada', 'cancelled' => 'cancelada'][$s ?? ''] ?? 'ainda não assinada';
}
