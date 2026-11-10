<?php
// Números das fichas (passo 20): visualizações com origem e pessoas diferentes por dia, aparições em listas e toques
// em WhatsApp/ligar/mapa. Vale para TODAS as fichas (com ou sem plano): é o dado da abordagem comercial.
// Tabelas: contatos (mudança 002/017), vistas e aparicoes (mudança 017).
declare(strict_types=1);

/** De onde a pessoa abriu a ficha (o app manda ?origem=; o site manda 'web'). */
const ORIGENS = [
    'busca' => 'Busca', 'categoria' => 'Categoria', 'perto' => 'Perto de você', 'destaque' => 'Destaque da tela inicial',
    'inicio' => 'Recomendados da tela inicial', 'favoritos' => 'Favoritos', 'link' => 'Link ou aviso', 'web' => 'Site',
    'app' => 'App (versão antiga, sem origem)',
];
/** Onde a ficha foi mostrada numa lista. */
const ONDE_APARICAO = ['inicio' => 'Tela inicial', 'categoria' => 'Categoria', 'busca' => 'Busca', 'perto' => 'Perto de você'];
const TIPOS_CONTATO = ['whatsapp' => 'WhatsApp', 'ligar' => 'Ligar', 'mapa' => 'Mapa', 'instagram' => 'Instagram', 'site' => 'Site'];
/** Numa lista longa (área com 700 comércios) só contam as primeiras posições, que a pessoa de fato vê. */
const APARICAO_MAX = 40;

function origem_valida(?string $o, string $padrao = 'app'): string {
    return $o !== null && isset(ORIGENS[$o]) ? $o : $padrao;
}

/** Robôs de busca, prévias de link e ferramentas não contam. */
function e_robo(): bool {
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    return $ua === '' || (bool)preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp\/|curl|wget|python|httpclient|headless|lighthouse/i', $ua);
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
    // de vez em quando apaga o que não serve mais (só o dia de hoje importa para separar as pessoas)
    if (random_int(1, 300) === 1) q('DELETE FROM vistas WHERE dia < CURDATE() - INTERVAL 1 DAY');
}

/** Toque em WhatsApp, ligar, Instagram, mapa ou site. */
function contar_contato(int $profissional_id, string $tipo, string $origem = 'app'): void {
    if (!isset(TIPOS_CONTATO[$tipo]) || e_robo()) return;
    try {
        q('INSERT INTO contatos (profissional_id, tipo, dia, origem, total) VALUES (?, ?, CURDATE(), ?, 1) ON DUPLICATE KEY UPDATE total = total + 1',
            [$profissional_id, $tipo, $origem === 'web' ? 'web' : 'app']);
    } catch (PDOException $e) { contar_sem_017($profissional_id, $tipo, $e); }
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
            $r['contatos'] += (int)$l['total'];
            $r['por_tipo'][$l['tipo']] = ($r['por_tipo'][$l['tipo']] ?? 0) + (int)$l['total'];
            $dia['contatos'] += (int)$l['total'];
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
