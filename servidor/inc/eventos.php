<?php
// Agenda de eventos por cidade (passo 32, mudança 020). Cadastro em /admin/eventos.php; no site:
//   /<cidade>/eventos/              agenda: próximos eventos em ordem de data, agrupados por mês, com "folhinha" do dia
//   /<cidade>/eventos/<nome>-<id>   página do evento (tudo + link da página oficial + Google Agenda)
// Até a mudança 020 ser aplicada, eventos_prontos() é false e nada disso aparece (o site publica direto da pasta).
declare(strict_types=1);
require_once __DIR__ . '/guia.php';

const MESES = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
const DIAS_SEMANA = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];

function eventos_prontos(): bool {
    static $ok = null;
    if ($ok === null) {
        try { $ok = (bool)valor("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'eventos'"); }
        catch (Throwable) { $ok = false; }
    }
    return $ok;
}

/** Eventos que ainda não terminaram (começam hoje ou depois, ou estão acontecendo), em ordem de data. */
function eventos_proximos(string $cidade, int $limite = 300): array {
    if (!eventos_prontos()) return [];
    return linhas("SELECT * FROM eventos WHERE cidade = ? AND visivel = 1 AND COALESCE(data_fim, data_inicio) >= CURDATE()
        ORDER BY data_inicio, hora_inicio IS NULL, hora_inicio, id LIMIT $limite", [$cidade]);
}
/** Próximos eventos de todas as cidades ligadas (seção da página inicial). */
function eventos_proximos_todas(int $limite = 6): array {
    if (!eventos_prontos() || !CIDADES) return [];
    $marcas = implode(',', array_fill(0, count(CIDADES), '?'));
    return linhas("SELECT * FROM eventos WHERE visivel = 1 AND cidade IN ($marcas) AND COALESCE(data_fim, data_inicio) >= CURDATE()
        ORDER BY data_inicio, hora_inicio IS NULL, hora_inicio, id LIMIT $limite", array_keys(CIDADES));
}
function eventos_contar(string $cidade): int {
    if (!eventos_prontos()) return 0;
    return (int)valor('SELECT COUNT(*) FROM eventos WHERE cidade = ? AND visivel = 1 AND COALESCE(data_fim, data_inicio) >= CURDATE()', [$cidade]);
}
function evento(int $id): ?array {
    if (!eventos_prontos()) return null;
    return linha('SELECT * FROM eventos WHERE id = ? AND visivel = 1', [$id]);
}
function evento_terminou(array $e): bool { return ($e['data_fim'] ?: $e['data_inicio']) < hoje(); }

// ---------- endereços ----------
function url_eventos(string $cidade): string { return url(cidade_web($cidade) . '/eventos/'); }
function url_evento(array $e): string {
    return url(cidade_web($e['cidade']) . '/eventos/' . (rtrim(mb_substr(slug_web($e['titulo']), 0, 70), '-') ?: 'evento') . '-' . (int)$e['id']);
}

// ---------- datas em português ----------
function hora_curta(?string $h): ?string {
    if (!$h) return null;
    [$hh, $mm] = explode(':', $h);
    return (int)$hh . 'h' . ($mm !== '00' ? $mm : '');
}
/** "sábado, 15 de novembro" / "15 a 17 de novembro" / "30 de novembro a 2 de dezembro" */
function evento_quando(array $e, bool $com_semana = true): string {
    $i = strtotime($e['data_inicio']);
    $f = $e['data_fim'] && $e['data_fim'] !== $e['data_inicio'] ? strtotime($e['data_fim']) : null;
    $ano = fn($t) => date('Y', $t) !== date('Y') ? ' de ' . date('Y', $t) : '';
    if (!$f) return ($com_semana ? DIAS_SEMANA[(int)date('w', $i)] . ', ' : '') . date('j', $i) . ' de ' . MESES[(int)date('n', $i)] . $ano($i);
    if (date('n Y', $i) === date('n Y', $f)) return date('j', $i) . ' a ' . date('j', $f) . ' de ' . MESES[(int)date('n', $f)] . $ano($f);
    return date('j', $i) . ' de ' . MESES[(int)date('n', $i)] . (date('Y', $i) !== date('Y', $f) ? $ano($i) : '') . ' a ' . date('j', $f) . ' de ' . MESES[(int)date('n', $f)] . $ano($f);
}
/** "20h" / "20h às 23h30" / null */
function evento_horario(array $e): ?string {
    $i = hora_curta($e['hora_inicio']); $f = hora_curta($e['hora_fim']);
    if (!$i) return null;
    return $f ? "$i às $f" : "A partir das $i";
}

/** Link "adicionar ao Google Agenda". */
function evento_google_agenda(array $e): string {
    $fmt = function (?string $d, ?string $h, bool $fim = false) use ($e) {
        if (!$d) return null;
        if (!$e['hora_inicio']) return date('Ymd', strtotime($d . ($fim ? ' +1 day' : '')));   // dia inteiro: fim é o dia seguinte
        return date('Ymd\THis', strtotime("$d " . ($h ?: $e['hora_inicio'])));
    };
    $ini = $fmt($e['data_inicio'], $e['hora_inicio']);
    $fim = $e['hora_inicio']
        ? ($e['hora_fim'] ? $fmt($e['data_fim'] ?: $e['data_inicio'], $e['hora_fim']) : date('Ymd\THis', strtotime(($e['data_fim'] ?: $e['data_inicio']) . ' ' . $e['hora_inicio'] . ' +2 hours')))
        : $fmt($e['data_fim'] ?: $e['data_inicio'], null, true);
    $onde = implode(', ', array_filter([$e['local_nome'], $e['endereco'], nome_cidade($e['cidade']) . ' - ' . uf_cidade($e['cidade'])]));
    return 'https://calendar.google.com/calendar/render?' . http_build_query(['action' => 'TEMPLATE', 'text' => $e['titulo'],
        'dates' => "$ini/$fim", 'ctz' => 'America/Sao_Paulo', 'location' => $onde,
        'details' => trim(mb_substr((string)$e['descricao'], 0, 600) . ($e['link_oficial'] ? "\n\n" . $e['link_oficial'] : ''))]);
}
function evento_link_oficial(array $e): ?string {
    $l = trim((string)$e['link_oficial']);
    return $l === '' ? null : (preg_match('#^https?://#i', $l) ? $l : 'https://' . $l);
}

// ---------- pedaços de página ----------
/** Abas da cidade: Comércios | Eventos (só aparece com a mudança 020 aplicada). */
function web_abas_cidade(string $cidade, string $ativa): string {
    if (!eventos_prontos()) return '';
    $n = eventos_contar($cidade);
    return '<nav class="abas-cidade" aria-label="Seções da cidade">'
        . '<a href="' . h(url_cidade($cidade)) . '"' . ($ativa === 'comercios' ? ' aria-current="page"' : '') . '>🏪 Comércios</a>'
        . '<a href="' . h(url_eventos($cidade)) . '"' . ($ativa === 'eventos' ? ' aria-current="page"' : '') . '>📅 Eventos' . ($n ? ' <small>' . $n . '</small>' : '') . '</a>'
        . '</nav>';
}
/** Folhinha do calendário (dia da semana, número, mês). */
function evento_folhinha(array $e): string {
    $i = strtotime($e['data_inicio']);
    $f = $e['data_fim'] && $e['data_fim'] !== $e['data_inicio'] ? strtotime($e['data_fim']) : null;
    return '<span class="folhinha" aria-hidden="true"><small>' . h(mb_substr(DIAS_SEMANA[(int)date('w', $i)], 0, 3)) . '</small><b>' . date('j', $i)
        . ($f ? '<i>–' . date('j', $f) . '</i>' : '') . '</b><small>' . h(mb_substr(MESES[(int)date('n', $f ?: $i)], 0, 3)) . '</small></span>';
}

/** Página da agenda da cidade. */
function web_pagina_eventos(string $cidade): void {
    [$nome, , $uf] = CIDADES[$cidade];
    $eventos = eventos_proximos($cidade);
    [$trilha, $ld_trilha] = web_trilha([["$nome - $uf", url_cidade($cidade)], ['Eventos', null]]);
    $ld = ['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => "Eventos em $nome - $uf",
        'itemListElement' => array_map(fn($e, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => web_absoluta(url_evento($e)), 'name' => $e['titulo']],
            $eventos, array_keys($eventos))];
    pagina_inicio("Eventos em $nome - $uf: agenda | Acha Fácil!",
        "Agenda de eventos em $nome ($uf): shows, festas, feiras e programação da cidade, em ordem de data, com local, horário e link oficial.",
        'web', ['canonica' => web_absoluta(url_eventos($cidade)), 'head' => web_json_ld($ld_trilha, $ld)]);
    ?>
<main>
  <section class="web-topo"><div class="conteudo">
    <?= $trilha ?>
    <h1>Eventos em <mark><?= h($nome) ?></mark></h1>
    <p class="lead">A agenda da cidade em ordem de data: toque no evento para ver tudo e o link oficial.</p>
    <?= web_abas_cidade($cidade, 'eventos') ?>
  </div></section>
  <section class="branco"><div class="conteudo">
    <?php if (!$eventos): ?>
      <p class="aviso ok">Nenhum evento marcado em <?= h($nome) ?> por enquanto. Organiza um evento na cidade? Mande para a gente: <?= contatos_html() ?>.</p>
    <?php else: $mes = null; ?>
      <div class="agenda">
      <?php foreach ($eventos as $e):
          $acontecendo = $e['data_inicio'] < hoje();
          $m = $acontecendo ? 'agora' : substr($e['data_inicio'], 0, 7);
          if ($m !== $mes): if ($mes !== null) echo '</ol>'; $mes = $m; ?>
            <h2 class="agenda-mes"><?= $acontecendo ? 'Acontecendo agora' : h(ucfirst(MESES[(int)substr($m, 5, 2)]) . (substr($m, 0, 4) !== date('Y') ? ' de ' . substr($m, 0, 4) : '')) ?></h2>
            <ol class="agenda-lista">
          <?php endif ?>
          <li><a class="evento-cartao" href="<?= h(url_evento($e)) ?>">
            <?= evento_folhinha($e) ?>
            <span class="evento-info">
              <b><?= h($e['titulo']) ?></b>
              <span><?= h(evento_quando($e)) ?><?= ($hr = evento_horario($e)) ? ' · ' . h($hr) : '' ?></span>
              <?php if ($e['local_nome'] || $e['endereco']): ?><span>📍 <?= h($e['local_nome'] ?: $e['endereco']) ?></span><?php endif ?>
              <?php if ($e['preco']): ?><span class="evento-preco"><?= h($e['preco']) ?></span><?php endif ?>
            </span>
            <?php if ($e['imagem']): ?><img src="<?= h(foto_url($e['imagem'])) ?>" alt="" loading="lazy" class="evento-mini"><?php endif ?>
          </a></li>
      <?php endforeach ?>
      </ol></div>
    <?php endif ?>
  </div></section>
  <?= web_baixe_app($cidade) ?>
</main>
<?php
    pagina_fim();
}

/** Página do evento. */
function web_pagina_evento(array $e): void {
    $cidade = $e['cidade'];
    [$nome_cidade, , $uf] = CIDADES[$cidade];
    [$trilha, $ld_trilha] = web_trilha([["$nome_cidade - $uf", url_cidade($cidade)], ['Eventos', url_eventos($cidade)], [$e['titulo'], null]]);
    $url = web_absoluta(url_evento($e));
    $imagem = $e['imagem'] ? foto_url($e['imagem']) : null;
    $oficial = evento_link_oficial($e);
    $terminou = evento_terminou($e);
    $quando = evento_quando($e); $hr = evento_horario($e);
    $onde = implode(', ', array_filter([$e['local_nome'], $e['endereco']]));
    $mapa = $onde !== '' ? 'https://www.google.com/maps/search/?' . http_build_query(['api' => 1, 'query' => "$onde, $nome_cidade - $uf"]) : null;

    $ld = ['@context' => 'https://schema.org', '@type' => 'Event', 'name' => $e['titulo'], 'url' => $url,
        'startDate' => $e['data_inicio'] . ($e['hora_inicio'] ? 'T' . substr($e['hora_inicio'], 0, 5) . ':00-03:00' : ''),
        'eventStatus' => 'https://schema.org/EventScheduled', 'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'location' => ['@type' => 'Place', 'name' => $e['local_nome'] ?: $nome_cidade,
            'address' => array_filter(['@type' => 'PostalAddress', 'streetAddress' => $e['endereco'], 'addressLocality' => $nome_cidade, 'addressRegion' => $uf, 'addressCountry' => 'BR'])]];
    if ($e['data_fim'] || $e['hora_fim']) $ld['endDate'] = ($e['data_fim'] ?: $e['data_inicio']) . ($e['hora_fim'] ? 'T' . substr($e['hora_fim'], 0, 5) . ':00-03:00' : '');
    if ($e['descricao']) $ld['description'] = mb_substr($e['descricao'], 0, 500);
    if ($imagem) $ld['image'] = [$imagem];
    if ($oficial) $ld['sameAs'] = $oficial;

    $descricao = $e['titulo'] . ' em ' . $nome_cidade . ' (' . $uf . '): ' . $quando . ($hr ? ", $hr" : '') . ($onde ? ", $onde" : '') . '.'
        . ($e['descricao'] ? ' ' . mb_substr(preg_replace('/\s+/u', ' ', $e['descricao']), 0, 160) : '');
    pagina_inicio($e['titulo'] . " - $nome_cidade | Eventos | Acha Fácil!", mb_substr($descricao, 0, 300), 'web',
        ['canonica' => $url, 'imagem' => $imagem, 'noindex' => $terminou, 'head' => web_json_ld($ld, $ld_trilha)]);
    ?>
<main class="ficha">
  <div class="conteudo">
    <?= $trilha ?>
    <article class="ficha-caixa evento-pagina">
      <?php if ($imagem): ?><img class="evento-capa" src="<?= h($imagem) ?>" alt="<?= h($e['titulo']) ?>"><?php endif ?>
      <header class="ficha-topo">
        <?= evento_folhinha($e) ?>
        <div>
          <h1><?= h($e['titulo']) ?></h1>
          <p class="ficha-sub"><?= h(ucfirst($quando)) ?><?= $hr ? ' · ' . h($hr) : '' ?></p>
          <?php if ($terminou): ?><p class="ficha-selos"><span class="selo-fechado">Este evento já aconteceu</span></p><?php endif ?>
        </div>
      </header>

      <div class="ficha-botoes">
        <?php if ($oficial): ?><a class="botao amarelo" href="<?= h($oficial) ?>" rel="noopener" target="_blank">Página oficial do evento</a><?php endif ?>
        <?php if (!$terminou): ?><a class="botao contorno" href="<?= h(evento_google_agenda($e)) ?>" rel="nofollow noopener" target="_blank">Salvar na minha agenda</a><?php endif ?>
        <?php if ($mapa): ?><a class="botao contorno" href="<?= h($mapa) ?>" rel="nofollow noopener" target="_blank">Como chegar</a><?php endif ?>
      </div>

      <div class="ficha-colunas">
        <div>
          <?php if ($e['descricao']): ?><h2>Sobre o evento</h2><p><?= nl2br(h($e['descricao'])) ?></p><?php endif ?>
        </div>
        <div>
          <h2>Quando</h2>
          <p><?= h(ucfirst($quando)) ?><?= $hr ? '<br>' . h($hr) : '' ?></p>
          <h2>Onde</h2>
          <p><?= $e['local_nome'] ? '<b>' . h($e['local_nome']) . '</b><br>' : '' ?><?= $e['endereco'] ? h($e['endereco']) . '<br>' : '' ?><?= h("$nome_cidade - $uf") ?></p>
          <?php if ($e['preco']): ?><h2>Entrada</h2><p><?= h($e['preco']) ?></p><?php endif ?>
        </div>
      </div>
      <p class="discreto">Datas e valores podem mudar: confira sempre na página oficial do evento.</p>
    </article>
    <p><a class="botao contorno" href="<?= h(url_eventos($cidade)) ?>">← Todos os eventos de <?= h($nome_cidade) ?></a></p>
  </div>
  <?= web_baixe_app($cidade) ?>
</main>
<?php
    pagina_fim();
}
