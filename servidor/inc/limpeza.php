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
    if (!mb_check_encoding($b, 'UTF-8')) $b = mb_convert_encoding($b, 'UTF-8', 'UTF-8');   // texto quebrado não derruba a importação
    // 20/11: sem trim(..., "–"): o trim corta byte a byte e o "–" tem o mesmo byte final do "Ó" ("JARDIM ITAPÓ" quebrava)
    $b = preg_replace('/^[\s,\-–;:]+|[\s,\-–;:]+$/u', '', (string)preg_replace('/\s+/u', ' ', $b));
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
    return acentuar_bairro($b);
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
    'Centro de Reciclagem' => 'servicos', 'Correios' => 'publicos', 'Serviços gráficos' => 'servicos', 'Serigrafia' => 'servicos',
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
// 20/11 (pedido do usuário): igrejas, cemitérios, velórios e órgãos públicos entram no app, cada um na sua área
// (vale para listas novas e para o limpar-dados.php). Área "Órgãos públicos" = slug publicos.
const TIPO_AREA_RE = [
    '/^(prefeitura.*|c[âa]mara (municipal|de vereadores).*|reparti[çc][ãa]o p[úu]blica.*|escrit[óo]rio do governo.*|prestador de servi[çc]os p[úu]blicos|'
        . 'delegacia.*|departamento de pol[íi]cia|pol[íi]cia.*|posto policial|base comunit[áa]ria.*|(corpo de )?bombeiros|tribunal.*|f[óo]rum.*|'
        . 'departamento de tr[âa]nsito|detran|departamento de ve[íi]culos automotores|minist[ée]rio p[úu]blico|defensoria p[úu]blica|'
        . 'secretaria.*|previd[êe]ncia social|ag[êe]ncia do inss|inss|poupatempo|conselho tutelar|cras|creas|'
        . 'departamento (de registro de domic[íi]lios|do meio ambiente|fiscal|municipal.*|de obras.*|de [áa]gua.*)|'
        . 'escrit[óo]rio de recrutamento militar|junta militar|quartel.*|correios|ag[êe]ncia dos correios|'
        . 'centro de informa[çc][ãa]o ao turista|biblioteca( p[úu]blica| municipal)?|cart[óo]rio eleitoral)$/iu' => 'publicos',
    '/^(cemit[ée]rio.*|vel[óo]rio.*|cremat[óo]rio|capela mortu[áa]ria|casa funer[áa]ria|funer[áa]ria|agente funer[áa]rio.*)$/iu' => 'servicos',
    '/^(igreja.*|templo.*|par[óo]quia|santu[áa]rio.*|bas[íi]lica|catedral|capela|destino religioso|congrega[çc][ãa]o|centro esp[íi]rita|'
        . 'terreiro.*|casa de ora[çc][ãa]o|mesquita|sinagoga|mosteiro|convento)$/iu' => 'entidades',
];
// 20/11 (pedido do usuário): repartição pública, escritório do governo e cemitério ENTRAM no app (TIPO_AREA_RE abaixo)
const TIPO_NAO_COMERCIO = '/^(ponte|complexo (de condom[íi]nio|residencial|de apartamentos)|pres[íi]dio|'
    . 'banheiro p[úu]blico|base militar|drinking water)$/iu';   // só tipo que nunca é comércio
function tipo_nao_comercio(?string $tipo): bool { return $tipo !== null && preg_match(TIPO_NAO_COMERCIO, trim($tipo)) === 1; }

/** Tipo padronizado (Bares → Bar); endereço no lugar do tipo ("R. Tabapuan, 12") vira vazio. */
function limpar_tipo(?string $tipo): ?string {
    if ($tipo === null || trim($tipo) === '') return $tipo;
    if (preg_match('/^(r|rua|av|avenida|al|alameda|rod|rodovia|estr|estrada|tv|travessa)\b\.?\s/iu', trim($tipo))) return null;
    return TIPO_IGUAL[trim($tipo)] ?? trim($tipo);
}
/** Slug da área certa para o tipo, ou null se o tipo não está na lista de correções. */
function area_certa_do_tipo(?string $tipo): ?string {
    if ($tipo === null) return null;
    if (isset(TIPO_AREA_CERTA[trim($tipo)])) return TIPO_AREA_CERTA[trim($tipo)];
    foreach (TIPO_AREA_RE as $re => $slug) if (preg_match($re, trim($tipo))) return $slug;
    return null;
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

// Palavras comuns de bairro escritas sem acento (ou com outra grafia) → forma certa. Só palavra inteira com maiúscula.
const BAIRRO_ACENTOS = [
    'Sao' => 'São', 'Jose' => 'José', 'Joao' => 'João', 'Antonio' => 'Antônio', 'Conceicao' => 'Conceição', 'Paraiso' => 'Paraíso',
    'Esperanca' => 'Esperança', 'Centenario' => 'Centenário', 'Ipe' => 'Ipê', 'Ipes' => 'Ipês', 'Sebastiao' => 'Sebastião', 'Lucia' => 'Lúcia',
    'Cecilia' => 'Cecília', 'Emilia' => 'Emília', 'Vitoria' => 'Vitória', 'Gloria' => 'Glória', 'Patricia' => 'Patrícia',
    'America' => 'América', 'Italia' => 'Itália', 'Brasilia' => 'Brasília', 'Lavinia' => 'Lavínia', 'Paraiba' => 'Paraíba',
    'Mario' => 'Mário', 'Marcia' => 'Márcia', 'Fatima' => 'Fátima', 'Bras' => 'Brás', 'Braz' => 'Brás', 'Inacio' => 'Inácio',
    'Mauricio' => 'Maurício', 'Cristovao' => 'Cristóvão', 'Estancia' => 'Estância', 'Aurea' => 'Áurea', 'Ceu' => 'Céu',
    'Galvao' => 'Galvão', 'Simao' => 'Simão', 'Monica' => 'Mônica', 'Imperio' => 'Império', 'Chacara' => 'Chácara',
    'Chacaras' => 'Chácaras', 'Sitio' => 'Sítio', 'Recanto' => 'Recanto', 'Patio' => 'Pátio', 'Aguas' => 'Águas', 'Agua' => 'Água',
    'Rosario' => 'Rosário', 'Belem' => 'Belém', 'Jardins' => 'Jardins', 'Eden' => 'Éden', 'Pinheirao' => 'Pinheirão',
    'Matao' => 'Matão', 'Capao' => 'Capão', 'Ribeirao' => 'Ribeirão', 'Corrego' => 'Córrego', 'Planalto' => 'Planalto',
    'Uniao' => 'União', 'Independencia' => 'Independência', 'Aviacao' => 'Aviação', 'Estacao' => 'Estação', 'Industria' => 'Indústria',
    'Universitario' => 'Universitário', 'Lazaro' => 'Lázaro', 'Benedito' => 'Benedito', 'Leticia' => 'Letícia', 'Julia' => 'Júlia',
    'Claudia' => 'Cláudia', 'Lidia' => 'Lídia', 'Amelia' => 'Amélia', 'Alvaro' => 'Álvaro', 'Angelica' => 'Angélica',
    'Monte' => 'Monte', 'Sinha' => 'Sinhá', 'Pantano' => 'Pântano', 'Ze' => 'Zé', 'Tome' => 'Tomé', 'Nazare' => 'Nazaré',
];
/** "Jardim Sao Jose" → "Jardim São José"; "Braz" → "Brás". */
function acentuar_bairro(string $b): string {
    return preg_replace_callback('/\b\p{Lu}\p{Ll}+\b/u', fn($m) => BAIRRO_ACENTOS[$m[0]] ?? $m[0], $b);
}

// ---------- nomes em caixa irregular (19/11, avaliação do site: "Canhotos BAR", "Point Vip Sjbv", "REDE DROGÃO") ----------

/** Siglas que ficam em maiúsculas (também corrige "Sjbv" → "SJBV"). */
const NOME_SIGLAS = ['SJBV', 'EMEB', 'EMEF', 'EMEI', 'ETEC', 'FATEC', 'APAE', 'SENAI', 'SESI', 'SENAC', 'SEBRAE', 'SESC', 'UBS', 'UPA',
    'CRAS', 'CREAS', 'CCAA', 'CNA', 'CFC', 'OAB', 'CDL', 'ACIA', 'ACE', 'INSS', 'CEU', 'SAAE', 'DAE', 'DER', 'IPTU', 'PUC', 'USP',
    'UNIFEOB', 'UNIFAE', 'CNPJ', 'CPF', 'RPG', 'DJ', 'TV', 'GNV', 'GLP', 'ATM', 'BMW', 'VW', 'GM', 'SP', 'MG', 'RJ', 'PR', 'CEP', 'EPI', 'EPP',
    'MEI', 'LTDA', 'EIRELI', 'CEI', 'CIEP', 'CEMEI', 'SOS'];
/** Palavras curtas comuns: num nome TODO EM MAIÚSCULAS não são tratadas como sigla ("ALÔ PIZZA" → "Alô Pizza"). */
const NOME_CURTAS_COMUNS = ['alô', 'alo', 'top', 'mix', 'vai', 'bem', 'sol', 'lar', 'pão', 'pao', 'céu', 'rei', 'boa', 'bom', 'mar', 'luz',
    'sul', 'lua', 'paz', 'fé', 'pé', 'lá', 'já', 'tô', 'eu', 'tu', 'ti', 'só', 'nós', 'meu', 'seu', 'sua', 'mãe', 'pai', 'avó', 'avô', 'tia',
    'tio', 'zé', 'joão', 'ana', 'léo', 'leo', 'bia', 'gás', 'gas', 'chá', 'cha', 'fit', 'pet', 'bar', 'spa', 'art', 'fox', 'pop', 'big',
    'new', 'one', 'the', 'max', 'kid', 'mel', 'oca', 'bis', 'ok', 'ki', 'dr', 'dra', 'sr', 'sra', 'são', 'sao', 'via', 'ver', 'uai', 'oi',
    'ar', 'os', 'as', 'ao', 'um', 'ou', 'me', 'te', 'se', 'lu', 'jô', 'bel', 'gil', 'rui', 'ivo', 'eva', 'liz', 'mia', 'nil', 'tom', 'zen',
    'jr', 'sta', 'sto', 'up', 'la', 'du', 'dú', 're', 'by', 'ju', 'ai', 'ed', 'ze', 'del', 'pri', 'all', 'kay', 'joy', 'ypê', 'ype', 'cão', 'cao', 'pão', 'pra', 'pro'];
/** Siglas que também são palavra ("Céu", "Ace"): só ficam em maiúsculas quando o nome inteiro já veio assim. */
const NOME_SIGLAS_SO_MAIUSC = ['SP', 'MG', 'RJ', 'PR', 'EPP', 'LTDA', 'EIRELI', 'ACE', 'CEU', 'DER', 'DAE', 'MEI'];

/** Palavras comuns (tipos de negócio, áreas, cidades e as do dia a dia): só essas saem de MAIÚSCULAS num nome misto. */
function nome_vocabulario(): array {
    static $v = null;
    if ($v !== null) return $v;
    $v = [];
    $textos = array_merge(
        array_column(linhas('SELECT DISTINCT tipo FROM profissionais WHERE tipo IS NOT NULL'), 'tipo'),
        array_column(linhas('SELECT nome FROM categorias'), 'nome'),
        array_map(fn($c) => $c[0], TODAS_CIDADES),
        ['loja lojas moda modas store decor decoração lanches lanche bar bares pastelaria salgaderia salgados doces bolos sobrancelhas cílios
          corretor corretora imóveis espaço passeios fisioterapia terapia ocupacional especializada especialista distribuidora baterias
          provedor internet via radio rádio locação equipamentos empreendimentos medicina nuclear refrigeração conveniência pronto quero
          mais casa nova novo boa bom são santa santo vila jardim centro filial matriz unidade cia família irmãos filhos auto peças
          serviços serviço comércio indústria artesanal caseira caseiro gastronomia multimarcas planejados funilaria pintura banho tosa
          rações sucos bebidas marmitaria marmitex restaurante churrascaria pizzaria padaria açougue mercado supermercado hortifruti
          farmácia drogaria ótica óculos academia studio estúdio salão beleza barbearia oficina mecânica elétrica escola colégio clínica
          consultório odontologia dentista advocacia advogados contabilidade imobiliária hotel pousada hostel chalés buffet festas eventos
          flores floricultura pet shop veterinária papelaria livraria móveis colchões tintas materiais construção vidraçaria serralheria
          calçados roupas infantil feminina masculina acessórios presentes variedades utilidades celulares informática assistência técnica
          pra tia tio vovó vovô'],
    );
    foreach ($textos as $t) foreach (preg_split('/[^\p{L}]+/u', (string)$t, -1, PREG_SPLIT_NO_EMPTY) as $p)
        if (mb_strlen($p) >= 3 && $p !== mb_strtoupper($p)) $v[mb_strtolower($p)] = true;   // sigla nos tipos (EMDR) não entra
    foreach (['de', 'da', 'do', 'das', 'dos', 'e', 'em', 'a', 'o', 'na', 'no', 'para', 'com'] as $p) $v[$p] = true;
    return $v;
}

/** Uma palavra em caixa de título, com os ajustes do português (de/da/do minúsculos, D'Jesus, algarismos romanos). */
function nome_palavra(string $w, bool $primeira, bool $ligacao = true): string {
    $baixa = mb_strtolower($w);
    if (!$primeira && $ligacao && in_array($baixa, ['de', 'da', 'do', 'das', 'dos', 'e', 'em', 'na', 'no', 'para', 'com', 'a', 'o'], true)) return $baixa;
    if (preg_match('/^(i|ii|iii|iv|vi|vii|viii|ix|xi|xii)$/u', $baixa)) return mb_strtoupper($w);
    $t = mb_convert_case($baixa, MB_CASE_TITLE);
    // apóstrofo: 1 letra depois fica minúscula (Deca's), mais letras ficam com maiúscula (D'Arc, Di'Ana)
    return preg_replace_callback("/'(\p{L}+)/u", fn($m) => "'" . (mb_strlen($m[1]) === 1 ? mb_strtolower($m[1]) : mb_convert_case($m[1], MB_CASE_TITLE)), $t);
}

/**
 * Nome com a caixa arrumada, ou o mesmo nome quando não precisa (ou quando é arriscado mexer):
 *  - TUDO MAIÚSCULO ou tudo minúsculo → "Rede Drogão", "Farmácia Popular" (siglas conhecidas e palavras sem vogal, como
 *    JMJ, ficam em maiúsculas);
 *  - nome misto: só a palavra comum em MAIÚSCULAS vira normal ("Canhotos BAR" → "Canhotos Bar"); marca em maiúsculas
 *    (FLAGRA, APPLE, LARALU) fica como está;
 *  - sigla conhecida escrita errada vira maiúscula ("Point Vip Sjbv" → "Point Vip SJBV").
 */
function limpar_nome(string $nome): string {
    $nome = trim(preg_replace('/\s+/u', ' ', $nome));
    if ($nome === '') return $nome;
    $letras = preg_replace('/[^\p{L}]/u', '', $nome);
    $tudo_maiusc = $letras !== '' && $letras === mb_strtoupper($letras) && mb_strlen($letras) >= 4;
    $tudo_minusc = $letras !== '' && $letras === mb_strtolower($letras) && mb_strlen($letras) >= 3;
    if (preg_match('/\p{L}\.\p{L}{2,}(\s|$)/u', $nome) && !str_contains(trim($nome), ' ')) return $nome;   // gu.car, 2solv.ai
    $voc = nome_vocabulario();
    $partes = preg_split('/(\s+|[\-\/|&(),.:+]+)/u', $nome, -1, PREG_SPLIT_DELIM_CAPTURE);
    $primeira = true;
    $ligacao_ok = fn(int $i) => in_array(mb_strtolower($partes[$i]), ['de', 'da', 'do', 'das', 'dos', 'e', 'em', 'na', 'no', 'a', 'o', 'com'], true);
    foreach ($partes as $i => $w) {
        if ($w === '' || !preg_match('/\p{L}/u', $w)) continue;   // separador ou número
        $sobe = mb_strtoupper($w);
        $sigla = in_array($sobe, NOME_SIGLAS, true) && ($tudo_maiusc || !in_array($sobe, NOME_SIGLAS_SO_MAIUSC, true));
        if ($sigla && !in_array($sobe, ['LTDA', 'EIRELI'], true)) { $partes[$i] = $sobe; $primeira = false; continue; }
        if (preg_match('/\d/u', $w)) { $primeira = false; continue; }   // 4x4, 2B...: fica como veio
        // ligação (de, da, e...) só quando está entre espaços: em "E.M.E.B." o E é letra de sigla
        $ligacao = preg_match('/^\s+$/u', $partes[$i - 1] ?? ' ') && preg_match('/^\s+$/u', $partes[$i + 1] ?? ' ');
        $letra_sigla = mb_strlen($w) === 1 && !$ligacao;
        $ultima = !preg_match('/\p{L}/u', implode('', array_slice($partes, $i + 1)));
        if ($sobe === 'ME' && $ultima && $tudo_maiusc || $sobe === 'ME' && $ultima && preg_match('/(-|LTDA\s*)\s*$/iu', implode('', array_slice($partes, 0, $i)))) {
            $partes[$i] = 'ME'; $primeira = false; continue;   // "... Ltda - ME" (microempresa)
        }
        // nome TODO EM MAIÚSCULAS: palavra de 2 ou 3 letras que não é comum é sigla (AEF Tech, DAF Peças, RC Motos)
        // (3 letras que se leem como palavra, consoante-vogal-consoante ou vogal-consoante-vogal, não são sigla: CAR, DOM, ECO)
        $comum = in_array(mb_strtolower($w), NOME_CURTAS_COMUNS, true);
        $se_le = mb_strlen($w) === 3 && preg_match('/^([^AEIOUÁÉÍÓÚÂÊÔÃÕ][AEIOUÁÉÍÓÚÂÊÔÃÕ][^AEIOUÁÉÍÓÚÂÊÔÃÕ]|[AEIOUÁÉÍÓÚÂÊÔÃÕ][^AEIOUÁÉÍÓÚÂÊÔÃÕ][AEIOUÁÉÍÓÚÂÊÔÃÕ])$/u', $sobe);
        if ($tudo_maiusc && mb_strlen($w) <= 3 && mb_strlen($w) >= 2 && !$ligacao_ok($i) && !isset($voc[mb_strtolower($w)])
            && !$comum && !$se_le) { $primeira = false; continue; }
        if ($tudo_maiusc || $tudo_minusc) {
            $sem_vogal = !preg_match('/[aeiouyáéíóúâêôãõà]/iu', $w) && mb_strlen($w) >= 2;
            $partes[$i] = ($sem_vogal && !$comum || $letra_sigla) && $tudo_maiusc ? $w : nome_palavra($w, $primeira, $ligacao);
        } elseif ($w === $sobe && mb_strlen($w) >= 2 && isset($voc[mb_strtolower($w)])) {
            $partes[$i] = nome_palavra($w, $primeira, $ligacao);   // palavra comum em MAIÚSCULAS no meio de nome normal
        }
        $primeira = false;
    }
    return implode('', $partes);
}
