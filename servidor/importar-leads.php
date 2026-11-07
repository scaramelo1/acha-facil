<?php
// Importa (ou atualiza) os comércios da lista de leads para o Acha Fácil. Rodar na VPS como root:
//   php servidor/importar-leads.php [arquivo leads.html]
// Padrão: a lista publicada em fsadesigners.com.br/leads/. Pode rodar de novo sempre que a lista mudar:
// atualiza quem já veio dos leads (pelo nome) e não mexe em quem assumiu o perfil nem em quem foi removido.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/inc/base.php';

$arquivo = $argv[1] ?? '/var/www/fsa-designer/leads/dados/leads.html';
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

function limpo(string $s): string { return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8'))); }
function chave(string $nome): string {
    $s = mb_strtolower(limpo($nome));
    $s = strtr($s, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ü'=>'u','ç'=>'c']);
    return mb_substr(trim(preg_replace('/[^a-z0-9]+/', ' ', $s)), 0, 160);
}
// "Rua X, 410 - Jardim São Paulo, São João da Boa Vista - SP, 13875-089, Brasil" → ["Rua X, 410", "Jardim São Paulo"]
function endereco_e_bairro(string $e): array {
    $e = preg_replace('/,?\s*S[ãa]o Jo[ãa]o da Boa Vista.*$/iu', '', $e);
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

preg_match_all('#<tr data-status="([^"]*)" data-cat="([^"]*)" data-seg="([^"]*)">(.*?)</tr>#s', $html, $linhas_html, PREG_SET_ORDER);
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
    $k = chave($nome);
    if ($nome === '' || $k === '' || isset($vistos[$k])) { $pulados++; continue; }
    $vistos[$k] = true;
    $mapa = html_entity_decode($m[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    preg_match('#<div class="sub">(.*?)</div>#s', $td[0], $e);
    [$endereco, $bairro] = endereco_e_bairro(limpo($e[1] ?? ''));

    $telefone = normalizar_telefone(limpo($td[3]));
    $whatsapp = preg_match('#wa\.me/55(\d{10,11})#', $td[4], $w) ? $w[1] : null;
    $link = preg_match('#href="([^"]+)"#', $td[6], $l) ? html_entity_decode($l[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
    $instagram = $site = null;
    if ($link && preg_match('#instagram\.com/([A-Za-z0-9_.]{2,30})#', $link, $ig)) $instagram = $ig[1];
    elseif ($link && $status !== 'fora_do_ar' && preg_match('#^https?://#', $link)) $site = mb_substr($link, 0, 200);
    $popularidade = preg_match('/\((\d+)\)/', limpo($td[7]), $p) ? (int)$p[1] : 0;

    $dados = [
        'categoria_id' => $cat[$areas[html_entity_decode($area)] ?? 'outros'] ?? $cat['outros'],
        'tipo' => mb_substr(html_entity_decode($tipo, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 0, 80) ?: null,
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
$total = (int)valor('SELECT COUNT(*) FROM profissionais WHERE importado = 1 AND status = \'ativo\'');
echo "✔ Leads importados: $novos novos, $atualizados atualizados, $pulados pulados (repetidos ou mantidos). No app: $total comércios importados.\n";
