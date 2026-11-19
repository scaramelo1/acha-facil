<?php
// Números das fichas (passo 20): visualizações com origem e pessoas diferentes por dia, aparições em listas e toques
// em WhatsApp/ligar/mapa. Vale para TODAS as fichas (com ou sem plano): é o dado da abordagem comercial.
// Tabelas: contatos (mudança 002/017), vistas e aparicoes (mudança 017).
declare(strict_types=1);

/** De onde a pessoa abriu a ficha (o app manda ?origem=; o site manda 'web'). */
const ORIGENS = [
    'busca' => 'Busca', 'categoria' => 'Categoria', 'perto' => 'Perto de você', 'destaque' => 'Destaque da tela inicial',
    'inicio' => 'Recomendados da tela inicial', 'favoritos' => 'Favoritos', 'link' => 'Link ou aviso',
    // site (passo 38, 14/11): de onde a pessoa chegou ao site; antes de 14/11 tudo do site era 'web'
    'google' => 'Site: veio do Google', 'buscadores' => 'Site: outro buscador (Bing...)', 'redes' => 'Site: redes sociais e WhatsApp',
    'outros_sites' => 'Site: link de outro site', 'direto' => 'Site: endereço digitado ou favorito',
    'web' => 'Site (sem origem, antes de 14/11)',
    'app' => 'App (versão antiga, sem origem)',
];
/** Origens que são do site (para somar "Site" e separar do app). */
const ORIGENS_SITE = ['google', 'buscadores', 'redes', 'outros_sites', 'direto', 'web'];
/** Tipos de página por onde se entra no site. */
const PAGINAS_ENTRADA = ['inicio' => 'Página inicial', 'cidade' => 'Cidade', 'lista' => 'Área ou tipo', 'ficha' => 'Ficha de comércio',
    'eventos' => 'Agenda de eventos', 'evento' => 'Evento', 'busca' => 'Busca', 'outra' => 'Outra página'];
/** Onde a ficha foi mostrada numa lista. */
const ONDE_APARICAO = ['inicio' => 'Tela inicial', 'categoria' => 'Categoria', 'busca' => 'Busca', 'perto' => 'Perto de você'];
const TIPOS_CONTATO = ['whatsapp' => 'WhatsApp', 'ligar' => 'Ligar', 'mapa' => 'Mapa', 'instagram' => 'Instagram', 'site' => 'Site',
    'compartilhar' => 'Compartilhar no WhatsApp'];   // 19/11 (mudança 032): compartilhar não entra no total de contatos
/** Numa lista longa (área com 700 comércios) só contam as primeiras posições, que a pessoa de fato vê. */
const APARICAO_MAX = 40;

function origem_valida(?string $o, string $padrao = 'app'): string {
    return $o !== null && isset(ORIGENS[$o]) ? $o : $padrao;
}

/** Robôs de busca, prévias de link e ferramentas não contam. */
function e_robo(): bool {
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp\/|curl|wget|python|httpclient|headless|lighthouse|'
        . 'mediapartners|adsbot|googleother|google-|playstore-google|privacy preserving prefetch/i', $ua)) return true;
    // 16/11: robô do AdSense que se passa por Chrome no Windows, vindo dos servidores do Google
    // (rate-limited-proxy-74-125-*.google.com), sem Referer. Contava como "entrou direto" e inflou o dia 15/11.
    return empty($_SERVER['HTTP_REFERER']) && preg_match('/^(74\.125\.(20[89]|21\d)|66\.249\.(6[4-9]|[7-9]\d))\./', ip()) === 1;
}

// ---------- de onde a pessoa chegou ao site (passo 38) ----------

/** Origem pelo endereço de onde veio (Referer). null = veio do próprio site ou sem Referer. */
function origem_do_referer(?string $ref = null): ?string {
    $ref ??= (string)($_SERVER['HTTP_REFERER'] ?? '');
    if ($ref === '') return null;
    $host = strtolower((string)parse_url($ref, PHP_URL_HOST));
    if ($host === '') return null;
    $proprio = strtolower((string)preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    if ($host === $proprio || preg_match('/(^|\.)(achafacilapp\.com\.br|fsadesigners\.com\.br)$/', $host)) return null;
    // o app do Google no Android manda android-app://com.google.android.googlequicksearchbox
    if (preg_match('/(^|\.)google\.|googlequicksearchbox|^com\.google\./', $host)) return 'google';
    if (preg_match('/(^|\.)(bing|yahoo|duckduckgo|yandex|ecosia|baidu|qwant)\.|search\.brave\.com$|(^|\.)msn\.com$/', $host)) return 'buscadores';
    if (preg_match('/(^|\.)(facebook|fb|instagram|whatsapp|wa|t|twitter|x|tiktok|linkedin|youtube|pinterest|kwai|threads|telegram|messenger)\.(com|me|net|co|org)$|^com\.(instagram|facebook|whatsapp|zhiliaoapp|twitter|linkedin|google\.android\.youtube)/', $host)) return 'redes';
    return 'outros_sites';
}

/**
 * Chamada no começo de cada página pública (antes de qualquer saída). Se a pessoa acabou de CHEGAR ao site,
 * guarda a origem num cookie de 30 min (renovado a cada página) e conta 1 entrada no fim, se a página existir (200).
 * Devolve a origem da visita atual: a do Referer de fora, a do cookie ou 'direto'.
 */
/**
 * Pré-carregamento do Chrome (passo 55): o Chrome baixa a página antes do clique, quando o site aparece bem colocado no
 * Google. Vem com o cabeçalho Sec-Purpose/Purpose "prefetch" ou pelo servidor do Google *.fetch.tunnel.googlezip.net
 * (endereços 162.120., 193.186. e 72.14.; o nome só é conferido nesses, para não atrasar ninguém).
 */
function e_pre_carregamento(?string $ip = null, ?string $proposito = null): bool {
    $proposito ??= (string)($_SERVER['HTTP_SEC_PURPOSE'] ?? $_SERVER['HTTP_PURPOSE'] ?? $_SERVER['HTTP_X_PURPOSE'] ?? '');
    if (stripos($proposito, 'prefetch') !== false || stripos($proposito, 'preview') !== false) return true;
    $ip ??= (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (!preg_match('/^(162\.120\.|193\.186\.|72\.14\.)/', $ip)) return false;
    static $nomes = [];
    return $nomes[$ip] ??= str_ends_with((string)@gethostbyaddr($ip), '.googlezip.net');
}

/** A mudança 027 (marca de pré-carregamento) já foi aplicada? */
function pre_carregamento_pronto(): bool {
    static $ok = null;
    try { return $ok ??= (bool)valor("SELECT 1 FROM migracoes WHERE nome = '027-entradas-pre-carregamento.sql'"); } catch (PDOException) { return $ok = false; }
}

function registrar_entrada_site(string $pagina, ?string $cidade = null): string {
    static $origem = null;
    if ($origem !== null) return $origem;
    $de_fora = origem_do_referer();
    $cookie = (string)($_COOKIE['af_origem'] ?? '');
    $cookie = isset(ORIGENS[$cookie]) && in_array($cookie, ORIGENS_SITE, true) ? $cookie : '';
    $origem = $de_fora ?? ($cookie ?: 'direto');
    if (e_robo()) return $origem;
    // entrada nova = veio de fora (e não é recarga da mesma entrada) ou chegou sem Referer e sem cookie
    $entrada = $de_fora !== null ? $de_fora !== $cookie : $cookie === '';
    if (!headers_sent()) setcookie('af_origem', $origem, ['expires' => time() + 1800, 'path' => '/', 'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true]);
    if ($entrada) {
        $caminho = mb_substr((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), 0, 190);
        register_shutdown_function(function () use ($origem, $caminho, $pagina, $cidade) {
            if (http_response_code() !== 200) return;   // 404 e redirecionamento não contam
            try {
                q('INSERT INTO entradas (dia, origem, caminho, pagina, cidade, total) VALUES (CURDATE(), ?, ?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE total = total + 1', [$origem, rawurldecode($caminho), $pagina, $cidade]);
            } catch (PDOException) {}   // mudança 022 ainda não aplicada: só não conta
            try {   // data e hora de cada entrada (mudança 023, passo 42) e se foi pré-carregamento (027, passo 55)
                if (pre_carregamento_pronto()) q('INSERT INTO entradas_hora (quando, origem, caminho, pagina, cidade, pre) VALUES (NOW(), ?, ?, ?, ?, ?)',
                    [$origem, rawurldecode($caminho), $pagina, $cidade, e_pre_carregamento() ? 1 : 0]);
                else q('INSERT INTO entradas_hora (quando, origem, caminho, pagina, cidade) VALUES (NOW(), ?, ?, ?, ?)',
                    [$origem, rawurldecode($caminho), $pagina, $cidade]);
            } catch (PDOException) {}
        });
    }
    return $origem;
}

/** A mudança 022 (entradas no site) já foi aplicada? */
function entradas_prontas(): bool {
    static $ok = null;
    try { return $ok ??= (bool)valor("SELECT 1 FROM migracoes WHERE nome = '022-entradas-site.sql'"); } catch (PDOException) { return $ok = false; }
}

/** Sal do embaralhamento: 'sal_visitas' no config ou, se não houver, derivado da senha do banco (nunca sai do servidor). */
function sal_visitas(): string {
    $c = config();
    return (string)($c['sal_visitas'] ?? hash('sha256', 'acha-facil-visitas|' . ($c['db_senha'] ?? '')));
}

/**
 * Quem está vendo, embaralhado (16 bytes). O app manda um código aleatório criado no celular (cabeçalho X-Visitante),
 * sem ligação com a conta; sem ele (app antigo, site) usa IP + navegador do dia. O código em si nunca é guardado.
 */
function visitante(): string {
    $cod = (string)($_SERVER['HTTP_X_VISITANTE'] ?? '');
    $base = preg_match('/^[A-Za-z0-9-]{16,64}$/', $cod) ? "app:$cod" : 'ip:' . ip() . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . hoje();
    return substr(hash_hmac('sha256', $base, sal_visitas(), true), 0, 16);
}

/**
 * Ficha aberta. Conta 1 visualização, e 1 pessoa se for a 1ª vez dela no dia; quem reabre em menos de 30 min não conta de novo.
 */
function contar_visita(int $profissional_id, string $origem): void {
    if (e_robo()) return;
    try { contar_visita_017($profissional_id, $origem); }
    catch (PDOException $e) { contar_sem_017($profissional_id, 'perfil', $e); }   // contar nunca derruba a resposta
}
function contar_visita_017(int $profissional_id, string $origem): void {
    $n = q('INSERT INTO vistas (profissional_id, dia, visitante, ultima) VALUES (?, CURDATE(), ?, NOW())
        ON DUPLICATE KEY UPDATE ultima = IF(ultima < NOW() - INTERVAL 30 MINUTE, NOW(), ultima)',
        [$profissional_id, visitante()])->rowCount();   // 1 = 1ª vez hoje, 2 = voltou depois de 30 min, 0 = repetida
    if ($n === 0) return;
    $pessoa = $n === 1 ? 1 : 0;
    q("INSERT INTO contatos (profissional_id, tipo, dia, origem, total, pessoas) VALUES (?, 'perfil', CURDATE(), ?, 1, ?)
        ON DUPLICATE KEY UPDATE total = total + 1, pessoas = pessoas + ?", [$profissional_id, origem_valida($origem), $pessoa, $pessoa]);
    contar_hora($profissional_id, 'perfil', origem_valida($origem), $pessoa);
    // de vez em quando apaga o que não serve mais (só o dia de hoje importa para separar as pessoas)
    if (random_int(1, 300) === 1) q('DELETE FROM vistas WHERE dia < CURDATE() - INTERVAL 1 DAY');
}

/** Toque em WhatsApp, ligar, Instagram, mapa ou site. */
function contar_contato(int $profissional_id, string $tipo, string $origem = 'app'): void {
    if (!isset(TIPOS_CONTATO[$tipo]) || e_robo()) return;
    try {
        q('INSERT INTO contatos (profissional_id, tipo, dia, origem, total) VALUES (?, ?, CURDATE(), ?, 1) ON DUPLICATE KEY UPDATE total = total + 1',
            [$profissional_id, $tipo, in_array($origem, ORIGENS_SITE, true) ? $origem : 'app']);
        contar_hora($profissional_id, $tipo, in_array($origem, ORIGENS_SITE, true) ? $origem : 'app');
    } catch (PDOException $e) { contar_sem_017($profissional_id, $tipo, $e); }
}

/** 19/11 (passo 112, mudança 034): a hora de cada visualização e toque, para a tabela "Por hora" do relatório do dia. */
function contatos_hora_prontos(): bool {
    static $ok = null;
    if ($ok === null) {
        try { $ok = (bool)valor("SELECT 1 FROM migracoes WHERE nome = '034-contatos-hora.sql'"); }
        catch (PDOException) { $ok = false; }
    }
    return $ok;
}
function contar_hora(int $profissional_id, string $tipo, string $origem, int $pessoa = 0): void {
    if (!contatos_hora_prontos()) return;
    try {
        q('INSERT INTO contatos_hora (profissional_id, tipo, origem, pessoa) VALUES (?, ?, ?, ?)', [$profissional_id, $tipo, $origem, $pessoa]);
    } catch (PDOException $e) { error_log('Acha Fácil contatos_hora: ' . $e->getMessage()); }
}

/** Banco ainda sem a mudança 017 (ou falha qualquer): conta do jeito antigo, sem origem nem pessoas, e anota no log. */
function contar_sem_017(int $profissional_id, string $tipo, Throwable $e): void {
    error_log('Acha Fácil números: ' . $e->getMessage());
    try {
        q('INSERT INTO contatos (profissional_id, tipo, dia, total) VALUES (?,?,CURDATE(),1) ON DUPLICATE KEY UPDATE total = total + 1', [$profissional_id, $tipo]);
    } catch (PDOException) {}
}

/** Fichas mostradas numa lista (resumos com 'id'): uma gravação só para a lista toda. */
function contar_aparicoes(array $lista, string $onde): void {
    if (!isset(ONDE_APARICAO[$onde]) || e_robo()) return;
    $ids = array_values(array_unique(array_map(fn($p) => (int)$p['id'], array_slice($lista, 0, APARICAO_MAX))));
    if (!$ids) return;
    $params = [];
    foreach ($ids as $id) array_push($params, $id, $onde);
    try {
        q('INSERT INTO aparicoes (profissional_id, dia, onde, total) VALUES ' . implode(',', array_fill(0, count($ids), '(?, CURDATE(), ?, 1)'))
            . ' ON DUPLICATE KEY UPDATE total = total + 1', $params);
    } catch (PDOException $e) { error_log('Acha Fácil aparições: ' . $e->getMessage()); }
}

// ---------- leitura (admin, relatório e painel do dono) ----------

/** A mudança 017 já foi aplicada? (antes disso as páginas de números não abrem e o painel esconde os números) */
function numeros_prontos(): bool {
    static $ok = null;
    try { return $ok ??= (bool)valor("SELECT 1 FROM migracoes WHERE nome = '017-visualizacoes.sql'"); } catch (PDOException) { return $ok = false; }
}
function exigir_numeros_prontos(): void {
    if (numeros_prontos()) return;
    http_response_code(503);
    exit('Os números ainda não estão ligados: falta aplicar a mudança 017 (bash servidor/instalar.sh, como root).');
}

/** Data inicial de um período de N dias que termina hoje. */
function desde_dias(int $dias): string { return date('Y-m-d', strtotime('-' . max(0, $dias - 1) . ' days')); }

/** Números de uma ficha entre $desde e $ate (inclusive). */
function numeros_ficha(int $id, string $desde, ?string $ate = null): array {
    $ate ??= hoje();
    $r = ['aparicoes' => 0, 'visualizacoes' => 0, 'pessoas' => 0, 'contatos' => 0,
          'por_tipo' => array_fill_keys(array_keys(TIPOS_CONTATO), 0), 'origens' => [], 'onde' => [], 'por_dia' => []];
    foreach (linhas('SELECT tipo, origem, dia, total, pessoas FROM contatos WHERE profissional_id = ? AND dia BETWEEN ? AND ?', [$id, $desde, $ate]) as $l) {
        $dia = &$r['por_dia'][$l['dia']];
        $dia ??= ['aparicoes' => 0, 'visualizacoes' => 0, 'pessoas' => 0, 'contatos' => 0];
        if ($l['tipo'] === 'perfil') {
            $r['visualizacoes'] += (int)$l['total'];
            $r['pessoas'] += (int)$l['pessoas'];
            $r['origens'][$l['origem']] = ($r['origens'][$l['origem']] ?? 0) + (int)$l['total'];
            $dia['visualizacoes'] += (int)$l['total'];
            $dia['pessoas'] += (int)$l['pessoas'];
        } else {
            if ($l['tipo'] !== 'compartilhar') $r['contatos'] += (int)$l['total'];
            $r['por_tipo'][$l['tipo']] = ($r['por_tipo'][$l['tipo']] ?? 0) + (int)$l['total'];
            if ($l['tipo'] !== 'compartilhar') $dia['contatos'] += (int)$l['total'];
        }
        unset($dia);
    }
    foreach (linhas('SELECT onde, dia, total FROM aparicoes WHERE profissional_id = ? AND dia BETWEEN ? AND ?', [$id, $desde, $ate]) as $l) {
        $r['aparicoes'] += (int)$l['total'];
        $r['onde'][$l['onde']] = ($r['onde'][$l['onde']] ?? 0) + (int)$l['total'];
        $r['por_dia'][$l['dia']]['aparicoes'] = ($r['por_dia'][$l['dia']]['aparicoes'] ?? 0) + (int)$l['total'];
    }
    arsort($r['origens']);
    arsort($r['onde']);
    // todos os dias do período, mesmo os sem nada
    $dias = [];
    for ($d = $desde; $d <= $ate; $d = date('Y-m-d', strtotime("$d +1 day")))
        $dias[$d] = ($r['por_dia'][$d] ?? []) + ['aparicoes' => 0, 'visualizacoes' => 0, 'pessoas' => 0, 'contatos' => 0];
    $r['por_dia'] = $dias;
    return $r;
}

/**
 * Posição da ficha em visualizações entre os comércios do mesmo tipo (ou da mesma área, se o tipo tiver menos de 3)
 * na mesma cidade, no período. null quando a ficha não teve visualização.
 */
function posicao_ficha(array $p, string $desde, string $ate): ?array {
    $vis = (int)valor("SELECT COALESCE(SUM(total), 0) FROM contatos WHERE profissional_id = ? AND tipo = 'perfil' AND dia BETWEEN ? AND ?", [$p['id'], $desde, $ate]);
    if ($vis === 0) return null;
    $grupo_sql = 'p.tipo = ?'; $grupo_val = $p['tipo']; $grupo_nome = $p['tipo'];
    $tam = $p['tipo'] ? (int)valor("SELECT COUNT(*) FROM profissionais p WHERE p.status = 'ativo' AND p.cidade = ? AND p.tipo = ?", [$p['cidade'], $p['tipo']]) : 0;
    if ($tam < 3) {
        $grupo_sql = 'p.categoria_id = ?'; $grupo_val = $p['categoria_id']; $grupo_nome = $p['categoria'] ?? 'área';
        $tam = (int)valor("SELECT COUNT(*) FROM profissionais p WHERE p.status = 'ativo' AND p.cidade = ? AND p.categoria_id = ?", [$p['cidade'], $p['categoria_id']]);
    }
    $acima = (int)valor("SELECT COUNT(*) FROM (SELECT k.profissional_id FROM contatos k JOIN profissionais p ON p.id = k.profissional_id
        WHERE k.tipo = 'perfil' AND k.dia BETWEEN ? AND ? AND p.status = 'ativo' AND p.cidade = ? AND $grupo_sql
        GROUP BY k.profissional_id HAVING SUM(k.total) > ?) t", [$desde, $ate, $p['cidade'], $grupo_val, $vis]);
    return ['posicao' => $acima + 1, 'de' => $tam, 'grupo' => $grupo_nome];
}

/** "1.234" */
function num(int|float $n): string { return number_format((float)$n, 0, ',', '.'); }
/** 12,5% (ou — sem base) */
function pct(int $parte, int $total): string { return $total > 0 ? number_format(100 * $parte / $total, 1, ',', '.') . '%' : '—'; }

// ---------- pedidos de informação (passo 59) ----------
// Botões nas fichas incompletas: "Quero saber o horário", "Quero ver fotos"... Cada pessoa conta 1 vez por dia, por tipo.
const TIPOS_PEDIDO_INFO = ['horario' => 'Horário', 'fotos' => 'Fotos', 'sobre' => 'O que faz ou vende', 'whatsapp' => 'WhatsApp'];

/** A mudança 028 já foi aplicada? Antes disso os botões aparecem, mas o pedido não é guardado. */
function pedidos_info_prontos(): bool {
    static $ok = null;
    try { return $ok ??= (bool)valor("SELECT 1 FROM migracoes WHERE nome = '028-pedidos-info.sql'"); } catch (PDOException) { return $ok = false; }
}

/** A mudança 030 (quem pediu) já foi aplicada? Antes dela só a contagem é guardada. */
function pedidos_info_pessoas_prontos(): bool {
    static $ok = null;
    try { return $ok ??= (bool)valor("SELECT 1 FROM migracoes WHERE nome = '030-pedidos-info-pessoas.sql'"); } catch (PDOException) { return $ok = false; }
}

/**
 * Guarda o pedido. true = contou (1ª vez da pessoa hoje), false = repetido, robô ou banco sem a 028.
 * $usuario_id: conta de quem pediu (app ou site), ou null = anônimo (passo 68, com a 030).
 */
function pedir_info(int $profissional_id, string $tipo, string $origem, ?int $usuario_id = null): bool {
    if (!isset(TIPOS_PEDIDO_INFO[$tipo]) || e_robo() || !pedidos_info_prontos()) return false;
    try {
        $novo = q('INSERT IGNORE INTO pedidos_info_vistos (profissional_id, tipo, dia, visitante) VALUES (?, ?, CURDATE(), ?)',
            [$profissional_id, $tipo, visitante()])->rowCount() === 1;
        if (!$novo) return false;
        q('INSERT INTO pedidos_info (profissional_id, tipo, dia, origem, total) VALUES (?, ?, CURDATE(), ?, 1)
            ON DUPLICATE KEY UPDATE total = total + 1', [$profissional_id, $tipo, $origem === 'web' ? 'web' : 'app']);
        if (pedidos_info_pessoas_prontos()) q('INSERT INTO pedidos_info_pessoas (profissional_id, tipo, usuario_id, origem) VALUES (?, ?, ?, ?)',
            [$profissional_id, $tipo, $usuario_id, $origem === 'web' ? 'web' : 'app']);
        if (random_int(1, 300) === 1) q('DELETE FROM pedidos_info_vistos WHERE dia < CURDATE() - INTERVAL 1 DAY');
        return true;
    } catch (PDOException $e) {
        error_log('Acha Fácil pedidos de info: ' . $e->getMessage());
        return false;
    }
}

/** ['horario' => 3, 'fotos' => 1, ...] de uma ficha no período (sem a 028: tudo zero). */
function pedidos_info_ficha(int $id, string $desde, ?string $ate = null): array {
    $r = array_fill_keys(array_keys(TIPOS_PEDIDO_INFO), 0);
    if (!pedidos_info_prontos()) return $r;
    foreach (linhas('SELECT tipo, SUM(total) AS t FROM pedidos_info WHERE profissional_id = ? AND dia BETWEEN ? AND ? GROUP BY tipo',
        [$id, $desde, $ate ?? hoje()]) as $l) $r[$l['tipo']] = (int)$l['t'];
    return $r;
}

/** "3 pessoas pediram o seu horário" (relatório para o comerciante e painel do dono). */
function pedido_info_frase(string $tipo, int $total): string {
    $o = ['horario' => 'o seu horário', 'fotos' => 'para ver fotos', 'sobre' => 'para saber o que você vende', 'whatsapp' => 'o seu WhatsApp'][$tipo] ?? $tipo;
    return num($total) . ($total > 1 ? ' pessoas pediram ' : ' pessoa pediu ') . $o;
}

// ---------- pré-carregamento que a pessoa abriu de verdade (16/11) ----------
// O Chrome baixa a ficha antes do clique (pré-carregamento). Nessa hora a visita NÃO conta: a página leva um sinal
// (visita_adiada_js) que só roda quando a pessoa abre a página; aí api/visita.php conta a visita e marca a entrada
// como aberta (entradas_hora.pre = 2: pré-carregada e vista; 1 = só baixada; 0 = clique normal).

function visita_adiada_sinal(int $id, string $origem): string {
    return substr(hash_hmac('sha256', "$id|$origem|" . hoje(), sal_visitas()), 0, 24);
}
/** <script> que avisa o servidor quando a ficha pré-carregada é aberta de verdade (vazio se a visita já contou). */
function visita_adiada_js(int $id, string $caminho): string {
    $origem = $GLOBALS['visita_adiada'] ?? null;
    if ($origem === null) return '';
    $dados = json_encode(['id' => $id, 'origem' => $origem, 'sinal' => visita_adiada_sinal($id, $origem), 'caminho' => $caminho]);
    return '<script>(function(){var d=' . $dados . ';function contar(){try{fetch(' . json_encode(url('api/visita.php'))
        . ',{method:"POST",keepalive:true,headers:{"Content-Type":"application/json"},body:JSON.stringify(d)});}catch(e){}}'
        . 'if(document.prerendering){document.addEventListener("prerenderingchange",contar,{once:true});}else{contar();}})();</script>';
}
/** A pessoa abriu a ficha pré-carregada: conta a visita e marca a entrada mais recente desse endereço como vista. */
function contar_visita_adiada(int $id, string $origem, string $caminho): void {
    contar_visita($id, $origem);
    if (!pre_carregamento_pronto()) return;
    try {
        q('UPDATE entradas_hora SET pre = 2 WHERE pre = 1 AND caminho = ? AND quando >= NOW() - INTERVAL 1 DAY ORDER BY quando DESC LIMIT 1',
            [mb_substr($caminho, 0, 190)]);
    } catch (PDOException) {}
}

/**
 * 19/11 (passo 116): números de verdade dos últimos 30 dias para mostrar aos donos ("Seu negócio" na inicial e planos).
 * Guardados por 1 hora em <dados_dir>/cache/numeros-publicos.json. Pessoas = entradas no site sem o pré-carregamento do Chrome.
 */
function numeros_publicos(): array {
    $arq = config()['dados_dir'] . '/cache/numeros-publicos.json';
    if (is_file($arq) && filemtime($arq) > time() - 3600 && ($j = json_decode((string)file_get_contents($arq), true))) return $j;
    $n = ['pessoas' => 0, 'vistas' => 0, 'contatos' => 0];
    try {
        $entradas = (int)(valor('SELECT SUM(total) FROM entradas WHERE dia >= CURDATE() - INTERVAL 29 DAY') ?? 0);
        $pre = 0;
        try { $pre = (int)valor("SELECT COUNT(*) FROM entradas_hora WHERE quando >= CURDATE() - INTERVAL 29 DAY AND pre = 1"); } catch (PDOException) {}
        $n['pessoas'] = max(0, $entradas - $pre);
        $l = linha("SELECT SUM(IF(tipo = 'perfil', total, 0)) AS v, SUM(IF(tipo IN ('whatsapp','ligar','mapa','site','instagram'), total, 0)) AS c
            FROM contatos WHERE dia >= CURDATE() - INTERVAL 29 DAY");
        $n['vistas'] = (int)($l['v'] ?? 0);
        $n['contatos'] = (int)($l['c'] ?? 0);
    } catch (PDOException) {}
    @mkdir(dirname($arq), 0775, true);
    @file_put_contents($arq, json_encode($n));
    return $n;
}

/** Bloco com os números (só mostra quando já tem movimento). */
function numeros_publicos_html(): string {
    $n = numeros_publicos();
    if ($n['vistas'] < 100) return '';
    $f = fn(int $v) => number_format($v, 0, ',', '.');
    return '<div class="numeros-blocos" aria-label="Números do Acha Fácil nos últimos 30 dias">'
        . ($n['pessoas'] ? '<div><strong>' . $f($n['pessoas']) . '</strong><span>visitas de pessoas ao site</span></div>' : '')
        . '<div><strong>' . $f($n['vistas']) . '</strong><span>páginas de comércio abertas</span></div>'
        . '<div><strong>' . $f($n['contatos']) . '</strong><span>toques em WhatsApp, ligar e "como chegar"</span></div>'
        . '</div><p class="discreto" style="margin-top:-6px">Números reais dos últimos 30 dias, no site e no app.</p>';
}
