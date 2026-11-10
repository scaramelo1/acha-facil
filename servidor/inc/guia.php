<?php
// Regras do guia: quem aparece, ordem da vitrine, formato dos dados enviados ao app, notas
declare(strict_types=1);

// Cidades atendidas: tabela cidades (mudança 016), editada em /admin/cidades.php. slug => [nome completo, nome curto, UF].
// O app manda ?cidade=<slug> em todas as chamadas e baixa a lista em api/cidades.php: cidade nova NÃO precisa de APK novo.
// CIDADES = só as ativas (as que aparecem no app); TODAS_CIDADES = também as preparadas (importação, geocodificação).
const CIDADES_RESERVA = [   // usada só se a tabela ainda não existir (antes da mudança 016)
    'sao-joao' => ['São João da Boa Vista', 'São João', 'SP', -21.9696, -46.7981],
    'aguas-da-prata' => ['Águas da Prata', 'Águas da Prata', 'SP', -21.9371, -46.7172],
    'pocos-de-caldas' => ['Poços de Caldas', 'Poços de Caldas', 'MG', -21.7878, -46.5614],
    'vargem-grande-do-sul' => ['Vargem Grande do Sul', 'Vargem Grande', 'SP', -21.8322, -46.8936],
    'aguai' => ['Aguaí', 'Aguaí', 'SP', -22.0597, -46.9803],
];
const NOMES_ESTADOS = [
    'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas', 'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal',
    'ES' => 'Espírito Santo', 'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais',
    'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná', 'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro',
    'RN' => 'Rio Grande do Norte', 'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
    'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins',
];
/** Cidades do banco: slug => [nome, curto, UF, lat, lng, ativa]. */
function carregar_cidades(): array {
    try {
        $r = [];
        foreach (db()->query('SELECT slug, nome, curto, uf, lat, lng, ativa FROM cidades ORDER BY ordem, nome')->fetchAll(PDO::FETCH_ASSOC) as $c)
            if (preg_match('/^[a-z0-9-]{2,40}$/', $c['slug']))   // o slug vai direto no SQL (visivel), então só letras, números e hífen
                $r[$c['slug']] = [$c['nome'], $c['curto'], $c['uf'], $c['lat'] === null ? null : (float)$c['lat'], $c['lng'] === null ? null : (float)$c['lng'], (bool)$c['ativa']];
        if ($r) return $r;
    } catch (Throwable) {
        // tabela ainda não criada
    }
    return array_map(fn($c) => [...$c, true], CIDADES_RESERVA);
}
define('TODAS_CIDADES', carregar_cidades());
define('CIDADES', array_filter(TODAS_CIDADES, fn($c) => $c[5]) ?: TODAS_CIDADES);
// estados que têm cidade ativa, na ordem em que aparecem
$ufs_ativas = array_values(array_unique(array_column(CIDADES, 2)));
define('ESTADOS', array_combine($ufs_ativas, array_map(fn($uf) => NOMES_ESTADOS[$uf] ?? $uf, $ufs_ativas)));
unset($ufs_ativas);
define('CIDADE_PADRAO', isset(CIDADES['sao-joao']) ? 'sao-joao' : (string)array_key_first(CIDADES));

/** Cidade pedida pelo app (?cidade=); desconhecida ou vazia vira a padrão. */
function cidade_pedida(): string {
    $c = (string)($_GET['cidade'] ?? '');
    return isset(CIDADES[$c]) ? $c : CIDADE_PADRAO;
}

/** <option> das cidades ativas, agrupadas por estado, para os formulários do site (a 1ª é "Escolha a cidade"). */
function opcoes_cidades(?string $escolhida = null): string {
    $por_uf = [];
    foreach (CIDADES as $slug => [$nome, , $uf]) $por_uf[$uf][$slug] = $nome;
    ksort($por_uf);
    $html = '<option value="">Escolha a cidade</option>';
    foreach ($por_uf as $uf => $cidades) {
        uasort($cidades, fn($a, $b) => strcmp(sem_acento($a), sem_acento($b)));   // "Águas" antes de "Aguaí" e não no fim
        $html .= '<optgroup label="' . h($uf) . '">';
        foreach ($cidades as $slug => $nome) $html .= '<option value="' . h($slug) . '"' . ($slug === $escolhida ? ' selected' : '') . '>' . h("$nome ($uf)") . '</option>';
        $html .= '</optgroup>';
    }
    return $html;
}
function nome_cidade(string $slug, bool $curto = false): string { return TODAS_CIDADES[$slug][$curto ? 1 : 0] ?? CIDADES[CIDADE_PADRAO][0]; }
function uf_cidade(string $slug): string { return TODAS_CIDADES[$slug][2] ?? 'SP'; }
/** "São João da Boa Vista, Águas da Prata (SP) e Poços de Caldas (MG)": cidades ativas agrupadas por estado, para os textos do site. */
function texto_cidades(): string {
    $por_uf = [];
    foreach (CIDADES as [$nome, , $uf]) $por_uf[$uf][] = $nome;
    $partes = [];
    foreach ($por_uf as $uf => $nomes) $partes[] = (count($nomes) > 1 ? implode(', ', array_slice($nomes, 0, -1)) . ' e ' . end($nomes) : $nomes[0]) . " ($uf)";
    return count($partes) > 1 ? implode(', ', array_slice($partes, 0, -1)) . ' e ' . end($partes) : $partes[0];
}

// Planos do dono do comércio (ver inc/mercadopago.php):
//   Básico: pagamento único (corrigir os dados, fotos, descrição e o que vende; vale para sempre)
//   Destaque: assinatura mensal no cartão (tudo do Básico + selo "Verificado", responder avaliações, números da página
//             e o topo da tela inicial e da área)
//   Destaque + Site (10/11, mudança 018): assinatura mensal no cartão com tudo do Destaque + landing page própria feita
//             pela FSA e domínio incluso (a FSA faz o site depois do pagamento; o endereço vai no campo `site` da ficha)
const PLANOS_PRECO = ['basico' => 9.90, 'destaque' => 29.90, 'site' => 89.90];
const PLANOS_NOME = ['basico' => 'Básico', 'destaque' => 'Destaque', 'site' => 'Destaque + Site'];
const PLANOS_DESTAQUE = ['destaque', 'site'];   // planos que dão os recursos do Destaque
function plano_unico(string $plano): bool { return $plano === 'basico'; }
/** A mudança 018 (plano Destaque + Site no banco) já foi aplicada? Antes dela o plano não aparece no site. */
function plano_site_pronto(): bool {
    static $ok = null;
    return $ok ??= (bool)valor("SELECT 1 FROM migracoes WHERE nome = '018-plano-site.sql'");
}
/** Planos que o dono pode escolher agora (o Destaque + Site só depois da mudança 018). */
function planos_disponiveis(): array {
    return array_values(array_filter(array_keys(PLANOS_PRECO), fn($k) => $k !== 'site' || plano_site_pronto()));
}
function preco_plano(string $plano): string { return 'R$ ' . number_format(PLANOS_PRECO[$plano] ?? 0, 2, ',', '.'); }
/** "R$ 5,99 uma vez só" ou "R$ 29,90 por mês". */
function preco_plano_texto(string $plano): string { return preco_plano($plano) . (plano_unico($plano) ? ' uma vez só' : ' por mês'); }

// Assinatura do Destaque em dia: cortesia dada pelo admin, ou paga até hoje (assinatura_ate é estendida a cada
// mensalidade paga; cancelada continua valendo até o fim do mês já pago).
const SQL_ASSINATURA_EM_DIA = "(p.assinatura = 'cortesia' OR (p.assinatura IN ('ativa','cancelada') AND p.assinatura_ate >= CURDATE()))";
// Em dia para aparecer no app: Básico pago (uma vez só, vale para sempre) ou assinatura em dia.
// Destaque atrasado: sai do destaque e, se pagou o Básico, continua no app com o Básico.
const SQL_EM_DIA = '(p.basico_pago_em IS NOT NULL OR ' . SQL_ASSINATURA_EM_DIA . ')';
const SQL_DESTAQUE = "(p.plano IN ('destaque','site') AND " . SQL_ASSINATURA_EM_DIA . ')';

// Aparece no app: aprovado e com assinatura em dia.
// Comércios importados da lista de leads aparecem sem assinatura até o dono assumir o perfil.
// Com $cidade, só os daquela cidade (o slug vem de CIDADES, validado em carregar_cidades, então pode ir direto no SQL).
function visivel(?string $cidade = null): string {
    $sql = "p.status = 'ativo' AND (p.importado = 1 OR " . SQL_EM_DIA . ") AND c.ativa = 1";
    return $cidade !== null && isset(CIDADES[$cidade]) ? "$sql AND p.cidade = '$cidade'" : $sql;
}

// Ordem do dia: muda uma vez por dia, para ninguém ficar sempre no topo nem sempre no fim
function semente_dia(): int { return (int)date('Ymd'); }

const CAMPOS_RESUMO = 'p.id, p.nome, p.tipo, p.cidade, p.importado, p.servicos, p.bairro, p.atende_em_casa, p.foto, p.fotos, p.verificado, p.plano,
    ' . SQL_DESTAQUE . ' AS destaque_pago,
    p.nota_media, p.total_avaliacoes, p.whatsapp, p.telefone, c.id AS categoria_id, c.nome AS categoria, c.icone, c.cor';

require_once __DIR__ . '/horarios.php';

function foto_url(?string $foto): ?string { return $foto ? url_publica('foto.php?f=' . rawurlencode($foto)) : null; }

/** Galeria do comércio (coluna fotos, lista JSON de arquivos) -> nomes dos arquivos, na ordem. */
function lista_fotos(?string $json): array {
    $l = $json ? json_decode($json, true) : null;
    return is_array($l) ? array_values(array_filter($l, fn($f) => is_string($f) && preg_match('/^[a-z0-9-]{8,80}\.(jpg|png|webp)$/', $f))) : [];
}

function prof_resumo(array $p): array {
    return [
        'id' => (int)$p['id'],
        'nome' => $p['nome'],
        'categoria' => $p['categoria'],
        'categoriaId' => (int)$p['categoria_id'],
        'icone' => $p['icone'],
        'cor' => $p['cor'],
        'tipo' => $p['tipo'] ?: $p['categoria'],
        'servicos' => $p['servicos'] ? array_values(array_filter(array_map('trim', explode(',', $p['servicos'])))) : [],
        'bairro' => $p['bairro'],
        'cidade' => nome_cidade($p['cidade'], true),
        'atendeEmCasa' => (bool)$p['atende_em_casa'],
        'foto' => foto_url($p['foto']),   // logo do comércio
        'capa' => foto_url(lista_fotos($p['fotos'] ?? null)[0] ?? null),   // 1ª foto da galeria (faixa do destaque)
        'verificado' => (bool)$p['verificado'] && (bool)($p['destaque_pago'] ?? false),   // o selo é do plano Destaque
        'destaque' => (bool)($p['destaque_pago'] ?? false),   // plano Destaque com a assinatura em dia
        'nota' => round((float)$p['nota_media'], 1),
        'avaliacoes' => (int)$p['total_avaliacoes'],
        'temWhatsapp' => (bool)$p['whatsapp'],
        'temTelefone' => (bool)$p['telefone'],
        'cadastrado' => !$p['importado'],   // false = veio da lista pública, o dono ainda não assumiu
    ];
}

function categorias_ativas(?string $cidade = null): array {
    return array_map(fn($c) => [
        'id' => (int)$c['id'], 'nome' => $c['nome'], 'slug' => $c['slug'], 'icone' => $c['icone'], 'cor' => $c['cor'],
        'naInicial' => (bool)$c['na_inicial'], 'total' => (int)$c['total'],
    ], linhas('SELECT c.*, (SELECT COUNT(*) FROM profissionais p WHERE p.categoria_id = c.id AND ' . visivel($cidade) . ') AS total
        FROM categorias c WHERE c.ativa = 1 ORDER BY c.ordem, c.nome'));
}

function destaques(string $cidade, int $limite = 10): array {
    return array_map('prof_resumo', linhas('SELECT ' . CAMPOS_RESUMO . ' FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE ' . visivel($cidade) . ' AND ' . SQL_DESTAQUE . " ORDER BY RAND(?) LIMIT $limite", [semente_dia()]));
}

// "Conhecidos na cidade": sorteio do dia entre os 80 mais procurados (sem destaque pago, que já tem vitrine).
// $fora: ids que já apareceram em outra parte da tela; $com_contato: só quem tem WhatsApp ou telefone.
function populares(string $cidade, int $limite = 12, array $fora = [], bool $com_contato = false): array {
    $fora = array_map('intval', $fora) ?: [0];
    return array_map('prof_resumo', linhas('SELECT * FROM (SELECT ' . CAMPOS_RESUMO . ' FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE ' . visivel($cidade) . ' AND NOT ' . SQL_DESTAQUE . " AND p.id NOT IN (" . implode(',', $fora) . ')'
        . ($com_contato ? ' AND (p.whatsapp IS NOT NULL OR p.telefone IS NOT NULL)' : '') . "
        ORDER BY p.popularidade + p.total_avaliacoes * 5 DESC LIMIT 80) t ORDER BY RAND(?) LIMIT $limite", [semente_dia()]));
}

/** "Comércios em destaque" da tela inicial: só quem paga o plano Destaque (nunca completa com outros). */
function vitrine_inicio(string $cidade, int $total = 10): array {
    return destaques($cidade, $total);
}

// Atalhos do "Perto de você": rótulo, ícone e partes do tipo de negócio que entram (LIKE). Ficam no servidor
// para poder mudar sem lançar app novo.
const ATALHOS_PERTO = [
    'restaurantes' => ['Restaurantes', 'silverware-fork-knife', ['Restaurante', 'Brasileira', 'Japonesa', 'Churrasc', 'Self-service', 'Marmit', 'Comida']],
    'lanches' => ['Lanches', 'hamburger', ['Lanchonete', 'Lanche', 'Fast Food', 'Hamburg', 'Pastéis', 'Pastel']],
    'pizzarias' => ['Pizzarias', 'pizza', ['Pizza']],
    'padarias' => ['Padarias', 'baguette', ['Padaria', 'Confeitaria']],
    'mercados' => ['Mercados', 'cart-outline', ['Mercado', 'Mercearia', 'Hortifruti', 'Empório', 'Açougue']],
    'farmacias' => ['Farmácias', 'pill', ['Farmácia', 'Drogaria']],
    'floriculturas' => ['Floriculturas', 'flower-tulip-outline', ['Floricultura', 'Flores']],
    'pet' => ['Pet shops', 'paw', ['Pet', 'Veterin', 'animais']],
    'beleza' => ['Beleza', 'content-cut', ['Salão de Beleza', 'Barbearia', 'Manicure', 'Cabeleireir', 'Estética', 'Sobrancelha']],
    'sorvetes' => ['Sorvetes', 'ice-cream', ['Sorvet', 'Açaí', 'Gelat']],
    'oficinas' => ['Oficinas', 'car-wrench', ['Oficina', 'Autopeças', 'Pneu', 'Borracharia', 'Lava-rápido']],
    'postos' => ['Postos', 'gas-station', ['Posto de combustível', 'Conveniência']],
    'saude' => ['Saúde', 'medical-bag', ['Dentista', 'Clínica', 'Médic', 'Fisioterap', 'Psicólog', 'Odonto', 'Laboratório']],
];

function atalhos_perto(): array {
    return array_map(fn($id, $a) => ['id' => $id, 'rotulo' => $a[0], 'icone' => $a[1]], array_keys(ATALHOS_PERTO), ATALHOS_PERTO);
}

/**
 * Comércios mais perto de um ponto (todas as cidades), do mais perto ao mais longe, até $raio_km.
 * $atalho (de ATALHOS_PERTO) filtra pelo tipo de negócio. A distância é em linha reta e aproximada
 * (muitos endereços só foram achados pela rua).
 */
// só comércios da cidade escolhida no app (quem está em São João olhando Poços não vê os de São João)
function perto(float $lat, float $lng, ?string $atalho, string $cidade, float $raio_km = 15, int $limite = 30): array {
    $filtro = '';
    $params = [$lat, $lat];
    if ($atalho !== null && isset(ATALHOS_PERTO[$atalho])) {
        $partes = ATALHOS_PERTO[$atalho][2];
        $filtro = ' AND (' . implode(' OR ', array_fill(0, count($partes), 'p.tipo LIKE ?')) . ')';
        foreach ($partes as $t) $params[] = '%' . $t . '%';
    }
    // caixa para usar o índice, depois a distância de verdade (Haversine, raio da Terra 6371 km)
    $dlat = $raio_km / 111; $dlng = $raio_km / (111 * max(0.2, cos(deg2rad($lat))));
    $params = array_merge($params, [$lat - $dlat, $lat + $dlat, $lng - $dlng, $lng + $dlng, $raio_km]);
    return array_map(fn($p) => prof_resumo($p) + ['distancia' => round((float)$p['distancia'], 2)], linhas('SELECT ' . CAMPOS_RESUMO . ',
        6371 * 2 * ASIN(SQRT(POW(SIN(RADIANS(p.lat - ?) / 2), 2) + COS(RADIANS(?)) * COS(RADIANS(p.lat)) * POW(SIN(RADIANS(p.lng - ' . (float)$lng . ') / 2), 2))) AS distancia
        FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE ' . visivel($cidade) . " AND p.lat IS NOT NULL$filtro AND p.lat BETWEEN ? AND ? AND p.lng BETWEEN ? AND ?
        HAVING distancia <= ? ORDER BY distancia LIMIT $limite", $params));
}

// tipos de negócio dentro de uma área (filtros da tela da área), do mais comum ao menos
function tipos_da_categoria(int $categoria_id, string $cidade): array {
    return array_map(fn($t) => ['nome' => $t['tipo'], 'total' => (int)$t['total']], linhas('SELECT p.tipo, COUNT(*) AS total
        FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE ' . visivel($cidade) . ' AND p.categoria_id = ? AND p.tipo IS NOT NULL
        GROUP BY p.tipo ORDER BY total DESC, p.tipo LIMIT 30', [$categoria_id]));
}

// destaques primeiro, depois quem se cadastrou, depois quem tem nota; rodízio diário dentro de cada grupo
function da_categoria(int $categoria_id, string $cidade): array {
    return array_map('prof_resumo', linhas('SELECT ' . CAMPOS_RESUMO . ' FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE ' . visivel($cidade) . " AND p.categoria_id = ?
        ORDER BY " . SQL_DESTAQUE . " DESC, p.importado ASC, p.total_avaliacoes > 0 DESC, RAND(?)",
        [$categoria_id, semente_dia()]));
}

// $cidade null = todas (busca do site, para o dono achar o negócio)
function buscar(string $termo, ?string $cidade, int $limite = 50): array {
    $termo = trim(mb_substr($termo, 0, 60));
    if (mb_strlen($termo) < 2) return [];
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $termo) . '%';
    return array_map('prof_resumo', linhas('SELECT ' . CAMPOS_RESUMO . ' FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE ' . visivel($cidade) . ' AND (p.nome LIKE ? OR p.tipo LIKE ? OR p.servicos LIKE ? OR p.descricao LIKE ? OR p.bairro LIKE ? OR c.nome LIKE ?)
        ORDER BY (c.nome LIKE ? OR p.tipo LIKE ? OR p.nome LIKE ?) DESC, ' . SQL_DESTAQUE . ' DESC, p.importado ASC, RAND(?) LIMIT ' . $limite,
        [$like, $like, $like, $like, $like, $like, $like, $like, $like, semente_dia()]));
}

function profissional_visivel(int $id): ?array {
    return $id ? linha('SELECT p.*, ' . SQL_DESTAQUE . ' AS destaque_pago, c.nome AS categoria, c.icone, c.cor FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE p.id = ? AND ' . visivel(), [$id]) : null;
}

function prof_completo(array $p, ?int $usuario_id): array {
    $r = prof_resumo($p);
    $p += ['site' => null, 'mapa' => null];
    $r += [
        'responsavel' => $p['responsavel'],
        'descricao' => $p['descricao'],
        'telefone' => $p['telefone'],
        'telefoneFormatado' => $p['telefone'] ? telefone_formatado($p['telefone']) : null,
        'whatsapp' => $p['whatsapp'] ? link_whatsapp($p['whatsapp'], 'Olá! Encontrei você no Acha Fácil.') : null,
        'instagram' => $p['instagram'] ? 'https://instagram.com/' . ltrim($p['instagram'], '@') : null,
        'endereco' => $p['endereco'],
        'mapa' => $p['mapa'] ?: ($p['endereco'] ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($p['endereco'] . ', ' . nome_cidade($p['cidade']) . ' - ' . uf_cidade($p['cidade'])) : null),
        'site' => $p['site'],
        'linkAssumir' => $p['importado'] ? url_publica('planos.php?id=' . $p['id']) : null,
        'linkRemover' => $p['importado'] ? url_publica('perfil.php?id=' . $p['id'] . '&acao=remover') : null,
        'horario' => $p['horario'],
        'horarios' => horarios_api(horarios_ler($p['horarios'] ?? null)),   // um campo por linha (Segunda a sexta, Sábado...)
        'fotos' => array_map('foto_url', lista_fotos($p['fotos'] ?? null)),   // carrossel da capa
        'favorito' => $usuario_id ? (bool)valor('SELECT 1 FROM favoritos WHERE usuario_id = ? AND profissional_id = ?', [$usuario_id, $p['id']]) : false,
        'minhaAvaliacao' => null,
        'comentarios' => array_map(fn($a) => [
            'id' => (int)$a['id'], 'autor' => primeiro_nome($a['autor']), 'estrelas' => (int)$a['estrelas'],
            'comentario' => $a['comentario'], 'resposta' => $a['resposta'], 'data' => data_br($a['criado_em']),
        ], linhas("SELECT a.*, u.nome AS autor FROM avaliacoes a JOIN usuarios u ON u.id = a.usuario_id
            WHERE a.profissional_id = ? AND a.status = 'publicada' ORDER BY a.comentario IS NULL, a.atualizado_em DESC LIMIT 30", [$p['id']])),
    ];
    if ($usuario_id) {
        $m = linha('SELECT estrelas, comentario FROM avaliacoes WHERE profissional_id = ? AND usuario_id = ?', [$p['id'], $usuario_id]);
        if ($m) $r['minhaAvaliacao'] = ['estrelas' => (int)$m['estrelas'], 'comentario' => $m['comentario']];
    }
    return $r;
}

// "Maria Aparecida Silva" → "Maria A." (privacidade de quem comenta)
function primeiro_nome(string $nome): string {
    $p = preg_split('/\s+/', trim($nome));
    return $p[0] . (isset($p[1]) ? ' ' . mb_strtoupper(mb_substr(end($p), 0, 1)) . '.' : '');
}

function recalcular_nota(int $profissional_id): void {
    q("UPDATE profissionais SET
        nota_media = COALESCE((SELECT AVG(estrelas) FROM avaliacoes WHERE profissional_id = ? AND status = 'publicada'), 0),
        total_avaliacoes = (SELECT COUNT(*) FROM avaliacoes WHERE profissional_id = ? AND status = 'publicada')
        WHERE id = ?", [$profissional_id, $profissional_id, $profissional_id]);
}

// contar_contato(), contar_visita() e contar_aparicoes() ficam em inc/numeros.php (passo 20)
require_once __DIR__ . '/numeros.php';

// Municípios do Brasil (IBGE, dados/municipios.json: {estados: {UF: nome}, cidades: {UF: [nomes]}}) para o
// "Quero minha Cidade!": o app escolhe o estado e a cidade sai desta lista (nada de nome inventado).
function municipios(): array {
    static $m = null;
    return $m ??= json_decode((string)file_get_contents(__DIR__ . '/../dados/municipios.json'), true) ?: ['estados' => [], 'cidades' => []];
}
function sem_acento(string $s): string {
    return mb_strtolower(strtr($s, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','ê'=>'e','è'=>'e','í'=>'i','ì'=>'i','î'=>'i',
        'ó'=>'o','ô'=>'o','õ'=>'o','ò'=>'o','ú'=>'u','ü'=>'u','ù'=>'u','ç'=>'c','Á'=>'a','À'=>'a','Â'=>'a','Ã'=>'a','É'=>'e','Ê'=>'e',
        'Í'=>'i','Ó'=>'o','Ô'=>'o','Õ'=>'o','Ú'=>'u','Ç'=>'c']));
}
/** Nome oficial da cidade daquele estado (sem ligar para acento e maiúscula), ou null se não existe. */
function municipio_oficial(string $uf, string $nome): ?string {
    $alvo = sem_acento(trim(preg_replace('/\s+/', ' ', $nome)));
    foreach (municipios()['cidades'][$uf] ?? [] as $c) if (sem_acento($c) === $alvo) return $c;
    return null;
}

// ---------- endereços das páginas públicas (passo 21: versão web com SEO) ----------
// Cidade com o nome completo no endereço (pedido do usuário): achafacilapp.com.br/sao-joao-da-boa-vista/saude/dentista/
/** "Clínica Odontológica São José" -> "clinica-odontologica-sao-jose". */
function slug_web(string $s): string {
    return trim(preg_replace('/[^a-z0-9]+/', '-', sem_acento($s)), '-');
}
/** Slug da cidade no endereço do site (nome completo), a partir do slug interno (sao-joao -> sao-joao-da-boa-vista). */
function cidade_web(string $cidade): string { return slug_web(TODAS_CIDADES[$cidade][0] ?? $cidade); }
/** Slug do endereço -> slug interno, só entre as cidades ativas. */
function cidade_da_web(string $web): ?string {
    foreach (CIDADES as $slug => $c) if (slug_web($c[0]) === $web) return $slug;
    return null;
}
function url_cidade(string $cidade): string { return url(cidade_web($cidade) . '/'); }
function url_area(string $cidade, string $area): string { return url(cidade_web($cidade) . "/$area/"); }
function url_tipo(string $cidade, string $area, string $tipo): string { return url(cidade_web($cidade) . "/$area/" . slug_web($tipo) . '/'); }
/** Ficha: /<cidade>/c/<nome>-<id> (o número no fim é o que vale; nome trocado redireciona para o certo). */
function caminho_ficha(int $id, string $nome, string $cidade): string {
    return cidade_web($cidade) . '/c/' . (rtrim(mb_substr(slug_web($nome), 0, 70), '-') ?: 'comercio') . '-' . $id;
}
function url_ficha(array $p): string { return url(caminho_ficha((int)$p['id'], (string)$p['nome'], (string)$p['cidade'])); }

// Cache das páginas públicas (inc/web.php): /var/lib/acha-facil/cache/web. Fica aqui porque o admin também limpa
// (aprovar alteração, ligar/desligar cidade).
function web_cache_dir(): ?string {
    $d = config()['dados_dir'] . '/cache/web';
    if (!is_dir($d)) @mkdir($d, 0750, true);
    return is_dir($d) && is_writable($d) ? $d : null;
}
/** Apaga as páginas guardadas (todas, ou só as mais velhas que $idade segundos). */
function web_cache_limpar(int $idade = 0): void {
    $dir = web_cache_dir();
    if (!$dir) return;
    foreach (glob("$dir/*.html") ?: [] as $f) if ($idade === 0 || filemtime($f) < time() - $idade) @unlink($f);
}
