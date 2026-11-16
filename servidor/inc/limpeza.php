<?php
// Limpeza de bairros e tipos (16/11, pedido do usuário). Usada pelo importador (importar-leads.php) em toda lista nova
// e por limpar-dados.php para o que já está no banco. Só vale para fichas importadas SEM dono: dado que o dono
// mandou (e o admin aprovou) nunca é mexido. É limpeza, não dado novo: nada é inventado, só tirado ou padronizado.
declare(strict_types=1);

/** Tira acentos e deixa minúsculo ("Jardim São Paulo" → "jardim sao paulo"), para comparar variantes. */
function chave_texto(string $s): string {
    $s = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s)));
    return strtr($s, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e', 'í' => 'i', 'î' => 'i',
        'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', '-' => ' ']);
}

/** "JARDIM EUROPA" → "Jardim Europa"; "vila DOS pinheiros" → "Vila dos Pinheiros". */
function titulo_br(string $s): string {
    $s = mb_convert_case(mb_strtolower($s), MB_CASE_TITLE);
    $s = preg_replace_callback('/(?<=\s)(De|Da|Do|Das|Dos|E)(?=\s)/u', fn($m) => mb_strtolower($m[1]), $s);
    return preg_replace_callback('/\b(Ii|Iii|Iv|Vi|Vii|Viii|Ix|Xi|Xii)\b/u', fn($m) => mb_strtoupper($m[1]), $s);   // algarismos romanos
}

/**
 * Bairro limpo, ou null quando o que veio não é bairro: CEP, rua, "Bloco II", "Em frente à Prefeitura", nome de
 * comércio, "Região Urbana Homogênea IV" (nome oficial que ninguém usa em Poços)...
 * Também padroniza abreviações (Jd. → Jardim, Pq → Parque) e TUDO MAIÚSCULO.
 */
function limpar_bairro(?string $b, string $nome_comercio = '', string $cidade = ''): ?string {
    if ($b === null) return null;
    $b = trim(preg_replace('/\s+/u', ' ', $b), " ,-–;:");
    $b = preg_replace('/^bairro:?\s+(?!(de|da|do|das|dos)\b)/iu', '', $b);   // "Bairro Buenos Aires" → "Buenos Aires"; "Bairro do Lote" fica
    $b = trim(preg_replace('/\s*\([^)]*\)\s*/u', ' ', $b));   // "Jardim Satélite (Centro)" → "Jardim Satélite"
    if (in_array(chave_texto($b), ['zona rural', 'rural', 'area rural'], true)) return 'Zona Rural';
    if (in_array(chave_texto($b), ['casa', 'centro comercial', 'sem bairro', 'nao informado', 'outros', 'dos', 'das', 'res.', 'res'], true)) return null;
    if (mb_strlen($b) < 3 || mb_strlen($b) > 60) return null;
    // não é bairro
    if (preg_match('/\bcep\b|^\d|regi[ãa]o urbana homog|[,\/:;()@]|\d{3,}/iu', $b)) return null;
    if (preg_match('/^(r|rua|av|avenida|avnida|al|alameda|rod|rodovia|estr|estrada|tv|travessa|pra[çc]a|p[çc]a|largo|via|km|sl|sala)\b\.?/iu', $b)) return null;
    if (preg_match('/\b(bloco|loja|apto|apartamento|box|galp[ãa]o|edif[íi]cio|em frente|ao lado|do lado|pr[óo]ximo|dentro|refer[êe]ncia|shopping|mall|galeria|esquina|fundos|andar|t[ée]rreo|n[º°]|cl[íi]nica|despachante|salgad\w*|lanchonete|restaurante|academia|mec[âa]nica|est[úu]dio|espa[çc]o|unimed|farm[áa]cia|escola|igreja|posto|street|rotat[óo]ri\w*|estacionamento|prefeitura|santu[áa]rio)\b/iu', $b)) return null;
    // tudo minúsculo ("centro"): vale só se a cidade já tem esse bairro escrito direito; senão é pedaço de rua ou nome de pessoa
    if (!preg_match('/\p{Lu}/u', $b)) {
        if ($cidade !== '' && isset(bairros_da_cidade($cidade)[chave_texto($b)])) return bairros_da_cidade($cidade)[chave_texto($b)];
        // "vila paulista", "jardim mercedes": começa como bairro → só arruma as maiúsculas
        return preg_match('/^(jardim|vila|parque|portal|vista|residencial|recanto|conjunto|chácara|chacara|sítio|sitio|alto|bairro|nova|novo|cidade|centro|são|sao|santa|santo|nossa)\b/iu', $b) ? titulo_br($b) : null;
    }
    if ($nome_comercio !== '' && chave_texto($b) === chave_texto($nome_comercio)) return null;
    if ($cidade !== '' && isset(TODAS_CIDADES[$cidade]) && chave_texto($b) === chave_texto(TODAS_CIDADES[$cidade][0])) return null;   // o nome da própria cidade não é bairro
    // padroniza
    if (!preg_match('/\p{Ll}/u', $b) && (str_contains($b, ' ') || mb_strlen($b) > 5)) $b = titulo_br($b);   // TUDO MAIÚSCULO (sigla curta como DER e CECAP fica)
    $b = preg_replace(['/^jd\.?\s+/iu', '/^jar\.?\s+/iu', '/^pq\.?\s+/iu', '/^vl\.?\s+/iu', '/^v\.\s*/u', '/^v\s+(?=\p{Lu})/u', '/^res\.?\s+/iu', '/^resid\.?\s+/iu',
        '/^conj\.?\s*hab\.?\s+/iu', '/^cj\.?\s+/iu', '/^chac\.?\s+/iu', '/^st\.?\s+/iu'],
        ['Jardim ', 'Jardim ', 'Parque ', 'Vila ', 'Vila ', 'Vila ', 'Residencial ', 'Residencial ', 'Conjunto Habitacional ', 'Conjunto ', 'Chácara ', 'Sítio '], $b);
    return $b;
}

// Tipos com nomes repetidos (plural, maiúscula, hífen): o da esquerda vira o da direita.
const TIPO_IGUAL = [
    'Bares' => 'Bar', 'Loja de roupas' => 'Loja de Roupa', 'Loja de calçados' => 'Loja de calçado',
    'Fabricante de cerâmica' => 'Fabricante de cerâmicas', 'Ferro velho' => 'Ferro-velho', 'Loja de cortinas' => 'Loja de Cortina',
    'Assistência Técnica' => 'Assistência técnica', 'Pizza' => 'Pizzaria', 'Café' => 'Cafeteria', 'Cosméticos' => 'Loja de cosmético',
];

// Tipo que caiu na área errada na lista do robô → área certa (slug). Vale para listas novas e para o que já está no banco.
const TIPO_AREA_CERTA = [
    // estavam em Alimentação
    'Treinador de cachorros' => 'pet', 'Centro de massagem' => 'beleza', 'Cama e café' => 'hospedagem', 'Buffet infantil' => 'hospedagem',
    'Loja de churrasqueiras' => 'lojas', 'Lan House e Cyber café' => 'servicos', 'Tabacaria' => 'lojas',
    // Beleza
    'Despachante' => 'veiculos', 'Espaço de coworking' => 'servicos', 'Salão de baile' => 'hospedagem',
    'Salão do Reino das Testemunhas de Jeová' => 'entidades',
    // Casa e construção
    'Autoelétrico' => 'veiculos', 'Serviço de limpeza completa de automóveis' => 'veiculos', 'Serviço de reconstrução de motores' => 'veiculos',
    'Loja de acessórios para dispositivos móveis' => 'lojas', 'Consultora de imóveis' => 'servicos',
    // Educação
    'Assessoria de recursos humanos' => 'servicos', 'Editora' => 'servicos',
    // Lojas
    'Salão de festas' => 'hospedagem', 'Estúdio de fotografia' => 'servicos', 'Serviços para festas infantis' => 'hospedagem',
    'Serviço de locação de artigos de festa' => 'hospedagem', 'Escola de informática' => 'educacao', 'Loja de sucos' => 'alimentacao',
    // Mercados
    'Distribuidor de aço' => 'industria', 'Mercado de roupas' => 'moda', 'Atacadista de móveis' => 'lojas',
    // Outros
    'Osteopata' => 'saude', 'Endocrinologista' => 'saude', 'Reumatologista' => 'saude', 'Imunologista' => 'saude', 'Ambulatório' => 'saude',
    'Cuidados com os pés' => 'saude', 'Lotérica' => 'financeiro', 'Cobrança de dívidas' => 'financeiro', 'Casa funerária' => 'servicos',
    'Centro de Reciclagem' => 'servicos', 'Correios' => 'servicos', 'Serviços gráficos' => 'servicos', 'Serigrafia' => 'servicos',
    'Assistência jurídica' => 'servicos', 'Recrutamento' => 'servicos', 'Serviços de faxina' => 'servicos',
    'Agência de viagens a pontos turísticos' => 'servicos', 'Operador de turismo' => 'servicos', 'Agência de locação de vans' => 'servicos',
    'Pensão' => 'hospedagem', 'Hostel' => 'hospedagem', 'Local para casamentos' => 'hospedagem', 'Desentupidora' => 'casa',
    'Serviço de acabamento de pisos' => 'casa', 'Creperia' => 'alimentacao', 'Patisserie' => 'alimentacao', 'Pub irlandês' => 'alimentacao',
    'Charutaria' => 'lojas', 'Boliche' => 'esporte', 'Centro de equitação' => 'esporte', 'Canil' => 'pet', 'Criador de cavalos' => 'pet',
    'Avícola' => 'pet', 'Ourives' => 'moda', 'Revendedor de barcos' => 'veiculos', 'Estacionamento para motocicletas' => 'veiculos',
    'Ferro-velho' => 'industria',
    // Pet e agro
    'Atração turística' => 'outros', 'Companhia de Mineração' => 'industria', 'Serviço de restauração de móveis antigos' => 'casa',
    'Serviço de escrituração contábil' => 'servicos', 'Serviço de declaração de impostos' => 'servicos',
    'Serviço de administração de propriedades' => 'servicos', 'Loja de materiais para preparação de cerveja' => 'lojas',
    'Agência de aluguel de apartamentos de curta duração' => 'hospedagem', 'Hotel fazenda' => 'hospedagem', 'Assistência técnica' => 'servicos',
    // Outros (16/11, 2ª rodada)
    'Vinícola' => 'alimentacao', 'Vinhedo' => 'alimentacao', 'Local com música ao vivo' => 'alimentacao', 'Karaokê' => 'alimentacao',
    'Loteamento de terrenos' => 'servicos', 'Serviço de gerenciamento de descartes' => 'servicos', 'Estúdio de televisão' => 'servicos',
    'Centro de coaching' => 'servicos', 'Agência de passeios de barco' => 'servicos', 'Serviço de tratamento de água e esgoto' => 'servicos',
    'Serviço de corte a laser' => 'servicos', 'Fotógrafo aéreo' => 'servicos', 'Casa de leilões' => 'servicos', 'Serviço de contabilidade de custos' => 'servicos',
    'Aluguel de quadriciclo' => 'esporte', 'Programa de condicionamento físico' => 'esporte', 'Centro de esportes de aventura' => 'esporte',
    'Aula de aerodança' => 'esporte', 'Agricultura' => 'pet', 'Selaria' => 'pet', 'Empresa de cultivo de orquídeas' => 'pet',
    'Feira de artesanato' => 'lojas', 'Compras' => 'lojas', 'Outlet' => 'lojas', 'Central de artesanato' => 'lojas', 'Antiquário' => 'lojas',
    'Locadora de filmes' => 'lojas', 'Galeria de Arte' => 'lojas', 'Casa especializada em frutas' => 'mercados',
    'Centro de exposições' => 'hospedagem', 'Rancho turístico' => 'hospedagem', 'Lago de pesca' => 'hospedagem', 'Acampamento de pesca' => 'hospedagem',
    'Dude Ranch' => 'hospedagem', 'Serviço de aluguel de tendas' => 'hospedagem', 'Centro de diversões infantil' => 'hospedagem',
    'Serviço de Aluguel de Equipamentos Audiovisuais' => 'hospedagem', 'Acampamento de verão' => 'hospedagem', 'Campo de férias' => 'hospedagem',
    'Retiro' => 'hospedagem', 'Teatro' => 'hospedagem', 'Fundição' => 'industria', 'Processamento de frutas e legumes' => 'industria',
    'Laboratório de química' => 'industria', 'Curtume' => 'industria', 'Planta química' => 'industria', 'Empresa de aquecedores' => 'casa',
    'Empresa de purificação de água' => 'casa', 'Serviço de locação de ferramentas' => 'casa', 'Serviço de poda' => 'casa',
    'Avaliador de joias' => 'moda', 'Empresa de aluguel de vestidos e smokings' => 'moda', 'Serviço de hipnoterapia' => 'saude',
    'Oncologista' => 'saude', 'Endoscopista' => 'saude', 'Hematologista' => 'saude', 'Proctologista' => 'saude', 'Homeopatia' => 'saude',
    'Banco de sangue' => 'saude', 'Centro de imagens por ressonância magnética' => 'saude', 'Terapia ocupacional' => 'saude', 'Doula' => 'saude',
    'Consultório de enfermagem' => 'saude',
    // Saúde, Serviços, Veículos, Hospedagem
    'Farmácia veterinária' => 'pet', 'Alfaiate' => 'moda', 'Lavanderia automática' => 'servicos',
    'Empresa de Automação Empresarial' => 'servicos', 'Oficina de costura' => 'moda', 'Empresa de hospedagem na Web' => 'servicos',
];

// Não é comércio (lugar público, prédio, ponte...): a ficha importada sai do app (status pausado; o admin pode voltar).
const TIPO_NAO_COMERCIO = '/^(ponte|complexo (de condom[íi]nio|residencial|de apartamentos)|pres[íi]dio|reparti[çc][ãa]o p[úu]blica( federal)?|'
    . 'banheiro p[úu]blico|base militar|cemit[ée]rio|escrit[óo]rio do governo.*|drinking water)$/iu';   // só tipo que nunca é comércio
function tipo_nao_comercio(?string $tipo): bool { return $tipo !== null && preg_match(TIPO_NAO_COMERCIO, trim($tipo)) === 1; }

/** Tipo padronizado (Bares → Bar); endereço no lugar do tipo ("R. Tabapuan, 12") vira vazio. */
function limpar_tipo(?string $tipo): ?string {
    if ($tipo === null || trim($tipo) === '') return $tipo;
    if (preg_match('/^(r|rua|av|avenida|al|alameda|rod|rodovia|estr|estrada|tv|travessa)\b\.?\s/iu', trim($tipo))) return null;
    return TIPO_IGUAL[trim($tipo)] ?? trim($tipo);
}
/** Slug da área certa para o tipo, ou null se o tipo não está na lista de correções. */
function area_certa_do_tipo(?string $tipo): ?string {
    return $tipo !== null ? (TIPO_AREA_CERTA[trim($tipo)] ?? null) : null;
}

/** Bairros já escritos direito na cidade (com maiúscula), do mais usado: chave sem acento → como escrever. */
function bairros_da_cidade(string $cidade): array {
    static $cache = [];
    if (isset($cache[$cidade])) return $cache[$cidade];
    $r = [];
    foreach (linhas("SELECT bairro, COUNT(*) AS n FROM profissionais WHERE cidade = ? AND bairro IS NOT NULL AND bairro <> ''
        GROUP BY bairro ORDER BY n DESC", [$cidade]) as $l) {
        if (preg_match('/\p{Lu}/u', $l['bairro'])) $r[chave_texto($l['bairro'])] ??= $l['bairro'];
    }
    return $cache[$cidade] = $r;
}

/** Bairro que só aparece numa ficha da cidade e não tem cara de bairro (Jardim, Vila, Parque...): quase sempre sujeira. */
function bairro_raro_suspeito(string $b): bool {
    return !preg_match('/^(jardim|jardins|vila|parque|residencial|recanto|conjunto|ch[áa]cara|ch[áa]caras|s[íi]tio|bairro|alto|nova|novo|portal|vista|'
        . 'cidade|n[úu]cleo|loteamento|distrito|condom[íi]nio|est[âa]ncia|morada|vale|bosque|s[ãa]o|santa|santo|nossa|centro|zona|cohab|cdhu|'
        . 'setor|granja|quinta|colina|colinas|solar|terras|village|cecap|conj|res|lot|jd|pq|vl|jardim|ilha|lagoa|serra|morro|praia|campo|'
        . 'sert[ãa]o|fazenda|rio|monte|boa|bela|belo|vale|reserva|cond|ch|c[óo]rrego|ribeir[ãa]o|cap[ãa]o|tanque|pinheiros?|'
        // bairros comuns de uma palavra só (não confundir com nome de pessoa ou de rua)
        . 'alvorada|planalto|primavera|industrial|cachoeira|fortaleza|para[íi]so|esperan[çc]a|aeroporto|universit[áa]rio|liberdade|'
        . 'independ[êe]ncia|progresso|recreio|itamaraty|itamarati|palmeiral|resedas|concei[çc][ãa]o|ros[áa]rio|aparecida|cruzeiro|esta[çc][ãa]o|'
        . 'ferrovi[áa]rio|matadouro|baix[ãa]o|ip[êe]|ipiranga|lavap[ée]s|vit[óo]ria|bonfim|capela|cascata|cascatinha|chapad[ãa]o|'
        . 'mirante|panorama|horizonte|bandeirantes|tropical|europa|am[ée]rica|brasil|eldorado|bnh)\b/iu', $b);
}
