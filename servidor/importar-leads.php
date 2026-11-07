<?php
// Importa (ou atualiza) os comércios de uma lista de leads para o Acha Fácil. Rodar na VPS como root:
//   php servidor/importar-leads.php [arquivo leads.html] [cidade]
//   cidade: sao-joao (padrão) ou aguas-da-prata. Ex.: php servidor/importar-leads.php /var/lib/acha-facil/leads/aguas-da-prata.html aguas-da-prata
// Padrão: a lista de São João publicada em fsadesigners.com.br/leads/. Linhas cujo endereço é de outra cidade atendida
// vão para aquela cidade. Listas sem área (data-seg) usam a área mais comum do mesmo tipo de negócio já cadastrado. Pode rodar de novo sempre que a lista mudar:
// atualiza quem já veio dos leads (pelo nome) e não mexe em quem assumiu o perfil nem em quem foi removido.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/inc/base.php';
require __DIR__ . '/inc/guia.php';

$arquivo = $argv[1] ?? '/var/www/fsa-designer/leads/dados/leads.html';
$cidade_lista = $argv[2] ?? 'sao-joao';
if (!isset(CIDADES[$cidade_lista])) exit("✘ Cidade desconhecida: $cidade_lista (use " . implode(' ou ', array_keys(CIDADES)) . ")\n");
$html = @file_get_contents($arquivo) ?: exit("✘ Não consegui ler $arquivo\n");

// área da lista (data-seg) → slug da categoria no app
$areas = [
    'Alimentação' => 'alimentacao', 'Mercados e bebidas' => 'mercados', 'Serviços' => 'servicos', 'Saúde' => 'saude',
    'Beleza' => 'beleza', 'Moda' => 'moda', 'Casa e construção' => 'casa', 'Veículos' => 'veiculos',
    'Lojas e varejo' => 'lojas', 'Educação' => 'educacao', 'Esporte e fitness' => 'esporte', 'Pet e agro' => 'pet',
    'Hospedagem e eventos' => 'hospedagem', 'Financeiro' => 'financeiro', 'Indústria' => 'industria',
    'Igrejas e entidades' => 'entidades', 'Outros' => 'outros',
];
$cat = [];
foreach (linhas('SELECT id, slug FROM categorias WHERE ativa = 1') as $c) $cat[$c['slug']] = (int)$c['id'];
if (!isset($cat['outros'])) exit("✘ Categorias novas não encontradas. Rode antes: bash servidor/instalar.sh\n");
// tipo de negócio → área mais usada para ele (para listas que não trazem a área)
$area_do_tipo = [];
foreach (linhas('SELECT p.tipo, p.categoria_id, COUNT(*) n FROM profissionais p JOIN categorias c ON c.id = p.categoria_id AND c.ativa = 1
    WHERE p.tipo IS NOT NULL GROUP BY p.tipo, p.categoria_id ORDER BY n') as $t) $area_do_tipo[mb_strtolower($t['tipo'])] = (int)$t['categoria_id'];
// tipos simples (listas sem área) que não batem com os nomes do Google já cadastrados
$area_fixa = [
    'açaí' => 'alimentacao', 'pastelaria' => 'alimentacao', 'pizzaria' => 'alimentacao', 'sorveteria' => 'alimentacao',
    'conveniência' => 'mercados', 'distribuidora' => 'mercados', 'empório' => 'mercados',
    'acessórios automotivos' => 'veiculos', 'estética automotiva' => 'veiculos', 'oficina de motos' => 'veiculos',
    'materiais de construção' => 'casa', 'materiais elétricos' => 'casa', 'paisagismo' => 'casa', 'produtos para piscina' => 'casa',
    'assistência técnica' => 'servicos', 'cobrança' => 'financeiro', 'contabilidade' => 'servicos',
    'eventos' => 'hospedagem', 'hospedagem' => 'hospedagem', 'fisioterapia' => 'saude', 'fábrica' => 'industria',
];
foreach ($area_fixa as $t => $slug) if (isset($cat[$slug])) $area_do_tipo[$t] = $cat[$slug];
// cidade pelo endereço ("..., Águas da Prata - SP")
function cidade_do_endereco(string $e, string $padrao): string {
    foreach (CIDADES as $slug => [$nome]) if (mb_stripos($e, $nome) !== false) return $slug;
    return $padrao;
}

function limpo(string $s): string { return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8'))); }
function chave(string $nome): string {
    $s = mb_strtolower(limpo($nome));
    $s = strtr($s, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ü'=>'u','ç'=>'c']);
    return mb_substr(trim(preg_replace('/[^a-z0-9]+/', ' ', $s)), 0, 160);
}
// "Rua X, 410 - Jardim São Paulo, São João da Boa Vista - SP, 13875-089, Brasil" → ["Rua X, 410", "Jardim São Paulo"]
function endereco_e_bairro(string $e): array {
    $e = preg_replace('/,?\s*(S[ãa]o Jo[ãa]o da Boa Vista|[ÁA]guas da Prata)\b.*$/iu', '', $e);
    $e = trim(preg_replace('/,?\s*\d{5}-?\d{3}.*$/', '', $e), " ,-");
    $endereco = null; $bairro = null;
    foreach (array_map('trim', explode(' - ', $e)) as $parte) {
        if ($parte === '') continue;
        $rua = preg_match('/^(r|rua|av|avenida|al|alameda|pra[çc]a|p[çc]a|rod|rodovia|estr|estrada|tv|travessa|largo|via)[\s.:]/iu', $parte);
        if ($rua || preg_match('/\d/', $parte)) $endereco ??= $parte;
        elseif (!$bairro && mb_strlen($parte) >= 3 && mb_strlen($parte) <= 60) $bairro = $parte;
    }
    return [$endereco ? mb_substr($endereco, 0, 200) : null, $bairro ? mb_substr($bairro, 0, 80) : null];
}
function normalizar_telefone(string $t): ?string {
    $t = so_digitos($t);
    if (str_starts_with($t, '0')) $t = ltrim($t, '0');                // 019... (discagem com 0)
    return telefone_normalizado($t);
}

preg_match_all('#<tr data-status="([^"]*)" data-cat="([^"]*)"(?: data-seg="([^"]*)")?>(.*?)</tr>#s', $html, $linhas_html, PREG_SET_ORDER);
if (!$linhas_html) exit("✘ Nenhum comércio encontrado em $arquivo (formato mudou?)\n");

$novos = $atualizados = $pulados = 0;
$vistos = [];
db()->beginTransaction();
foreach ($linhas_html as [, $status, $tipo, $area, $tr]) {
    preg_match_all('#<td[^>]*>(.*?)</td>#s', $tr, $td);
    $td = $td[1];
    if (count($td) < 8) { $pulados++; continue; }

    preg_match('#<a class="nm" href="([^"]*)"[^>]*>(.*?)</a>#s', $td[0], $m);
    $nome = isset($m[2]) ? mb_substr(limpo($m[2]), 0, 120) : '';
    preg_match('#<div class="sub">(.*?)</div>#s', $td[0], $e);
    $endereco_completo = limpo($e[1] ?? '');
    $cidade = cidade_do_endereco($endereco_completo, $cidade_lista);
    // São João mantém a chave antiga (só o nome); outras cidades levam o slug na frente
    $k = mb_substr(($cidade === 'sao-joao' ? '' : $cidade . ' ') . chave($nome), 0, 160);
    if ($nome === '' || $k === '' || isset($vistos[$k])) { $pulados++; continue; }
    $vistos[$k] = true;
    $mapa = html_entity_decode($m[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    [$endereco, $bairro] = endereco_e_bairro($endereco_completo);

    $telefone = normalizar_telefone(limpo($td[3]));
    $whatsapp = preg_match('#wa\.me/55(\d{10,11})#', $td[4], $w) ? $w[1] : null;
    $link = preg_match('#href="([^"]+)"#', $td[6], $l) ? html_entity_decode($l[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
    $instagram = $site = null;
    if ($link && preg_match('#instagram\.com/([A-Za-z0-9_.]{2,30})#', $link, $ig)) $instagram = $ig[1];
    elseif ($link && $status !== 'fora_do_ar' && preg_match('#^https?://#', $link)) $site = mb_substr($link, 0, 200);
    $popularidade = preg_match('/\((\d+)\)/', limpo($td[7]), $p) ? (int)$p[1] : 0;

    $tipo = mb_substr(html_entity_decode($tipo, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 0, 80);
    $area = html_entity_decode((string)$area, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $categoria_id = isset($areas[$area]) ? ($cat[$areas[$area]] ?? $cat['outros']) : ($area_do_tipo[mb_strtolower($tipo)] ?? $cat['outros']);
    $dados = [
        'categoria_id' => $categoria_id,
        'tipo' => $tipo ?: null,
        'cidade' => $cidade,
        'nome' => $nome, 'telefone' => $telefone, 'whatsapp' => $whatsapp, 'instagram' => $instagram, 'site' => $site,
        'endereco' => $endereco, 'bairro' => $bairro, 'mapa' => $mapa && str_starts_with($mapa, 'https://www.google.com/maps') ? mb_substr($mapa, 0, 500) : null,
        'popularidade' => $popularidade,
    ];
    $atual = linha('SELECT id, importado, status FROM profissionais WHERE chave_importacao = ?', [$k]);
    if (!$atual) {
        inserir('profissionais', $dados + ['chave_importacao' => $k, 'importado' => 1, 'status' => 'ativo', 'assinatura' => 'sem']);
        $novos++;
    } elseif ((int)$atual['importado'] === 1 && $atual['status'] === 'ativo') {
        atualizar('profissionais', (int)$atual['id'], $dados);
        $atualizados++;
    } else {
        $pulados++; // dono assumiu o perfil, ou foi removido/pausado pelo admin
    }
}
db()->commit();
$total = (int)valor('SELECT COUNT(*) FROM profissionais WHERE importado = 1 AND status = \'ativo\' AND cidade = ?', [$cidade_lista]);
echo "✔ Leads de " . nome_cidade($cidade_lista) . ": $novos novos, $atualizados atualizados, $pulados pulados (repetidos ou mantidos). No app: $total comércios importados nesta cidade.\n";
