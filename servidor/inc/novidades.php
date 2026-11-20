<?php
// Novidades do robô (passo 56): a lista do robô chega (pelo PC sozinho, em /admin/robo-enviar.php, ou à mão em
// /admin/importar.php), o importador confere em modo TESTE e guarda o que mudou: comércios novos, os que mudaram
// telefone/endereço/nome e os que fecharam. O admin vê em /admin/novidades.php e importa (ou entra sozinho, se o
// "modo automático" estiver ligado). Tudo em arquivos ($DADOS/novidades), sem mudança no banco.
declare(strict_types=1);
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/guia.php';
require_once __DIR__ . '/moderacao.php';

function nov_dir(): string {
    $d = config()['dados_dir'] . '/novidades';
    if (!is_dir($d)) mkdir($d, 0750, true);
    return $d;
}
function nov_raiz(): string { return dirname(__DIR__); }

/** roda um script do servidor/ com o php de linha de comando e devolve [código, saída] */
function nov_rodar(array $args): array {
    $cmd = 'php ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';
    exec($cmd, $out, $rc);
    return [$rc, trim(implode("\n", $out))];
}
function geo_rodando(): bool {
    exec('ps -C php -o args=', $out);
    foreach ($out as $l) if (str_contains($l, 'geocodificar.php')) return true;
    return false;
}

/** "São João da Boa Vista - SP · atualizado em ..." no cabeçalho → slug da cidade cadastrada */
function cidade_do_arquivo(string $html): ?string {
    if (!preg_match('/<div class="muted">\s*([^<·]+?) - ([A-Z]{2})\s*·/u', $html, $m)) return null;
    $norm = fn($s) => strtr(mb_strtolower(trim(html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
        ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ü'=>'u','ç'=>'c']);
    foreach (TODAS_CIDADES as $slug => $c) if ($norm($c[0]) === $norm($m[1]) && $c[2] === $m[2]) return $slug;
    return null;
}

/** 20/11: pelo nome do arquivo ("leads-divinolandia-sp (1).html", "leads_monte_sião_novos.html") quando o cabeçalho não diz */
function cidade_do_nome_arquivo(array $nomes): ?string {
    $norm = fn($s) => preg_replace('/[^a-z0-9]/', '', strtr(mb_strtolower($s),
        ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ü'=>'u','ç'=>'c']));
    foreach ($nomes as $n) {
        $b = mb_strtolower(preg_replace('/\.html?$/i', '', basename((string)$n)));
        $b = preg_replace(['/^leads[-_ ]*/u', '/\s*\(\d+\)$/u', '/[-_ ](novos|completo)$/u', '/[-_ ](sp|mg)$/u', '/[-_ ](novos|completo)$/u'], '', $b);
        $b = $norm($b);
        if ($b === '') continue;
        foreach (TODAS_CIDADES as $slug => $c) if ($b === $norm($slug) || $b === $norm($c[0])) return $slug;
    }
    return null;
}

// ---------- configuração: chave do robô e modo automático ----------
function nov_config(): array {
    $f = nov_dir() . '/config.json';
    $c = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    return $c + ['automatico' => false, 'chave_hash' => '', 'chave_criada' => null];
}
function nov_config_salvar(array $c): void {
    file_put_contents(nov_dir() . '/config.json', json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
/** Gera uma chave nova (a antiga para de valer). Devolve a chave, que só é mostrada uma vez. */
function robo_chave_nova(): string {
    $k = 'af_' . bin2hex(random_bytes(24));
    $c = nov_config();
    $c['chave_hash'] = hash('sha256', $k);
    $c['chave_criada'] = date('c');
    nov_config_salvar($c);
    return $k;
}
function robo_chave_confere(string $k): bool {
    $h = nov_config()['chave_hash'];
    return $h !== '' && $k !== '' && hash_equals($h, hash('sha256', $k));
}

// ---------- envios ----------
/** id = data + cidade + sorteio (só letras, números e hífen) */
function nov_id_valido(string $id): bool { return (bool)preg_match('/^\d{8}-\d{6}-[a-z0-9-]+-[a-f0-9]{6}$/', $id); }
function nov_ler(string $id): ?array {
    if (!nov_id_valido($id)) return null;
    $f = nov_dir() . "/$id.meta.json";
    return is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: null) : null;
}
function nov_salvar(array $m): void {
    file_put_contents(nov_dir() . "/{$m['id']}.meta.json", json_encode($m, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
function nov_detalhes(string $id): array {
    $f = nov_dir() . "/$id.detalhes.json";
    $d = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    return $d + ['novos' => [], 'mudaram' => [], 'fecharam' => []];
}
/** os envios, do mais novo para o mais velho */
function nov_lista(int $limite = 60): array {
    $r = [];
    $arqs = glob(nov_dir() . '/*.meta.json') ?: [];
    rsort($arqs);
    foreach (array_slice($arqs, 0, $limite) as $f) if ($m = json_decode((string)file_get_contents($f), true)) $r[] = $m;
    return $r;
}
function nov_aguardando_contar(): int {
    $n = 0;
    foreach (nov_lista(200) as $m) if ($m['status'] === 'aguardando') $n++;
    return $n;
}
/** "14 novos, 3 mudaram, 2 fecharam" */
function nov_resumo(array $m): string {
    $p = [];
    $p[] = $m['novos'] . ($m['novos'] === 1 ? ' novo' : ' novos');
    if ($m['mudaram']) $p[] = $m['mudaram'] . ' mudaram';
    if ($m['fecharam']) $p[] = $m['fecharam'] . ($m['fecharam'] === 1 ? ' fechou' : ' fecharam');
    return implode(', ', $p);
}

/**
 * Guarda a lista recebida e confere com o importador em modo TESTE (nada muda no banco).
 * Devolve o envio (meta) ou lança RuntimeException com o motivo.
 */
function nov_criar(string $html, string $slug, string $origem, array $nomes): array {
    if (!str_contains($html, '<tr data-status=')) throw new RuntimeException('Não parece a lista do robô (não achei a tabela de comércios).');
    if ($slug === '') $slug = (string)(cidade_do_arquivo($html) ?? cidade_do_nome_arquivo($nomes));
    if (!isset(TODAS_CIDADES[$slug])) throw new RuntimeException('Não sei de qual cidade é a lista. Cadastre a cidade em /admin/cidades.php (ou escolha a cidade no envio).');
    $id = date('Ymd-His') . '-' . $slug . '-' . bin2hex(random_bytes(3));
    $dir = nov_dir();
    file_put_contents("$dir/$id.html", $html);
    [$rc, $saida] = nov_rodar([nov_raiz() . '/importar-leads.php', "$dir/$id.html", $slug, '--teste', "--detalhes=$dir/$id.detalhes.json"]);
    if ($rc !== 0 || !str_contains($saida, '✔')) { @unlink("$dir/$id.html"); @unlink("$dir/$id.detalhes.json"); throw new RuntimeException('O importador recusou a lista: ' . $saida); }
    $d = nov_detalhes($id);
    $m = ['id' => $id, 'cidade' => $slug, 'recebido' => date('c'), 'origem' => $origem, 'arquivos' => $nomes,
        'linhas' => substr_count($html, '<tr data-status='), 'status' => 'aguardando', 'saida' => $saida,
        'novos' => count($d['novos']), 'mudaram' => count($d['mudaram']), 'fecharam' => count($d['fecharam'])];
    nov_salvar($m);
    return $m;
}

/**
 * Importa de verdade: backup dos comércios da cidade, a lista vira leads/<slug>.html (a anterior vira .antes-<data>),
 * importa, limpa o cache do site e procura a localização dos novos em segundo plano. Lança RuntimeException se falhar.
 */
function nov_importar(array $m, ?int $admin_id): array {
    if ($m['status'] !== 'aguardando') throw new RuntimeException('Esta lista já foi ' . ($m['status'] === 'importada' ? 'importada' : 'descartada') . '.');
    $dados = config()['dados_dir'];
    $slug = $m['cidade']; $quando = date('Ymd-His');
    $origem = nov_dir() . "/{$m['id']}.html";
    if (!is_file($origem)) throw new RuntimeException('O arquivo desta lista não existe mais.');
    // backup dos comércios desta cidade (para voltar atrás se precisar)
    $bks = "$dados/backups";
    if (!is_dir($bks)) mkdir($bks, 0750, true);
    $c = config(); $bk = "$bks/profissionais-$slug-$quando.sql";
    $cmd = 'mysqldump --single-transaction --skip-lock-tables --no-create-info -h ' . escapeshellarg($c['db_host']) . ' -u ' . escapeshellarg($c['db_usuario'])
         . ' ' . escapeshellarg($c['db_nome']) . ' profissionais --where=' . escapeshellarg("cidade = '$slug'") . ' > ' . escapeshellarg($bk) . ' 2>&1';
    $p = proc_open($cmd, [], $pipes, null, ['MYSQL_PWD' => $c['db_senha'], 'PATH' => '/usr/bin:/bin']);
    $rc = is_resource($p) ? proc_close($p) : 1;
    if ($rc !== 0 || !filesize($bk)) { $msg = mb_substr((string)@file_get_contents($bk), 0, 300); @unlink($bk); throw new RuntimeException('Não consegui fazer o backup antes de importar; nada foi mudado. ' . $msg); }
    // a lista anterior desta cidade vira <slug>.antes-<data>.html
    $pasta = "$dados/leads";
    if (!is_dir($pasta)) mkdir($pasta, 0755, true);
    $final = "$pasta/$slug.html";
    if (is_file($final)) rename($final, "$pasta/$slug.antes-$quando.html");
    copy($origem, $final);
    [$rc, $saida] = nov_rodar([nov_raiz() . '/importar-leads.php', $final, $slug]);
    if ($rc !== 0 || !str_contains($saida, '✔')) throw new RuntimeException('Erro ao importar (o banco não mudou): ' . $saida);
    // 16/11: limpeza que depende da cidade inteira (bairros raros, fichas repetidas); guarda arquivo para desfazer
    nov_rodar([nov_raiz() . '/limpar-dados.php', '--aplicar']);
    web_cache_limpar();
    // passo 65: lista nova pode ter trazido o WhatsApp que alguém pediu
    try { require_once __DIR__ . '/avisos-info.php'; avisar_infos_completas(); } catch (Throwable $e) { error_log('acha-facil avisos info: ' . $e->getMessage()); }
    registrar($admin_id, 'importou_lista', null, nome_cidade($slug) . ': ' . strtok($saida, "\n"));
    // localização dos novos, em segundo plano (OpenStreetMap: ~1,3 s por endereço sem coordenadas no link)
    if (!geo_rodando()) exec('nohup php ' . escapeshellarg(nov_raiz() . '/geocodificar.php') . ' > ' . escapeshellarg("$dados/geocodificar.log") . ' 2>&1 &');
    @unlink($origem);   // a lista fica guardada em leads/<slug>.html
    $m['status'] = 'importada';
    $m['importada_em'] = date('c');
    $m['importada_por'] = $admin_id ? 'admin' : 'automático';
    $m['saida_importacao'] = $saida;
    nov_salvar($m);
    return $m;
}

function nov_descartar(array $m, ?int $admin_id): void {
    if ($m['status'] !== 'aguardando') return;
    @unlink(nov_dir() . "/{$m['id']}.html");
    $m['status'] = 'descartada';
    $m['descartada_em'] = date('c');
    nov_salvar($m);
    registrar($admin_id, 'descartou_lista', null, nome_cidade($m['cidade']) . ': ' . nov_resumo($m));
}

/** Uma lista mais velha da mesma cidade, ainda esperando, fica sem sentido quando chega outra: descarta. */
function nov_descartar_antigas(string $slug, string $menos_id): void {
    foreach (nov_lista(200) as $m) if ($m['cidade'] === $slug && $m['id'] !== $menos_id && $m['status'] === 'aguardando') nov_descartar($m, null);
}
