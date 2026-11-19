<?php
// Ofertas da semana (19/11, pedido do usuário; mudança 031). Benefício pago à parte de quem tem o Destaque em dia:
//  - o dono escreve a oferta no painel (/painel/oferta.php): título, texto, preço "de/por" e foto, todos menos o título opcionais;
//  - paga por oferta (pagamento único no Checkout do Mercado Pago, como o Básico): OFERTA_PRECO, ou 'preco_oferta' no config;
//  - a equipe confere em /admin/ofertas.php (regra do app: nada do dono vai ao ar sem conferência);
//  - aprovada, fica no ar pelos dias escolhidos (no máximo OFERTA_MAX_DIAS) na ficha e na página da cidade;
//  - recusada: o dono corrige e manda de novo sem pagar outra vez. Destaque vencido: a oferta some junto.
// Por que por oferta e não por semana: o dono só paga quando tem uma promoção (sem outra assinatura para cancelar) e o
// pagamento único já existe e funciona (Básico). Uma oferta no ar por vez em cada comércio.
declare(strict_types=1);
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/guia.php';

const OFERTA_PRECO = 9.90;
const OFERTA_MAX_DIAS = 7;
// 19/11 (pedido do usuário): tamanhos fixos para a oferta ficar bonita e fácil de ler em todo lugar (ficha, cidade, app)
const OFERTA_TITULO_MAX = 50;    // letras: cabe em 2 linhas no cartão do celular
const OFERTA_TEXTO_MAX = 250;
const OFERTA_FOTO_LADO = 800;    // foto sempre quadrada 800 x 800 (o servidor corta o meio se vier deitada ou em pé)
const OFERTA_FOTO_MIN = 400;
const OFERTA_STATUS = ['aguardando_pagamento' => 'Esperando o pagamento', 'em_analise' => 'Esperando a conferência',
    'no_ar' => 'No ar', 'recusada' => 'Recusada', 'cancelada' => 'Cancelada'];

function ofertas_prontas(): bool {
    static $ok = null;
    try { return $ok ??= (bool)valor("SELECT 1 FROM migracoes WHERE nome = '031-ofertas-relatorio.sql'"); } catch (PDOException) { return $ok = false; }
}
function oferta_preco(): float { return (float)(config()['preco_oferta'] ?? OFERTA_PRECO); }
function oferta_preco_texto(): string { return 'R$ ' . number_format(oferta_preco(), 2, ',', '.'); }
function dinheiro(?string $v): string { return $v === null ? '' : 'R$ ' . number_format((float)$v, 2, ',', '.'); }

/** Oferta no ar (aprovada, dentro do prazo) de um comércio com o Destaque em dia. */
function oferta_no_ar(int $profissional_id): ?array {
    if (!ofertas_prontas()) return null;
    return linha("SELECT o.* FROM ofertas o JOIN profissionais p ON p.id = o.profissional_id
        WHERE o.profissional_id = ? AND o.status = 'no_ar' AND o.fim > NOW() AND " . SQL_DESTAQUE . ' ORDER BY o.id DESC LIMIT 1', [$profissional_id]);
}

/** Ofertas no ar numa cidade (com os dados do comércio para o cartão), as que acabam antes primeiro. */
function ofertas_da_cidade(string $cidade, int $limite = 12): array {
    if (!ofertas_prontas()) return [];
    return linhas('SELECT o.id AS oferta_id, o.titulo AS oferta_titulo, o.preco_de AS oferta_de, o.preco_por AS oferta_por, o.foto AS oferta_foto,
        o.fim AS oferta_fim, ' . CAMPOS_RESUMO . " FROM ofertas o JOIN profissionais p ON p.id = o.profissional_id JOIN categorias c ON c.id = p.categoria_id
        WHERE o.status = 'no_ar' AND o.fim > NOW() AND " . visivel($cidade) . ' AND ' . SQL_DESTAQUE . " ORDER BY o.fim LIMIT $limite");
}

/** A oferta "em andamento" do dono: esperando pagamento, em análise, recusada (para corrigir) ou no ar. */
function oferta_do_dono(int $profissional_id): ?array {
    if (!ofertas_prontas()) return null;
    return linha("SELECT * FROM ofertas WHERE profissional_id = ? AND (status IN ('aguardando_pagamento','em_analise','recusada')
        OR (status = 'no_ar' AND fim > NOW())) ORDER BY id DESC LIMIT 1", [$profissional_id]);
}

/** "até sexta, 28/11" */
function oferta_validade(array $o): string {
    $fim = strtotime((string)($o['fim'] ?? $o['oferta_fim']));
    $dias = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
    return date('Y-m-d', $fim) === hoje() ? 'só hoje, até ' . date('H:i', $fim) : 'até ' . $dias[(int)date('w', $fim)] . ', ' . date('d/m', $fim);
}

/** Confere o que o dono mandou. Devolve [dados, erro]. */
function oferta_do_post(array $post): array {
    $t = fn(string $k, int $max) => ($v = trim(preg_replace('/[ \t]+/u', ' ', (string)($post[$k] ?? '')))) === '' ? null : mb_substr($v, 0, $max);
    $preco = function (string $k) use ($post): ?string {
        $v = trim(str_replace(['R$', ' '], '', (string)($post[$k] ?? '')));
        if ($v === '') return null;
        $v = str_contains($v, ',') ? str_replace(',', '.', str_replace('.', '', $v)) : $v;
        return is_numeric($v) && (float)$v > 0 && (float)$v < 1000000 ? number_format((float)$v, 2, '.', '') : 'erro';
    };
    $d = ['titulo' => $t('titulo', OFERTA_TITULO_MAX), 'texto' => $t('texto', OFERTA_TEXTO_MAX), 'preco_de' => $preco('preco_de'), 'preco_por' => $preco('preco_por'),
        'dias' => max(1, min(OFERTA_MAX_DIAS, (int)($post['dias'] ?? OFERTA_MAX_DIAS)))];
    if (!$d['titulo'] || mb_strlen($d['titulo']) < 4) return [$d, 'Escreva o título da oferta (ex.: "Pizza grande + refri por R$ 49,90").'];
    if ($d['preco_de'] === 'erro' || $d['preco_por'] === 'erro') return [$d, 'Preço inválido. Use só números, ex.: 49,90.'];
    if ($d['preco_de'] && !$d['preco_por']) return [$d, 'Se colocar o preço "de", coloque também o preço "por".'];
    if ($d['preco_de'] && (float)$d['preco_por'] >= (float)$d['preco_de']) return [$d, 'O preço "por" tem que ser menor que o preço "de".'];
    if (preg_match('#https?://|www\.#i', $d['titulo'] . ' ' . $d['texto'])) return [$d, 'Não coloque links na oferta: o botão do WhatsApp e do site já ficam na ficha.'];
    return [$d, null];
}

/** Foto da oferta: salva e corta o meio para ficar quadrada, 800 x 800. Foto pequena demais (menos de 400) não entra. */
function salvar_foto_oferta(array $f, int $profissional_id): string {
    $info = @getimagesize((string)($f['tmp_name'] ?? ''));
    if ($info && min($info[0], $info[1]) < OFERTA_FOTO_MIN)
        throw new RuntimeException('A foto é pequena demais (' . $info[0] . ' x ' . $info[1] . '). Use pelo menos ' . OFERTA_FOTO_MIN . ' x ' . OFERTA_FOTO_MIN . ' pixels.');
    require_once __DIR__ . '/moderacao.php';
    $nome = salvar_foto_enviada($f, $profissional_id, 2000);
    $arq = config()['dados_dir'] . '/fotos/' . $nome;
    $l = OFERTA_FOTO_LADO;
    exec('convert ' . escapeshellarg($arq) . ' -resize ' . escapeshellarg("{$l}x{$l}^") . ' -gravity center -extent ' . escapeshellarg("{$l}x{$l}")
        . ' -quality 84 ' . escapeshellarg('jpg:' . $arq) . ' 2>&1', $saida, $codigo);
    if ($codigo !== 0) { apagar_foto($nome); throw new RuntimeException('Não deu para preparar essa foto. Tente outra.'); }
    return $nome;
}

/** Ajuda e contador dos campos da oferta (painel do dono e admin). */
function oferta_campos_ajuda_js(): string {
    return '<script>document.querySelectorAll("[data-contar]").forEach(function(c){var s=document.createElement("small");s.className="contador-letras";'
        . 'c.insertAdjacentElement("afterend",s);function f(){var n=c.value.length,m=+c.getAttribute("maxlength");s.textContent=n+" de "+m+" letras"+(m-n<=5?" (quase no limite)":"");'
        . 's.classList.toggle("perto",m-n<=5);}c.addEventListener("input",f);f();});'
        . 'var fo=document.getElementById("foto"),pv=document.getElementById("foto-previa");if(fo&&pv)fo.addEventListener("change",function(){var a=fo.files&&fo.files[0];'
        . 'if(!a){pv.hidden=true;return;}var u=URL.createObjectURL(a),i=new Image();i.onload=function(){var m=Math.min(i.width,i.height);pv.hidden=false;pv.querySelector("img").src=u;'
        . 'pv.querySelector("small").textContent=i.width+" x "+i.height+" px"+(m<' . OFERTA_FOTO_MIN . '?" - pequena demais, use pelo menos ' . OFERTA_FOTO_MIN . ' x ' . OFERTA_FOTO_MIN . '":(i.width!==i.height?" - vamos cortar o meio, como na prévia":" - ótimo!"));};i.src=u;});</script>';
}

/** Checkout do Mercado Pago da oferta (pagamento único). Devolve o endereço para pagar. */
function mp_pagar_oferta(array $o): string {
    require_once __DIR__ . '/mercadopago.php';
    $comercio = (string)valor('SELECT nome FROM profissionais WHERE id = ?', [$o['profissional_id']]);
    require_once __DIR__ . '/relatorio-mensal.php';
    $u = $o['usuario_id'] ?: (contas_do_comercio((int)$o['profissional_id'])[0] ?? null);
    $email = (string)(($u ? valor('SELECT email FROM usuarios WHERE id = ?', [$u]) : null)
        ?: valor("SELECT email FROM pedidos WHERE profissional_id = ? AND email IS NOT NULL ORDER BY id DESC LIMIT 1", [$o['profissional_id']])
        ?: 'achafacilcontato@gmail.com');
    $volta = url_publica('painel/oferta.php');
    $pref = mp_api('POST', '/checkout/preferences', [
        'items' => [['id' => 'oferta', 'title' => mb_substr('Acha Fácil - Oferta da semana - ' . $comercio, 0, 250),
            'quantity' => 1, 'unit_price' => (float)$o['valor'], 'currency_id' => 'BRL']],
        'payer' => ['email' => mp_modo() === 'sandbox' ? 'test_user_123456789@testuser.com' : $email],
        'external_reference' => 'oferta:' . $o['id'],
        'back_urls' => ['success' => $volta, 'pending' => $volta, 'failure' => $volta],
        'auto_return' => 'approved',
        'notification_url' => url_publica('mp-webhook.php?source_news=webhooks'),
        'statement_descriptor' => 'ACHAFACIL',
    ], ['X-Idempotency-Key: achafacil-oferta-' . $o['id'] . '-' . time()]);
    $endereco = (string)(str_starts_with(mp_token(), 'TEST-') ? ($pref['sandbox_init_point'] ?? $pref['init_point'] ?? '') : ($pref['init_point'] ?? ''));
    if ($endereco === '') throw new RuntimeException('O Mercado Pago não devolveu o endereço do pagamento.');
    q("UPDATE ofertas SET mp_status = IF(mp_status IS NULL, 'pending', mp_status), atualizado_em = NOW() WHERE id = ?", [$o['id']]);
    return $endereco;
}

/**
 * Pagamento de oferta conferido na API (chamado por mp_conferir_pagamento quando a referência é "oferta:N").
 * Aprovado, em reais e no valor = a oferta vai para a conferência da equipe (uma vez só).
 */
function mp_conferir_oferta(string $mp_id, array $pg, int $oferta_id): void {
    $o = linha('SELECT * FROM ofertas WHERE id = ?', [$oferta_id]);
    if (!$o) return;
    $status = (string)($pg['status'] ?? 'desconhecido');
    $valor = (float)($pg['transaction_amount'] ?? 0);
    mp_evento(null, 'oferta', $mp_id, $status, $valor);
    if ($o['pago_em']) return;
    if ($status !== 'approved') {
        q('UPDATE ofertas SET mp_status = ?, atualizado_em = NOW() WHERE id = ? AND pago_em IS NULL', [$status, $o['id']]);
        return;
    }
    if (($pg['currency_id'] ?? '') !== 'BRL' || $valor + 0.009 < (float)$o['valor']) {
        error_log("acha-facil mp: pagamento $mp_id com valor ou moeda diferente da oferta {$o['id']}");
        return;
    }
    $marcou = q("UPDATE ofertas SET mp_pagamento = ?, mp_status = 'approved', pago_em = NOW(), atualizado_em = NOW(),
        status = IF(status = 'aguardando_pagamento', 'em_analise', status) WHERE id = ? AND pago_em IS NULL", [$mp_id, $o['id']])->rowCount();
    if ($marcou === 1) {
        require_once __DIR__ . '/avisos.php';
        avisar_admins('Oferta para conferir', (string)valor('SELECT nome FROM profissionais WHERE id = ?', [$o['profissional_id']])
            . ' pagou uma oferta: ' . $o['titulo'], ['oferta' => (int)$o['id']]);
    }
}

/** Avisa o dono no celular: a conta que criou a oferta ou, sem ela (entrou pelo link), as contas ligadas ao comércio. */
function oferta_avisar_dono(array $o, string $titulo, string $texto, array $dados): void {
    require_once __DIR__ . '/avisos.php';
    require_once __DIR__ . '/relatorio-mensal.php';   // contas_do_comercio()
    $contas = $o['usuario_id'] ? [(int)$o['usuario_id']] : contas_do_comercio((int)$o['profissional_id']);
    foreach (array_unique($contas) as $u) avisar_usuario($u, $titulo, $texto, $dados);
}

/** Aprova: fica no ar a partir de agora pelos dias escolhidos. */
function aprovar_oferta(int $id, int $admin_id): void {
    $o = linha("SELECT * FROM ofertas WHERE id = ? AND status = 'em_analise'", [$id]);
    if (!$o) throw new RuntimeException('Esta oferta não está esperando conferência.');
    // uma no ar por vez: a anterior (se houver) termina agora
    q("UPDATE ofertas SET status = 'cancelada', fim = NOW(), atualizado_em = NOW() WHERE profissional_id = ? AND status = 'no_ar' AND id <> ?", [$o['profissional_id'], $id]);
    q("UPDATE ofertas SET status = 'no_ar', inicio = NOW(), fim = NOW() + INTERVAL dias DAY, admin_id = ?, motivo = NULL, atualizado_em = NOW() WHERE id = ?", [$admin_id, $id]);
    require_once __DIR__ . '/moderacao.php';
    registrar($admin_id, 'aprovou_oferta', (int)$o['profissional_id'], $o['titulo']);
    web_cache_limpar();
    $o = linha('SELECT * FROM ofertas WHERE id = ?', [$id]);
    oferta_avisar_dono($o, '🏷️ Sua oferta está no ar!',
        $o['titulo'] . ' — aparece na sua ficha e na página da cidade ' . oferta_validade($o) . '.', ['tela' => 'profissional', 'id' => (int)$o['profissional_id']]);
}

/**
 * 19/11 (pedido do usuário): admin põe no ar SEM pagamento (teste ou cortesia). Vale para oferta esperando pagamento,
 * em análise ou recusada. Fica registrada como valor 0 e mp_status 'cortesia'.
 */
function liberar_oferta_sem_pagamento(int $id, int $admin_id): void {
    $o = linha("SELECT * FROM ofertas WHERE id = ? AND status IN ('aguardando_pagamento','em_analise','recusada')", [$id]);
    if (!$o) throw new RuntimeException('Esta oferta não pode ser liberada (já está no ar ou foi cancelada).');
    q("UPDATE ofertas SET status = 'em_analise', valor = 0, mp_status = 'cortesia', pago_em = COALESCE(pago_em, NOW()), atualizado_em = NOW() WHERE id = ?", [$id]);
    require_once __DIR__ . '/moderacao.php';
    registrar($admin_id, 'liberou_oferta_sem_pagar', (int)$o['profissional_id'], $o['titulo']);
    aprovar_oferta($id, $admin_id);
}

/** 19/11 (pedido do usuário): admin ajusta a oferta direto (qualquer situação menos cancelada). No ar, mudar os dias refaz o fim. */
function editar_oferta_admin(int $id, int $admin_id, array $d, ?string $foto_nova, bool $tirar_foto): void {
    $o = linha("SELECT * FROM ofertas WHERE id = ? AND status <> 'cancelada'", [$id]);
    if (!$o) throw new RuntimeException('Oferta não encontrada (ou já cancelada).');
    $campos = ['titulo' => $d['titulo'], 'texto' => $d['texto'], 'preco_de' => $d['preco_de'], 'preco_por' => $d['preco_por'], 'dias' => $d['dias']];
    if ($foto_nova) $campos['foto'] = $foto_nova;
    elseif ($tirar_foto) $campos['foto'] = null;
    $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($campos)));
    q("UPDATE ofertas SET $sets, atualizado_em = NOW() WHERE id = ?", [...array_values($campos), $id]);
    if ($o['status'] === 'no_ar' && $o['inicio']) q('UPDATE ofertas SET fim = inicio + INTERVAL dias DAY WHERE id = ?', [$id]);
    require_once __DIR__ . '/moderacao.php';
    if (array_key_exists('foto', $campos) && $o['foto'] && $o['foto'] !== $campos['foto']) apagar_foto($o['foto']);
    registrar($admin_id, 'editou_oferta', (int)$o['profissional_id'], $d['titulo']);
    web_cache_limpar();
}

/** Recusa com motivo: o dono corrige no painel e manda de novo sem pagar outra vez. */
function recusar_oferta(int $id, int $admin_id, string $motivo): void {
    $o = linha("SELECT * FROM ofertas WHERE id = ? AND status = 'em_analise'", [$id]);
    if (!$o) throw new RuntimeException('Esta oferta não está esperando conferência.');
    $motivo = trim($motivo);
    if (mb_strlen($motivo) < 5) throw new RuntimeException('Escreva o motivo para o dono saber o que corrigir.');
    q("UPDATE ofertas SET status = 'recusada', motivo = ?, admin_id = ?, atualizado_em = NOW() WHERE id = ?", [mb_substr($motivo, 0, 300), $admin_id, $id]);
    require_once __DIR__ . '/moderacao.php';
    registrar($admin_id, 'recusou_oferta', (int)$o['profissional_id'], $o['titulo'] . ': ' . $motivo);
    oferta_avisar_dono($o, 'Sua oferta precisa de um ajuste',
        'Motivo: ' . mb_substr($motivo, 0, 120) . '. Corrija no painel e mande de novo (sem pagar outra vez).', ['tela' => 'inicio']);
}

/** Encerra antes do prazo (dono ou admin). */
function encerrar_oferta(int $id, ?int $admin_id = null): void {
    q("UPDATE ofertas SET status = 'cancelada', fim = LEAST(COALESCE(fim, NOW()), NOW()), atualizado_em = NOW() WHERE id = ? AND status = 'no_ar'", [$id]);
    if ($admin_id) { require_once __DIR__ . '/moderacao.php'; registrar($admin_id, 'encerrou_oferta', (int)valor('SELECT profissional_id FROM ofertas WHERE id = ?', [$id])); }
    web_cache_limpar();
}

/** Preço "de R$ 59,90 por R$ 49,90" em HTML. */
function oferta_precos_html(?string $de, ?string $por): string {
    if (!$por) return '';
    return '<p class="oferta-precos">' . ($de ? '<s>' . h(dinheiro($de)) . '</s> ' : '') . '<b>' . h(dinheiro($por)) . '</b></p>';
}

/** Quadro da oferta na ficha do site. */
function oferta_quadro_ficha(array $o): string {
    return '<section class="oferta-ficha" aria-label="Oferta da semana">'
        . ($o['foto'] ? '<img src="' . h(foto_url($o['foto'])) . '" alt="" loading="lazy">' : '')
        . '<div><p class="oferta-selo">🏷️ Oferta da semana</p>'   // 19/11 (pedido do usuário): sem "até quinta, 26/11"; o prazo da promoção o dono põe no texto ou na foto
        . '<h2>' . h($o['titulo']) . '</h2>'
        . ($o['texto'] ? '<p>' . nl2br(h($o['texto'])) . '</p>' : '')
        . oferta_precos_html($o['preco_de'], $o['preco_por']) . '</div></section>';
}

/** Para o app (API): a oferta no ar da ficha, ou null. */
function oferta_api(?array $o): ?array {
    if (!$o) return null;
    return ['titulo' => $o['titulo'], 'texto' => $o['texto'], 'precoDe' => $o['preco_de'] !== null ? (float)$o['preco_de'] : null,
        'precoPor' => $o['preco_por'] !== null ? (float)$o['preco_por'] : null, 'foto' => foto_url($o['foto']),
        // 19/11: sem a data (é o prazo no ar, não o da promoção); apps até 1.12.0 mostram "Oferta da semana · aproveite!"
        'validade' => 'aproveite!', 'fim' => $o['fim']];
}
