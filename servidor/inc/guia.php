<?php
// Regras do guia: quem aparece, ordem da vitrine, formato dos dados enviados ao app, notas
declare(strict_types=1);

// Estados e cidades atendidas: slug => [nome completo, nome curto, UF]. O app manda ?cidade=<slug> em todas as chamadas.
// Cidade nova: acrescentar aqui, em src/cidade.tsx (app) e, se for longe, na caixa de geocodificar.php.
const ESTADOS = ['SP' => 'São Paulo', 'MG' => 'Minas Gerais'];
const CIDADES = [
    'sao-joao' => ['São João da Boa Vista', 'São João', 'SP'],
    'aguas-da-prata' => ['Águas da Prata', 'Águas da Prata', 'SP'],
    'pocos-de-caldas' => ['Poços de Caldas', 'Poços de Caldas', 'MG'],
];
const CIDADE_PADRAO = 'sao-joao';

/** Cidade pedida pelo app (?cidade=); desconhecida ou vazia vira a padrão. */
function cidade_pedida(): string {
    $c = (string)($_GET['cidade'] ?? '');
    return isset(CIDADES[$c]) ? $c : CIDADE_PADRAO;
}
function nome_cidade(string $slug, bool $curto = false): string { return CIDADES[$slug][$curto ? 1 : 0] ?? CIDADES[CIDADE_PADRAO][0]; }
function uf_cidade(string $slug): string { return CIDADES[$slug][2] ?? 'SP'; }

// Planos do dono do comércio (ver inc/mercadopago.php):
//   Básico: pagamento único (corrigir os dados, fotos, descrição e o que vende; vale para sempre)
//   Destaque: assinatura mensal no cartão (tudo do Básico + selo "Verificado", responder avaliações, números da página
//             e o topo da tela inicial e da área)
const PLANOS_PRECO = ['basico' => 5.99, 'destaque' => 29.90];
const PLANOS_NOME = ['basico' => 'Básico', 'destaque' => 'Destaque'];
function plano_unico(string $plano): bool { return $plano === 'basico'; }
function preco_plano(string $plano): string { return 'R$ ' . number_format(PLANOS_PRECO[$plano] ?? 0, 2, ',', '.'); }
/** "R$ 5,99 uma vez só" ou "R$ 29,90 por mês". */
function preco_plano_texto(string $plano): string { return preco_plano($plano) . (plano_unico($plano) ? ' uma vez só' : ' por mês'); }

// Assinatura do Destaque em dia: cortesia dada pelo admin, ou paga até hoje (assinatura_ate é estendida a cada
// mensalidade paga; cancelada continua valendo até o fim do mês já pago).
const SQL_ASSINATURA_EM_DIA = "(p.assinatura = 'cortesia' OR (p.assinatura IN ('ativa','cancelada') AND p.assinatura_ate >= CURDATE()))";
// Em dia para aparecer no app: Básico pago (uma vez só, vale para sempre) ou assinatura em dia.
// Destaque atrasado: sai do destaque e, se pagou o Básico, continua no app com o Básico.
const SQL_EM_DIA = '(p.basico_pago_em IS NOT NULL OR ' . SQL_ASSINATURA_EM_DIA . ')';
const SQL_DESTAQUE = "(p.plano = 'destaque' AND " . SQL_ASSINATURA_EM_DIA . ')';

// Aparece no app: aprovado e com assinatura em dia.
// Comércios importados da lista de leads aparecem sem assinatura até o dono assumir o perfil.
// Com $cidade, só os daquela cidade (o slug vem da lista fixa CIDADES, então pode ir direto no SQL).
function visivel(?string $cidade = null): string {
    $sql = "p.status = 'ativo' AND (p.importado = 1 OR " . SQL_EM_DIA . ") AND c.ativa = 1";
    return $cidade !== null && isset(CIDADES[$cidade]) ? "$sql AND p.cidade = '$cidade'" : $sql;
}

// Ordem do dia: muda uma vez por dia, para ninguém ficar sempre no topo nem sempre no fim
function semente_dia(): int { return (int)date('Ymd'); }

const CAMPOS_RESUMO = 'p.id, p.nome, p.tipo, p.cidade, p.importado, p.servicos, p.bairro, p.atende_em_casa, p.foto, p.verificado, p.plano,
    ' . SQL_DESTAQUE . ' AS destaque_pago,
    p.nota_media, p.total_avaliacoes, p.whatsapp, p.telefone, c.id AS categoria_id, c.nome AS categoria, c.icone, c.cor';

function foto_url(?string $foto): ?string { return $foto ? url_publica('foto.php?f=' . rawurlencode($foto)) : null; }

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
        'foto' => foto_url($p['foto']),
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

function contar_contato(int $profissional_id, string $tipo): void {
    if (!in_array($tipo, ['perfil', 'whatsapp', 'ligar', 'instagram', 'mapa', 'site'], true)) return;
    q('INSERT INTO contatos (profissional_id, tipo, dia, total) VALUES (?,?,CURDATE(),1) ON DUPLICATE KEY UPDATE total = total + 1',
        [$profissional_id, $tipo]);
}
