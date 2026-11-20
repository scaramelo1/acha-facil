<?php
// Importa (ou atualiza) os comércios de uma lista de leads para o Acha Fácil. Rodar na VPS como root:
//   php servidor/importar-leads.php [arquivo leads.html] [cidade]
//   cidade: o slug cadastrado em /admin/cidades.php (sao-joao é o padrão). Ex.: php servidor/importar-leads.php /var/lib/acha-facil/leads/aguas-da-prata.html aguas-da-prata
// Padrão: a lista de São João publicada em fsadesigners.com.br/leads/. Linhas cujo endereço é de outra cidade atendida
// vão para aquela cidade. Listas sem área (data-seg) usam a área mais comum do mesmo tipo de negócio já cadastrado. Pode rodar de novo sempre que a lista mudar:
// atualiza quem já veio dos leads (pelo nome) e não mexe em quem assumiu o perfil nem em quem foi removido.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/inc/base.php';
require_once __DIR__ . '/inc/guia.php';
require_once __DIR__ . '/inc/limpeza.php';   // 16/11: bairros e tipos limpos
require_once __DIR__ . '/inc/moderacao.php';   // editados_pelo_admin()

// --teste: faz tudo e mostra o resultado, mas desfaz no fim (nada muda no banco)
$teste = in_array('--teste', $argv, true);
// --detalhes=<arquivo.json>: grava a lista do que mudou (novos, mudaram, fecharam) para /admin/novidades.php
$detalhes_arq = null;
foreach ($argv as $a) if (str_starts_with($a, '--detalhes=')) $detalhes_arq = substr($a, 11);
$args = array_values(array_filter($argv, fn($a) => $a !== '--teste' && !str_starts_with($a, '--detalhes=')));
$det = ['novos' => [], 'mudaram' => [], 'fecharam' => []];
$arquivo = $args[1] ?? '/var/www/fsa-designer/leads/dados/leads.html';
$cidade_lista = $args[2] ?? 'sao-joao';
// vale também cidade cadastrada e ainda desligada em /admin/cidades.php (prepara os comércios antes de ligar)
if (!isset(TODAS_CIDADES[$cidade_lista])) exit("✘ Cidade desconhecida: $cidade_lista. Cadastre em /admin/cidades.php (use " . implode(', ', array_keys(TODAS_CIDADES)) . ")\n");
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
    // Poços de Caldas
    'autopeças' => 'veiculos', 'baterias' => 'veiculos', 'peças usadas' => 'veiculos', 'peças para caminhões' => 'veiculos', 'peças para motos' => 'veiculos',
    'reparo de celulares' => 'servicos', 'informática' => 'servicos', 'reparo de impressoras' => 'servicos', 'copiadora' => 'servicos',
    'comunicação visual' => 'servicos', 'convites' => 'servicos',
    'acessórios para celular' => 'lojas', 'artigos domésticos' => 'lojas', 'papelaria fina' => 'lojas', 'papelaria personalizada' => 'lojas',
    'descartáveis' => 'lojas', 'embalagens' => 'lojas', 'ateliê de arte' => 'lojas',
    'loja de roupas' => 'moda', 'loja de calçados' => 'moda', 'sapataria' => 'moda', 'loja de acessórios' => 'moda',
    'cosméticos' => 'beleza', 'chocolateria' => 'alimentacao', 'artigos para festas' => 'lojas', 'suplementos' => 'saude',
    'agropecuária' => 'pet', 'marcenaria' => 'casa',
    'escola infantil' => 'educacao', 'loja de queijos' => 'mercados', 'fondue' => 'alimentacao', 'equipamentos industriais' => 'industria',
];
foreach ($area_fixa as $t => $slug) if (isset($cat[$slug])) $area_do_tipo[$t] = $cat[$slug];
const PALAVRAS_AREA = [
    '/pizza|hamburg|restaurante|lanche|sushi|japonesa|marmit|churras|pastel|past[ée]is|sorvet|a[çc]a[íi]/iu' => 'alimentacao',
    '/sobrancelha|cabeleireir|barbear|manicure|est[ée]tica|maquiag/iu' => 'beleza',
    '/animais|pet\b|veterin/iu' => 'pet',
    '/nutr|m[ée]dic|cl[íi]nica|odont|dentista|psic/iu' => 'saude',
    '/mercad|mercearia|hortifr|a[çc]ougue|bebidas/iu' => 'mercados',
    // nomes longos do Google (listas como a de Aguaí)
    '/gastropub|petiscaria|lanchon|\bbar\b|beer|tabacaria|casa noturna|boate|\bclub\b|destilaria|lounge bar|panificadora|caf[ée]|torrefa|pra[çc]a de alimenta|churros|cachorro|padaria|doce|confeit/iu' => 'alimentacao',
    '/atacadista|especiarias|distribuidora|conveni[êe]ncia|cesta b[áa]sica|\bg[áa]s\b|[áa]gua mineral|empório|emporio/iu' => 'mercados',
    '/cirurgi|hospital|cl[íi]nico|pediatra|urologista|optometrista|massagista|pronto atendimento|primeiros socorros|psican|terapeut|pod[óo]log|depend[êe]ncia|sa[úu]de|farm[áa]cia|drogaria/iu' => 'saude',
    '/depila|bronzeamento|piercing|tatua|tattoo|barber|make\b|sal[ãa]o/iu' => 'beleza',
    '/yoga|nata[çc][ãa]o|artes marciais|personal trainer|academia|clube/iu' => 'esporte',
    '/aulas|escola|col[ée]gio|preparat|curso/iu' => 'educacao',
    '/f[áa]brica|fabricante|biotecnologia|finalista de metais|serraria|ferro-velho|ind[úu]stria/iu' => 'industria',
    '/mec[âa]nic|oficina|automotiv|autom[óo]ve|troca de [óo]leo|radiador|caminh|empilhadeira|lubrificante|motos|tratores|auto ?pe[çc]as|pneu/iu' => 'veiculos',
    '/concreteira|terraplenagem|ca[çc]amba|entulho|tijolo|toldo|ar[- ]condicionado|constru[çc]|manuten[çc][ãa]o predial|instala[çc][ãa]o el[ée]trica|tape[çc]aria|decora|forro|energia solar|serralheri|gesso|impermeabiliz|calhas|pintor|pinturas|hidr[áa]ulic|interiores|arquitet|engenh|topografia|viveiro|orquid|guindaste|im[óo]ve/iu' => 'casa',
    '/fazenda|agr[íi]col|agro|ra[çc][ãa]o|coop|fertilizante|peixes|s[íi]tio|ch[áa]cara/iu' => 'pet',
    '/pousada|casa de campo|temporada|cinema|hotel|eventos|festas|ch[áa]cara|chal[ée]|albergue|camping|hospedagem|parque de divers/iu' => 'hospedagem',
    '/igreja|templo|organiza[çc][ãa]o|ado[çc][ãa]o|associa[çc]|par[óo]quia|caridade|escoteiro/iu' => 'entidades',
    '/advog|tabeli|gr[áa]fica|despachante|rastreamento|sat[ée]lite|chaveiro|design|r[áa]dio|not[íi]cias|jornal|jornais|branding|certifica|loca[çc][õo]es|tecnologia|\btec\b|fretamento|[ôo]nibus|t[áa]xi|transporte|funer[áa]ria|reciclag|recicl[áa]veis|log[íi]stica|armaz[ée]ns|motorista|alfaiat|cart[óo]rio|fotogra|v[íi]deo|webdesign|computador|conserto|limpeza|fantasias|recursos humanos|empregos|empresarial|consultor|assistente social|toner|videogame/iu' => 'servicos',
    '/tecidos|estilista|ateli[êe]/iu' => 'moda',
    // Poços de Caldas (lista de 10/11)
    '/fonoaudi|ginecolog|casa de repouso|idosos|acupunt|c[âa]ncer|diagn[óo]stic|ortopt|sex[óo]log|aromaterapia|bioqu[íi]mica|pronto-socorro/iu' => 'saude',
    '/c[íi]lios|\\bspa\\b|bem-estar/iu' => 'beleza',
    '/cervejaria|sanduich|frango|batataria|produ[çc][ãa]o de comida/iu' => 'alimentacao',
    '/sacol[ãa]o|adega/iu' => 'mercados',
    '/pedreiro|jardin|reforma|construt|estucador|bancadas|caldeireiro|automa[çc][ãa]o de casas|estruturas met|antenas|ilumina|el[ée]tric/iu' => 'casa',
    '/c[âa]mbio|auditoria|holding/iu' => 'financeiro',
    '/motel|conven[çc][õo]es|parque aqu[áa]tico|termal|infl[áa]vel|cabine de fotos/iu' => 'hospedagem',
    '/conservat[óo]rio|professor|universidade/iu' => 'educacao',
    '/futebol|gin[áa]sio|motocross/iu' => 'esporte',
    '/beneficente|casa lar|centro comunit|assist[êe]ncia familiar|banco de alimentos/iu' => 'entidades',
    '/ve[íi]culos recreativos|concession[áa]ria|scooter|lava ?jato|inspe[çc][ãa]o de ve[íi]culos|seguran[çc]a para carros/iu' => 'veiculos',
    '/bordado|impress|convites|faixas|placas|tradu[çc]|lavanderia|organizador|carga|telegrama|mala direta|funer|empresas|produtor musical|ensaio|seguran[çc]a/iu' => 'servicos',
    '/costura|joalheir|kilts|brech[óo]/iu' => 'moda',
    '/sex shop|bazar|smartshop|eletr[ôo]nicos|ferragens|\\bshop\\b|comercial|descartados|api[áa]rio|sitiante/iu' => 'lojas',
    // Santa Rita do Sapucaí (lista de 11/11)
    '/delicatessen|bistr[ôo]|latic[íi]nio/iu' => 'alimentacao',
    '/cabelereir/iu' => 'beleza',
    '/m[óo]veis|aqu[áa]rio|livraria|distribuidor de papel|dep[óo]sito/iu' => 'lojas',
    '/aluguel de roupa/iu' => 'moda',
    '/publicidade|tv a cabo|entretenimento|artista/iu' => 'servicos',
    '/investimento/iu' => 'financeiro',
    '/pista de skate|centro aqu[áa]tico/iu' => 'esporte',
    '/teatro|centro de arte/iu' => 'hospedagem',
    '/loja|revistaria|relojoaria|fornecedor|com[ée]rcio|variedades|presentes|shopping|cell|celulares|embal/iu' => 'lojas',
];
// lugares públicos que vêm nas listas do Google e não são comércio (não entram no app). 20/11 (pedido do usuário): órgãos
// públicos, cemitérios, igrejas (destino religioso), escolas públicas e bibliotecas SAÍRAM daqui e entram (TIPO_AREA_RE em inc/limpeza.php)
const NAO_COMERCIO = '/^(ponto de [ôo]nibus|esta[çc][ãa]o de [ôo]nibus|esta[çc][ãa]o de (tratamento|pesagem).*|subesta[çc][ãa]o.*|esta[çc][ãa]o ferrovi[áa]ria.*|infraestrutura de transporte|companhia ferrovi[áa]ria|parque|parque (esportivo|rural)|playground|trilha.*|jardim|[áa]rea de (recrea[çc][ãa]o|escrit[óo]rios)|campo de futebol|est[áa]dio.*|gin[áa]sio poliesportivo|quadra de esportes|complexo (habitacional|de condom[íi]nio|de apartamentos)|apartamentos|desenvolvimento de moradias|sociedade de servi[çc]os habitacionais|pra[çc]a|represa|apartamento|complexo residencial|fonte de [áa]gua pot[áa]vel|companhia el[ée]trica|central el[ée]trica|marco hist[óo]rico|aeroporto|cachoeira|ponto panor[âa]mico|zool[óo]gico|palco|cal[çc]ad[ãa]o|centro de recrea[çc][ãa]o|ponto de coleta seletiva|casa|garagem|piscina p[úu]blica|estacionamento p[úu]blico|parque (ecol[óo]gico|municipal)|jardim comunit[áa]rio|marco cultural|posto de abastecimento de ve[íi]culos el[ée]tricos|companhia de saneamento|servi[çc]o de ambul[âa]ncia|museu|usina solar fotovoltaica)$/iu';
// linha sem tipo cujo nome é só bairro ou endereço (vem nas buscas do Google por região)
const SO_LUGAR = '/^(centro$|cidade nova$|santa maria$|vila \\S|jardim \\S|parque \\S|condom[íi]nio|pra[çc]a\\s|s[ãa]o \\S+$|santa \\S+$|dom bosco$|real$|vale d|cascata|mirante|campo d|est[âa]ncia|psf\\b|regi[ãa]o urbana|conj\\.? hab|r\\.\\s|rua\\s|av\\.?\\s|avenida\\s|esta[çc][ãa]o)/iu';
// cidade pelo endereço ("..., Águas da Prata - SP")
// 20/11: a que TERMINA por último e, empatando, a de nome mais longo ("Águas de São Pedro" ganha de "São Pedro", "Águas de
// Lindóia" de "Lindóia"); só nome inteiro ("Itu" não casa dentro de "Itupeva"). Antes era a que começava por último.
// Se o endereço tem "Cidade - UF", só vale o nome nessa posição: "R. Itu, 3, Itupeva - SP" fica com a cidade da lista,
// não vai para Itu. "Camanducaia - MG" = Monte Verde (distrito).
function cidade_do_endereco(string $e, string $padrao): string {
    $achada = $padrao; $fim = -1; $tam = 0;   // a que aparece por último ("..., Cidade - UF, CEP"); o começo pode citar outra cidade
    $com_uf = preg_match('/\p{L}\s+-\s+[A-Z]{2}\b/u', $e) === 1;
    $nomes = array_map(fn($c) => $c[0], TODAS_CIDADES);
    if (isset($nomes['monte-verde'])) $nomes['monte-verde|camanducaia'] = 'Camanducaia';   // distrito de Camanducaia (MG)
    foreach ($nomes as $slug => $nome) {
        $slug = explode('|', $slug)[0];
        $depois = $com_uf ? '(?=\s+-\s+[A-Z]{2}\b)' : '(?!\p{L})';
        if (!preg_match_all('/(?<!\p{L})' . preg_quote($nome, '/') . $depois . '/iu', $e, $m, PREG_OFFSET_CAPTURE)) continue;
        $f = end($m[0])[1] + strlen(end($m[0])[0]);
        if ($f > $fim || ($f === $fim && strlen($nome) > $tam)) { $achada = $slug; $fim = $f; $tam = strlen($nome); }
    }
    return $achada;
}

function limpo(string $s): string { return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8'))); }
function chave(string $nome): string {
    $s = mb_strtolower(limpo($nome));
    $s = strtr($s, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ü'=>'u','ç'=>'c']);
    return mb_substr(trim(preg_replace('/[^a-z0-9]+/', ' ', $s)), 0, 160);
}
// nomes das cidades (com ou sem acento) para cortar o fim do endereço
define('NOMES_NO_ENDERECO', implode('|', array_map(function ($c) {
    $t = preg_quote($c[0], '/');
    return strtr($t, ['á' => '[áa]', 'à' => '[àa]', 'â' => '[âa]', 'ã' => '[ãa]', 'é' => '[ée]', 'ê' => '[êe]', 'í' => '[íi]', 'ó' => '[óo]', 'ô' => '[ôo]', 'õ' => '[õo]', 'ú' => '[úu]', 'ç' => '[çc]', 'Á' => '[ÁA]', 'É' => '[ÉE]', 'Í' => '[ÍI]', 'Ó' => '[ÓO]', 'Ú' => '[ÚU]']);
}, TODAS_CIDADES)) . '|Camanducaia');   // 17/11: Monte Verde é distrito de Camanducaia (MG), o Google escreve as duas
/** Corta o endereço no nome da cidade (o 1º que abre um pedaço do endereço e não é bairro de outra cidade). */
function corta_cidade(string $e): string {
    // o nome fecha o pedaço: depois vem "-", UF, CEP, "," que não é número de casa ("Tiradentes, 115" é rua) ou o fim
    // ("São Sebastião da Serra" é bairro, não corta)
    if (!preg_match_all('/(?:^|,|\s-)\s*(' . NOMES_NO_ENDERECO . ')(?=\s*(?:,(?!\s*\d{1,4}(?!\d))|-|$|\d{5})|\s+(?-i:[A-Z]{2})\b)/iu', $e, $m, PREG_OFFSET_CAPTURE)) return $e;
    foreach ($m[0] as $i => [$todo, $ini]) {
        $resto = substr($e, $ini + strlen($todo));
        // no começo do endereço só é a cidade se vier logo UF, CEP ou "Brasil" ("Tiradentes - Centro, São José do Rio Pardo" é rua)
        if ($ini === 0 && !preg_match('/^\s*(?:[-,]?\s*(?-i:[A-Z]{2})\b|,?\s*\d{5}|,?\s*Brasil\b|$)/u', $resto)) continue;
        if (preg_match('/^\s*,\s*([^,]+?)\s+-\s+(?-i:[A-Z]{2})\b/u', $resto, $c) && chave($c[1]) !== chave($m[1][$i][0])
            && chave($c[1]) !== 'camanducaia') continue;   // é bairro: a cidade de verdade vem depois
        return substr($e, 0, $ini);
    }
    return $e;
}
// "Rua X, 410 - Jardim São Paulo, São João da Boa Vista - SP, 13875-089, Brasil" → ["Rua X, 410", "Jardim São Paulo"]
function endereco_e_bairro(string $e): array {
    // 17/11: só corta quando o nome da cidade abre um pedaço do endereço ("..., Monte Verde"), não no meio da rua ("Av. Monte Verde, 100")
    // 20/11: menos quando o nome é BAIRRO com nome de cidade cadastrada, seguido da cidade de verdade
    // ("- Tiradentes, Poços de Caldas - MG", "- São Pedro, Pedreira - SP"); "- Aguai, Aguaí - SP" continua cortando
    $e = corta_cidade($e);
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

// atributos extras na ordem que vier (data-seg, data-cid, data-ativo: o robô de 11/11 traz data-ativo="ativo|confirmar|inativo")
preg_match_all('#<tr data-status="([^"]*)" data-cat="([^"]*)"([^>]*)>(.*?)</tr>#s', $html, $linhas_html, PREG_SET_ORDER);
if (!$linhas_html) exit("✘ Nenhum comércio encontrado em $arquivo (formato mudou?)\n");

$novos = $atualizados = $pulados = $publicos = $fechados = $pausados = 0;
$vistos = [];
db()->beginTransaction();
$do_admin = editados_pelo_admin();   // 16/11: comércio editado à mão em /admin/comercio.php não é mudado pela lista
foreach ($linhas_html as [, $status, $tipo, $attrs, $tr]) {
    $area = preg_match('/data-seg="([^"]*)"/', $attrs, $x) ? $x[1] : '';
    $ativo = preg_match('/data-ativo="([^"]*)"/', $attrs, $x) ? $x[1] : 'ativo';
    // 16/11: ponto exato do Google, se o robô mandar (data-lat/data-lng; robô da API oficial com places.location)
    $lat_lista = preg_match('/data-lat="(-?\d+\.\d+)"/', $attrs, $x) ? (float)$x[1] : null;
    $lng_lista = preg_match('/data-lng="(-?\d+\.\d+)"/', $attrs, $x) ? (float)$x[1] : null;
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
    if ($nome === '' || $k === '' || !preg_match('/\pL/u', $nome)) { $pulados++; continue; }
    // mesmo nome em outro endereço = filial (redes de farmácia, mercado): entra com o endereço na chave.
    // A 1ª linha de cada nome continua com a chave antiga (acha quem já estava no app).
    if (isset($vistos[$k])) {
        if ($vistos[$k] === chave($endereco_completo)) { $pulados++; continue; }
        $k = mb_substr($k . ' @ ' . chave($endereco_completo), 0, 160);
        if (isset($vistos[$k])) { $pulados++; continue; }
    }
    $vistos[$k] = chave($endereco_completo);
    $mapa = html_entity_decode($m[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    [$endereco, $bairro] = endereco_e_bairro($endereco_completo);

    $telefone = normalizar_telefone(limpo($td[3]));
    $whatsapp = preg_match('#wa\.me/55(\d{10,11})#', $td[4], $w) ? $w[1] : null;
    $link = preg_match('#href="([^"]+)"#', $td[6], $l) ? html_entity_decode($l[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
    $instagram = $site = null;
    if ($link && preg_match('#instagram\.com/([A-Za-z0-9_.]{2,30})#', $link, $ig)) $instagram = $ig[1];
    elseif ($link && !in_array($status, ['fora_do_ar', 'site_fora'], true) && preg_match('#^https?://#', $link)) $site = mb_substr($link, 0, 200);
    $popularidade = preg_match('/\((\d+)\)/', limpo($td[7]), $p) ? (int)$p[1] : 0;

    $tipo = mb_substr(html_entity_decode($tipo, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 0, 80);
    if (mb_strtolower($tipo) === 'sem categoria') $tipo = '';
    $tipo = (string)limpar_tipo($tipo);   // 16/11: Bares → Bar etc. (inc/limpeza.php)
    // 19/11 (Brotas): bairro/endereço vem com tipo "Brotas - SP" (cidade - UF): não é comércio
    if ($tipo !== '' && (preg_match(NAO_COMERCIO, $tipo) || tipo_nao_comercio($tipo) || preg_match('/^[^,]+ - [A-Z]{2}$/u', $tipo))) { $pulados++; $publicos++; continue; }
    if (($tipo === '' && preg_match(SO_LUGAR, $nome)) || in_array(mb_strtolower($nome), array_map(fn($c) => mb_strtolower($c[0]), TODAS_CIDADES), true)) { $pulados++; $publicos++; continue; }
    // fechado no Google: não entra; se fechou de vez e já estava no app (importado, sem dono), sai do app (pausado)
    if ($ativo === 'inativo') {
        $fechados++; $pulados++;
        if (str_contains($tr, 'Fechado permanentemente')) {
            $ja = linha('SELECT id, importado, status, nome, endereco FROM profissionais WHERE chave_importacao = ?', [$k]);
            if ($ja && (int)$ja['importado'] === 1 && $ja['status'] === 'ativo' && !isset($do_admin[(int)$ja['id']])) {
                q("UPDATE profissionais SET status = 'pausado' WHERE id = ?", [$ja['id']]); $pausados++;
                $det['fecharam'][] = ['id' => (int)$ja['id'], 'nome' => $ja['nome'], 'endereco' => $ja['endereco']];
            }
        }
        continue;
    }
    $area = html_entity_decode((string)$area, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $categoria_id = isset($areas[$area]) ? ($cat[$areas[$area]] ?? $cat['outros']) : ($area_do_tipo[mb_strtolower($tipo)] ?? $cat['outros']);
    // "Outros" com tipo que claramente é de outra área (ex.: Pizzaria, Hamburgueria, Cabeleireiro)
    if ($categoria_id === $cat['outros']) {
        foreach (PALAVRAS_AREA as $re => $slug) if (preg_match($re, $tipo ?: $nome) && isset($cat[$slug])) { $categoria_id = $cat[$slug]; break; }
    }
    // 16/11: tipo que o robô põe na área errada (Despachante em Beleza...) e bairro limpo (inc/limpeza.php)
    if (($certa = area_certa_do_tipo($tipo)) && isset($cat[$certa])) $categoria_id = $cat[$certa];
    $bairro = limpar_bairro($bairro, $nome, $cidade);
    $nome = limpar_nome($nome);   // 19/11: "REDE DROGÃO" → "Rede Drogão", "Canhotos BAR" → "Canhotos Bar" (inc/limpeza.php)
    $dados = [
        'categoria_id' => $categoria_id,
        'tipo' => $tipo ?: null,
        'cidade' => $cidade,
        'nome' => $nome, 'telefone' => $telefone, 'whatsapp' => $whatsapp, 'instagram' => $instagram, 'site' => $site,
        'endereco' => $endereco, 'bairro' => $bairro, 'mapa' => $mapa && preg_match('#^https://(www\.google\.com/maps|maps\.google\.com/)#', $mapa) ? mb_substr($mapa, 0, 500) : null,
        'popularidade' => $popularidade,
    ];
    // coordenadas exatas: da lista (data-lat/lng) ou do link do Maps (!3d…!4d…, @lat,lng, query=lat,lng); só dentro do Brasil
    // (!3d…!4d… é o ponto do comércio; @lat,lng é só o centro do mapa: vem por último)
    if ($lat_lista === null) foreach (['/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', '/query=(-?\d+\.\d+)(?:,|%2C)(-?\d+\.\d+)/', '/@(-?\d+\.\d+),(-?\d+\.\d+)/'] as $re) {
        if (preg_match($re, (string)$mapa, $g)) { [$lat_lista, $lng_lista] = [(float)$g[1], (float)$g[2]]; break; }
    }
    if ($lat_lista !== null && $lng_lista !== null && $lat_lista > -34 && $lat_lista < 6 && $lng_lista > -74 && $lng_lista < -34)
        $dados += ['lat' => $lat_lista, 'lng' => $lng_lista, 'geo_origem' => 'google'];
    $atual = linha('SELECT id, importado, status, categoria_id, tipo, mapa, nome, telefone, whatsapp, endereco, bairro, instagram, site, geo_origem FROM profissionais WHERE chave_importacao = ?', [$k]);
    if (!$atual) {
        inserir('profissionais', $dados + ['chave_importacao' => $k, 'importado' => 1, 'status' => 'ativo', 'assinatura' => 'sem']);
        $novos++;
        $det['novos'][] = ['nome' => $nome, 'tipo' => $dados['tipo'], 'area' => array_search($categoria_id, $cat, true) ?: 'outros',
            'telefone' => $telefone, 'whatsapp' => $whatsapp, 'endereco' => $endereco, 'bairro' => $bairro, 'cidade' => $cidade,
            'mapa' => $dados['mapa'], 'popularidade' => $popularidade];
    } elseif ((int)$atual['importado'] === 1 && $atual['status'] === 'ativo' && !isset($do_admin[(int)$atual['id']])) {
        // lista nova mais pobre não estraga o que já estava bom: área/tipo sem informação e link do Maps sem coordenadas
        if ($dados['categoria_id'] === $cat['outros'] && (int)$atual['categoria_id'] !== $cat['outros']) { unset($dados['categoria_id']); $dados['tipo'] = $dados['tipo'] ?? $atual['tipo']; }
        if ($dados['tipo'] === null) unset($dados['tipo']);
        if ($dados['popularidade'] === 0) unset($dados['popularidade']);   // lista sem número de avaliações não zera a ordem
        if (str_contains((string)$atual['mapa'], '!3d') && !str_contains((string)$dados['mapa'], '!3d')) unset($dados['mapa']);
        if ($atual['geo_origem'] === 'manual') unset($dados['lat'], $dados['lng'], $dados['geo_origem']);   // ponto acertado à mão fica
        // o que mudou de verdade (sem contar maiúsculas/acentos): nome, telefone, WhatsApp, endereço, bairro, Instagram, site
        $campos = [];
        foreach (['nome', 'telefone', 'whatsapp', 'endereco', 'bairro', 'instagram', 'site'] as $cp) {
            $antes = trim((string)$atual[$cp]); $depois = trim((string)($dados[$cp] ?? ''));
            if ($depois !== '' && chave($antes) !== chave($depois)) $campos[$cp] = [$antes, $depois];
        }
        if ($campos) $det['mudaram'][] = ['id' => (int)$atual['id'], 'nome' => $atual['nome'], 'campos' => $campos];
        atualizar('profissionais', (int)$atual['id'], $dados);
        $atualizados++;
    } else {
        $pulados++; // dono assumiu o perfil, foi removido/pausado ou editado à mão pelo admin
    }
}
// resumo por área do que ficou nesta cidade (antes de confirmar ou desfazer)
$por_area = linhas('SELECT c.slug, COUNT(*) n FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE p.cidade = ? AND p.status = \'ativo\' GROUP BY c.slug ORDER BY n DESC', [$cidade_lista]);
$total = (int)valor('SELECT COUNT(*) FROM profissionais WHERE importado = 1 AND status = \'ativo\' AND cidade = ?', [$cidade_lista]);
$sem_area = $teste ? linhas("SELECT COALESCE(p.tipo, CONCAT('(sem tipo) ', p.nome)) t, COUNT(*) n FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
    WHERE p.cidade = ? AND p.status = 'ativo' AND c.slug = 'outros' GROUP BY t ORDER BY n DESC LIMIT 150", [$cidade_lista]) : [];
$teste ? db()->rollBack() : db()->commit();
if (!$teste) { require_once __DIR__ . '/inc/avisos-info.php'; avisar_infos_completas(); }   // passo 65
if ($detalhes_arq) file_put_contents($detalhes_arq, json_encode($det, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo ($teste ? "🧪 TESTE (nada gravado) " : '') . "✔ Leads de " . nome_cidade($cidade_lista) . ": $novos novos, $atualizados atualizados, $pulados pulados (repetidos, mantidos, $publicos lugares públicos, $fechados fechados no Google" . ($pausados ? ", $pausados que fecharam de vez saíram do app" : '') . "). No app: $total comércios importados nesta cidade.\n";
echo '  Por área: ' . implode(', ', array_map(fn($a) => "{$a['slug']} {$a['n']}", $por_area)) . "\n";
if ($sem_area) echo '  Em "Outros": ' . implode(' | ', array_map(fn($a) => "{$a['t']} {$a['n']}", $sem_area)) . "\n";
