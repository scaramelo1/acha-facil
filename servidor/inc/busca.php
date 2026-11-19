<?php
// Busca do site e do app (19/11, passo 106, pedido do usuário, avaliação itens 1, 2 e 6).
// Antes a busca procurava a frase inteira ("lava jato", "conserto celular" e "pizaria" davam 0; "mecanico" achava 3).
// Agora:
//   - cada palavra é procurada separada (todas precisam aparecer, em qualquer campo);
//   - cada palavra vira a raiz ("mecanico" -> "mecanic", acha mecânica/mecânico; "celulares" -> "celular");
//   - sinônimos (BUSCA_SINONIMOS por palavra, BUSCA_FRASES por expressão: "lava jato" = lava-rápido);
//   - palavra curta (bar, gás, pet) só vale como palavra inteira (antes "bar" achava toda barbearia); as outras, como
//     começo de palavra ("unha" não acha "Cunha");
//   - nada encontrado: corrige erro de digitação pelos tipos cadastrados ("pizaria" -> "pizzaria");
//   - ainda nada e mais de uma palavra: mostra quem tem parte das palavras (parcial).
// O banco usa utf8mb4_unicode_ci: LIKE já ignora acento, ç e maiúsculas.
// Cada busca é guardada na tabela buscas (mudança 033) para o /admin/buscas.php.
declare(strict_types=1);

/** Palavras que não ajudam a achar nada. */
const BUSCA_PARADAS = ['de', 'da', 'do', 'das', 'dos', 'e', 'em', 'na', 'no', 'nas', 'nos', 'para', 'pra', 'pro', 'a', 'o', 'as', 'os',
    'com', 'um', 'uma', 'perto', 'mim', 'aqui', 'onde', 'tem', 'que', 'melhor', 'melhores', 'barato', 'barata', 'bom', 'boa', 'proximo',
    'proxima', 'delivery', 'entrega', 'plantao', '24h', 'aberto', 'aberta', 'agora', 'hoje', 'cidade', 'centro'];

/** Expressões inteiras (sem acento, minúsculas) -> o que procurar no lugar. */
const BUSCA_FRASES = [
    'lava jato' => ['lava-rápido', 'lava rápido', 'lava jato', 'lavagem', 'estética automotiva'],
    'lava rapido' => ['lava-rápido', 'lava rápido', 'lava jato', 'lavagem', 'estética automotiva'],
    'lava car' => ['lava-rápido', 'lava rápido', 'lava jato', 'lavagem'],
    'auto eletrica' => ['autoelétric', 'auto elétric'],
    'auto peca' => ['autopeça', 'auto peça'],
    'auto pecas' => ['autopeça', 'auto peça'],
    'pet shop' => ['pet shop', 'petshop', 'banho e tosa', 'ração'],
    'self service' => ['self-service', 'self service', 'restaurante'],
    'comida japonesa' => ['japon', 'sushi', 'temaki'],
    'comida caseira' => ['comida caseira', 'marmit', 'restaurante'],
    'material de construcao' => ['materiais de construção', 'material de construção', 'depósito de material'],
    'disk gas' => ['gás'],
    'disk agua' => ['água mineral', 'distribuidora de água'],
    'banho e tosa' => ['banho e tosa', 'pet shop'],
    'salao de beleza' => ['salão de beleza', 'cabelei', 'centro de saúde e beleza'],
    'casa de racao' => ['ração', 'agropecuária', 'pet shop'],
    'loja de roupa' => ['roupa', 'moda', 'boutique', 'vestuário'],
    'auto escola' => ['autoescola', 'auto escola', 'centro de formação de condutores'],
    'moto taxi' => ['mototáxi', 'moto táxi'],
];

/** Palavra (sem acento, minúsculas, como a pessoa escreve) -> outros jeitos que estão no banco. */
const BUSCA_SINONIMOS = [
    'mecanico' => ['mecânic', 'auto center'], 'mecanica' => ['mecânic', 'auto center'],
    'remedio' => ['farmácia', 'drogaria'], 'remedios' => ['farmácia', 'drogaria'],
    'farmacia' => ['farmácia', 'drogaria'], 'drogaria' => ['farmácia', 'drogaria'],
    'dentista' => ['dentista', 'odonto'], 'odontologia' => ['dentista', 'odonto'],
    'medico' => ['médic', 'clínica'], 'medica' => ['médic', 'clínica'],
    'cabelo' => ['cabelei', 'cabelo', 'salão'], 'cabeleireiro' => ['cabelei', 'salão'], 'cabeleireira' => ['cabelei', 'salão'],
    'salao' => ['salão', 'cabelei'],
    'unha' => ['manicure', 'unha', 'esmalteria', 'nail'], 'unhas' => ['manicure', 'unha', 'esmalteria', 'nail'],
    'manicure' => ['manicure', 'unha', 'esmalteria'],
    'barbeiro' => ['barbe'], 'barba' => ['barbe'],
    'mercado' => ['mercado', 'mercearia', 'atacad'], 'supermercado' => ['mercado', 'atacad'],
    'pao' => ['padaria', 'panificadora'], 'padaria' => ['padaria', 'panificadora'],
    'lanche' => ['lanch', 'hamburg', 'pastel'], 'lanches' => ['lanch', 'hamburg', 'pastel'], 'lanchonete' => ['lanch'],
    'hamburguer' => ['hamburg', 'burger', 'lanch'], 'burger' => ['hamburg', 'burger'], 'hamburgueria' => ['hamburg', 'burger'],
    'sorvete' => ['sorvet', 'gelato'], 'acai' => ['açaí'],
    'bar' => ['bar', 'boteco', 'choperia', 'pub'], 'boteco' => ['bar', 'boteco'],
    'cafe' => ['café', 'cafeteria'],
    'comida' => ['restaurante', 'comida', 'marmit', 'lanch'], 'almoco' => ['restaurante', 'marmit', 'self-service'],
    'restaurante' => ['restaurante', 'self-service'], 'marmita' => ['marmit', 'comida caseira'], 'marmitex' => ['marmit', 'comida caseira'],
    'carne' => ['açougue', 'carne'], 'acougue' => ['açougue', 'casa de carnes'],
    'churrasco' => ['churrasc'], 'sushi' => ['sushi', 'japon', 'temaki'], 'japones' => ['japon', 'sushi'],
    'roupa' => ['roupa', 'moda', 'boutique', 'vestuário'], 'roupas' => ['roupa', 'moda', 'boutique', 'vestuário'],
    'sapato' => ['calçad', 'sapat'], 'calcado' => ['calçad', 'sapat'], 'tenis' => ['tênis', 'calçad'],
    'oculos' => ['ótica', 'óculos'], 'otica' => ['ótica', 'óculos'],
    'pneu' => ['pneu', 'borrachar'], 'borracharia' => ['borrachar', 'pneu'], 'borracheiro' => ['borrachar', 'pneu'],
    'gasolina' => ['posto', 'combustível'], 'posto' => ['posto', 'combustível'], 'combustivel' => ['posto', 'combustível'],
    'guincho' => ['guincho', 'reboque'], 'reboque' => ['guincho', 'reboque'],
    'conserto' => ['conserto', 'reparo', 'assistência', 'manutenção'], 'concerto' => ['conserto', 'reparo', 'assistência', 'manutenção'],
    'arrumar' => ['conserto', 'reparo', 'assistência', 'manutenção'], 'reparo' => ['conserto', 'reparo', 'assistência', 'manutenção'],
    'assistencia' => ['assistência', 'conserto', 'reparo'],
    'computador' => ['informática', 'computador', 'notebook'], 'notebook' => ['informática', 'computador', 'notebook'],
    'informatica' => ['informática', 'computador'], 'pc' => ['informática', 'computador'],
    'eletricista' => ['eletricista', 'elétric'], 'encanador' => ['encanador', 'hidráulic'],
    'pedreiro' => ['pedreiro', 'construção', 'reforma'], 'pintor' => ['pintor', 'pintura'],
    'advogado' => ['advoga', 'jurídic'], 'advogada' => ['advoga', 'jurídic'],
    'contador' => ['contab', 'contador'], 'contabilidade' => ['contab', 'contador'],
    'academia' => ['academia', 'fitness', 'musculação', 'crossfit'], 'musculacao' => ['academia', 'musculação'],
    'pet' => ['pet', 'veterinár', 'ração', 'banho e tosa'], 'cachorro' => ['pet', 'veterinár', 'banho e tosa'],
    'gato' => ['pet', 'veterinár'], 'racao' => ['ração', 'pet', 'agropecuária'],
    'veterinario' => ['veterinár', 'pet'], 'veterinaria' => ['veterinár', 'pet'],
    'hotel' => ['hotel', 'pousada', 'hospedagem'], 'pousada' => ['pousada', 'hotel', 'hospedagem', 'chalé'],
    'hospedagem' => ['hospedagem', 'hotel', 'pousada'], 'dormir' => ['hotel', 'pousada', 'hospedagem'],
    'gas' => ['gás'], 'agua' => ['água'],
    'chaveiro' => ['chaveiro'], 'chave' => ['chaveiro'],
    'psicologo' => ['psicólog', 'terapeuta'], 'psicologa' => ['psicólog', 'terapeuta'],
    'fisioterapia' => ['fisioterap', 'pilates'], 'fisioterapeuta' => ['fisioterap'],
    'escola' => ['escola', 'colégio', 'curso'], 'curso' => ['curso', 'escola'], 'ingles' => ['inglês', 'idiomas'],
    'igreja' => ['igreja', 'paróquia', 'santuário', 'templo'],
    'banco' => ['banco', 'cooperativa de crédito'], 'loterica' => ['lotérica'],
    'carro' => ['carro', 'veícul', 'automó'], 'carros' => ['carro', 'veícul', 'automó'],
    'festa' => ['festa', 'buffet', 'eventos'], 'aniversario' => ['festa', 'buffet'], 'buffet' => ['buffet', 'festa'],
    'bolo' => ['bolo', 'confeit', 'doce'], 'doce' => ['doce', 'confeit'], 'doces' => ['doce', 'confeit'],
    'flor' => ['flor', 'floricultura'], 'flores' => ['flor', 'floricultura'],
    'construcao' => ['construção'], 'vidro' => ['vidr'], 'moveis' => ['móve', 'moveleir', 'marcen'], 'movel' => ['móve', 'moveleir', 'marcen'],
    'tatuagem' => ['tatua', 'tattoo'], 'tattoo' => ['tatua', 'tattoo'],
    'impressao' => ['gráfica', 'impressão', 'copiadora'], 'xerox' => ['gráfica', 'copiadora', 'papelaria'],
    'foto' => ['fotógraf', 'foto'], 'fotografo' => ['fotógraf'],
    'taxi' => ['táxi', 'mototáxi', 'transporte'], 'uber' => ['táxi', 'transporte'],
    'costura' => ['costur', 'ajuste'], 'costureira' => ['costur', 'ajuste'],
    'dedetizacao' => ['dedetiz', 'controle de pragas'],
    'aluguel' => ['imobiliár', 'imóve', 'corretor'], 'imovel' => ['imobiliár', 'imóve', 'corretor'], 'imobiliaria' => ['imobiliár', 'imóve'],
    'cerveja' => ['bebida', 'adega', 'distribuidora', 'cerveja'], 'bebida' => ['bebida', 'adega', 'distribuidora'],
    'bebidas' => ['bebida', 'adega', 'distribuidora'], 'adega' => ['adega', 'bebida'],
    'celular' => ['celular'], 'celulares' => ['celular'],
    'material' => ['materia'], 'materiais' => ['materia'],
];

const BUSCA_CAMPOS = ['p.nome', 'p.tipo', 'p.servicos', 'p.descricao', 'p.bairro', 'c.nome'];
const BUSCA_CAMPOS_FORTES = ['p.nome', 'p.tipo', 'c.nome'];   // achou aqui = vem antes na lista
const BUSCA_LIMITE_PALAVRAS = 5;

/** Minúsculas, sem acento, só letras/números e espaço. */
function busca_normalizar(string $s): string {
    $s = mb_strtolower($s);
    $s = strtr($s, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n']);
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}

/** Raiz da palavra: tira plural e a vogal do fim ("mecanicos" -> "mecanic", "celulares" -> "celular", "materiais" -> "materi"). */
function busca_raiz(string $p): string {
    if (strlen($p) < 5) return $p;
    $r = preg_replace('/(oes|aes|ais|eis)$/', '', $p, 1, $n);
    if (!$n) $r = preg_replace('/res$/', 'r', $p, 1, $n);
    if (!$n) $r = preg_replace('/s$/', '', $p);
    $r2 = preg_replace('/[aoe]$/', '', $r);
    if (strlen($r2) >= 5) return $r2;
    return strlen($r) >= 4 ? $r : $p;
}

/**
 * Termo -> lista de "grupos": cada grupo é uma palavra (ou expressão) com os jeitos de achar.
 * Todos os grupos precisam aparecer (modo normal). Retorna [grupos, rotulos].
 */
function busca_grupos(string $termo): array {
    $n = ' ' . busca_normalizar($termo) . ' ';
    $grupos = [];
    $rotulos = [];
    foreach (BUSCA_FRASES as $frase => $alts) {
        if (str_contains($n, " $frase ")) {
            $grupos[] = $alts;
            $rotulos[] = $frase;
            $n = str_replace(" $frase ", ' ', $n);
        }
    }
    foreach (explode(' ', trim($n)) as $p) {
        if ($p === '' || in_array($p, BUSCA_PARADAS, true) || (strlen($p) < 2)) continue;
        $alts = BUSCA_SINONIMOS[$p] ?? [];
        $alts[] = busca_raiz($p);
        $grupos[] = array_values(array_unique($alts));
        $rotulos[] = $p;
        if (count($grupos) >= BUSCA_LIMITE_PALAVRAS) break;
    }
    return [$grupos, $rotulos];
}

/** Condição SQL de um jeito de achar num campo. Palavra curta (até 3 letras) só como palavra inteira. */
function busca_cond_campo(string $campo, string $alt): string {
    $alt = trim($alt);
    $esc = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $alt);
    $pdo = db();
    if (mb_strlen($alt) <= 3) {
        return "($campo = " . $pdo->quote($alt) . " OR $campo LIKE " . $pdo->quote("$esc %") . " OR $campo LIKE " . $pdo->quote("% $esc")
            . " OR $campo LIKE " . $pdo->quote("% $esc %") . " OR $campo LIKE " . $pdo->quote("%-$esc %") . ')';
    }
    // começo de palavra ("unha" não acha "Cunha", "ração" não acha "decoração"); raiz e sinônimos já são começos
    $partes = [];
    foreach (['', '% ', '%-', '%/', '%('] as $antes) $partes[] = "$campo LIKE " . $pdo->quote("$antes$esc%");
    return '(' . implode(' OR ', $partes) . ')';
}
function busca_cond_grupo(array $alts, array $campos = BUSCA_CAMPOS): string {
    $partes = [];
    foreach ($alts as $a) foreach ($campos as $c) $partes[] = busca_cond_campo($c, $a);
    return '(' . implode(' OR ', $partes) . ')';
}

/** Roda a busca com os grupos. $todos = todos os grupos precisam aparecer; senão basta um (ordena por quantos achou). */
function busca_sql(array $grupos, ?string $cidade, int $limite, bool $so_com_horario, bool $todos): array {
    if (!$grupos) return [];
    $conds = array_map('busca_cond_grupo', $grupos);
    $fortes = array_map(fn($g) => busca_cond_grupo($g, BUSCA_CAMPOS_FORTES), $grupos);
    $where = visivel($cidade) . ' AND (' . implode($todos ? ' AND ' : ' OR ', $conds) . ')'
        . ($so_com_horario ? " AND p.horarios IS NOT NULL AND p.horarios NOT IN ('', '[]', '{}')" : '');
    $ordem = ($todos ? '' : '(' . implode(' + ', $conds) . ') DESC, ')
        . '(' . implode(' + ', $fortes) . ') DESC, ' . SQL_DESTAQUE . ' DESC, ' . SQL_FICHA_PAGA . ' DESC, ' . SQL_COM_HORARIO . ' DESC, RAND(' . semente_dia() . ')';
    return linhas('SELECT ' . CAMPOS_RESUMO . ' FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE ' . $where . ' ORDER BY ' . $ordem . ' LIMIT ' . (int)$limite);
}

/** Palavras dos tipos e áreas cadastrados (para corrigir erro de digitação), guardadas por 1 dia. */
function busca_vocabulario(): array {
    static $v = null;
    if ($v !== null) return $v;
    $arq = config()['dados_dir'] . '/cache/busca-vocabulario.json';
    if (is_file($arq) && filemtime($arq) > time() - 86400) {
        $v = json_decode((string)file_get_contents($arq), true) ?: [];
        if ($v) return $v;
    }
    $v = [];
    $textos = linhas("SELECT tipo AS t, COUNT(*) AS n FROM profissionais WHERE status = 'ativo' AND tipo IS NOT NULL GROUP BY tipo");
    foreach (linhas('SELECT nome AS t, 50 AS n FROM categorias WHERE ativa = 1') as $l) $textos[] = $l;
    foreach ($textos as $l) foreach (explode(' ', busca_normalizar((string)$l['t'])) as $p)
        if (strlen($p) >= 4 && !in_array($p, BUSCA_PARADAS, true)) $v[$p] = ($v[$p] ?? 0) + (int)$l['n'];
    foreach (array_keys(BUSCA_SINONIMOS) as $p) if (strlen($p) >= 4) $v[$p] = ($v[$p] ?? 0) + 100;
    foreach (array_keys(BUSCA_FRASES) as $f) foreach (explode(' ', $f) as $p) if (strlen($p) >= 4) $v[$p] = ($v[$p] ?? 0) + 100;
    @mkdir(dirname($arq), 0775, true);
    @file_put_contents($arq, json_encode($v));
    return $v;
}

/** Palavra digitada errado -> a mais parecida do vocabulário (ou null se já existe / nada perto). */
function busca_corrigir_palavra(string $p): ?string {
    $voc = busca_vocabulario();
    if (strlen($p) < 4 || isset($voc[$p])) return null;
    $raiz = busca_raiz($p);
    foreach ($voc as $w => $_) if (str_starts_with((string)$w, $raiz)) return null;   // é começo de palavra que existe
    $max = strlen($p) <= 5 ? 1 : 2;
    $melhor = null;
    $melhor_d = $max + 1;
    $melhor_n = 0;
    foreach ($voc as $w => $n) {
        $w = (string)$w;
        if (abs(strlen($w) - strlen($p)) > $max) continue;
        $d = levenshtein($p, $w);
        if ($d < $melhor_d || ($d === $melhor_d && $n > $melhor_n)) { $melhor = $w; $melhor_d = $d; $melhor_n = $n; }
    }
    return $melhor_d <= $max ? $melhor : null;
}

/**
 * A busca completa. Retorna:
 *   lista      linhas do banco (CAMPOS_RESUMO)
 *   corrigido  termo usado no lugar do digitado ("pizzaria"), ou null
 *   parcial    true = ninguém tinha todas as palavras; a lista tem quem tem parte delas
 */
function busca_executar(string $termo, ?string $cidade, int $limite = 60, bool $so_com_horario = false): array {
    $vazio = ['lista' => [], 'corrigido' => null, 'parcial' => false];
    $termo = trim(mb_substr($termo, 0, 60));
    if (mb_strlen($termo) < 2) return $vazio;
    [$grupos, $rotulos] = busca_grupos($termo);
    if (!$grupos) {   // só palavras como "de", "perto": procura o que foi escrito mesmo
        $grupos = [[$termo]];
        $rotulos = [busca_normalizar($termo)];
    }
    $lista = busca_sql($grupos, $cidade, $limite, $so_com_horario, true);
    if ($lista) return ['lista' => $lista] + $vazio;

    // erro de digitação: troca as palavras que não existem pela mais parecida
    $trocou = false;
    $novos = $rotulos;
    foreach ($rotulos as $i => $r) {
        if (str_contains($r, ' ')) continue;   // expressão de BUSCA_FRASES
        $c = busca_corrigir_palavra($r);
        if ($c !== null) { $novos[$i] = $c; $trocou = true; }
    }
    $corrigido = null;
    if ($trocou) {
        $corrigido = implode(' ', $novos);
        [$grupos] = busca_grupos($corrigido);
        $lista = busca_sql($grupos, $cidade, $limite, $so_com_horario, true);
        if ($lista) return ['lista' => $lista, 'corrigido' => $corrigido, 'parcial' => false];
    }
    // mais de uma palavra: quem tem parte delas
    if (count($grupos) > 1) {
        $lista = busca_sql($grupos, $cidade, $limite, $so_com_horario, false);
        if ($lista) return ['lista' => $lista, 'corrigido' => $corrigido, 'parcial' => true];
    }
    return $vazio;
}

/** Quantos resultados o termo tem em cada outra cidade (página sem resultado): [slug => total], maiores primeiro. */
function busca_outras_cidades(string $termo, string $menos, int $max = 5): array {
    [$grupos] = busca_grupos($termo);
    if (!$grupos) return [];
    $r = [];
    foreach (linhas('SELECT p.cidade, COUNT(*) AS n FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE ' . visivel() . ' AND p.cidade <> ? AND ' . implode(' AND ', array_map('busca_cond_grupo', $grupos)) . '
        GROUP BY p.cidade ORDER BY n DESC', [$menos]) as $l)
        if (isset(CIDADES[$l['cidade']]) && count($r) < $max) $r[$l['cidade']] = (int)$l['n'];
    return $r;
}

// ---------- registro das buscas (mudança 033) ----------

function buscas_prontas(): bool {
    static $ok = null;
    if ($ok === null) {
        try { $ok = (bool)valor("SELECT 1 FROM migracoes WHERE nome = '033-buscas.sql'"); }
        catch (PDOException $e) { $ok = false; }
    }
    return $ok;
}

/**
 * Guarda a busca. O app busca enquanto a pessoa digita: se a mesma pessoa buscou há menos de 90 s algo que é começo
 * (ou continuação) do termo novo ("piz" -> "pizza"), a linha anterior é trocada em vez de criar outra.
 * A mesma busca repetida pela mesma pessoa em 30 min não conta de novo. Robôs não contam.
 */
function registrar_busca(string $termo, ?string $cidade, int $resultados, ?string $corrigido, string $origem): void {
    $termo = trim(mb_substr($termo, 0, 60));
    if (mb_strlen($termo) < 2 || e_robo() || !buscas_prontas()) return;
    try {
        $quem = visitante();
        $cid = $cidade !== null && isset(CIDADES[$cidade]) ? $cidade : '';
        $ultima = linha('SELECT id, termo, criado_em > NOW() - INTERVAL 90 SECOND AS recente FROM buscas
            WHERE quem = ? AND cidade = ? AND criado_em > NOW() - INTERVAL 30 MINUTE ORDER BY id DESC LIMIT 1', [$quem, $cid]);
        $n_novo = busca_normalizar($termo);
        if ($ultima) {
            $n_antes = busca_normalizar((string)$ultima['termo']);
            if ($n_antes === $n_novo) return;
            if ($ultima['recente'] && $n_antes !== '' && (str_starts_with($n_novo, $n_antes) || str_starts_with($n_antes, $n_novo))) {
                q('UPDATE buscas SET termo = ?, resultados = ?, corrigido = ?, criado_em = NOW() WHERE id = ?',
                    [$termo, min($resultados, 65535), $corrigido, $ultima['id']]);
                return;
            }
        }
        inserir('buscas', ['cidade' => $cid, 'termo' => $termo, 'resultados' => min($resultados, 65535), 'corrigido' => $corrigido,
            'origem' => $origem === 'site' ? 'site' : 'app', 'quem' => $quem]);
    } catch (PDOException $e) {
        error_log('acha-facil registrar_busca: ' . $e->getMessage());   // guardar nunca derruba a busca
    }
}

/** Buscas mais feitas na cidade que acharam algo (sugestões da página sem resultado). */
function buscas_populares(string $cidade, int $max = 8): array {
    if (!buscas_prontas()) return [];
    try {
        return array_column(linhas("SELECT MIN(termo) AS termo, COUNT(DISTINCT quem) AS pessoas FROM buscas
            WHERE cidade = ? AND resultados > 0 AND corrigido IS NULL AND criado_em > NOW() - INTERVAL 30 DAY
            GROUP BY LOWER(termo) HAVING pessoas >= 2 ORDER BY pessoas DESC LIMIT " . (int)$max, [$cidade]), 'termo');
    } catch (PDOException $e) { return []; }
}
