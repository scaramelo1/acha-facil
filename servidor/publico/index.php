<?php
// Página de divulgação do app: números reais, áreas, como funciona, área do dono do negócio, perguntas e lojas
require __DIR__ . '/../inc/base.php';
require_once __DIR__ . '/../inc/guia.php';
require __DIR__ . '/../inc/pagina.php';
require_once __DIR__ . '/../inc/eventos.php';
require_once __DIR__ . '/../inc/numeros.php';
// Busca do topo (16/11): sem JavaScript o formulário vem para cá (?cidade=&q=) e vai para a busca da cidade
if (isset($_GET['cidade'])) {
    $c = (string)$_GET['cidade'];
    $q = trim((string)($_GET['q'] ?? ''));
    if (isset(CIDADES[$c])) {
        header('Location: ' . (mb_strlen($q) >= 2 ? url(cidade_web($c) . '/buscar') . '?q=' . rawurlencode($q) : url_cidade($c)), true, 302);
        exit;
    }
}
registrar_entrada_site('inicio');   // passo 38: quem chega pela página inicial

// Totais (áreas, cidades, comércios) e eventos guardados prontos por 1 hora (16/11): contar 44 mil fichas a cada visita
// deixava a inicial lenta. Aprovar alteração/importar limpa o cache (web_cache_limpar) e os números se atualizam.
require_once __DIR__ . '/../inc/web.php';
$dados = json_decode(web_cache('inicio-dados', function () {
    echo json_encode([
        'areas' => categorias_ativas(),
        'total' => (int)valor('SELECT COUNT(*) FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE ' . visivel()),
        'eventos' => eventos_proximos_todas(6),
        'por_cidade' => array_column(linhas('SELECT p.cidade, COUNT(*) AS total FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
            WHERE ' . visivel() . ' GROUP BY p.cidade'), 'total', 'cidade'),
        // 20/11: as mais procuradas = mais entradas no site nos últimos 30 dias (cartões grandes no topo de "Escolha a sua cidade")
        'populares' => entradas_prontas() ? array_column(linhas("SELECT cidade, SUM(total) AS t FROM entradas WHERE cidade IS NOT NULL
            AND dia >= CURDATE() - INTERVAL 29 DAY GROUP BY cidade ORDER BY t DESC LIMIT 20"), 'cidade') : [],
    ], JSON_UNESCAPED_UNICODE);
}), true) ?: [];
$areas = $dados['areas'] ?? [];
$total = (int)($dados['total'] ?? 0);
$eventos = $dados['eventos'] ?? [];
$por_cidade = $dados['por_cidade'] ?? [];
// 8 mais procuradas (só cidades ligadas); faltando dado, completa com as que têm mais comércios
$populares = array_values(array_filter($dados['populares'] ?? [], fn($c) => isset(CIDADES[$c])));
if (count($populares) < 8) { arsort($por_cidade); foreach (array_keys($por_cidade) as $c) if (isset(CIDADES[$c]) && !in_array($c, $populares, true)) $populares[] = $c; }
$populares = array_slice($populares, 0, 8);
$base = config()['url'];
$num = fn(int $n) => number_format($n, 0, ',', '.');
$por_uf = [];   // cidades separadas por estado, na ordem da tabela cidades
foreach (CIDADES as $slug => $c) $por_uf[$c[2]][$slug] = $c;
// 19/11 (pedido do usuário): dentro de cada estado, em ordem alfabética (Á junto do A)
$sem_acento = fn(string $s) => strtr(mb_strtolower($s), ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i',
    'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);
foreach ($por_uf as &$cidades_do_uf) uksort($cidades_do_uf, fn($a, $b) => strcmp($sem_acento($cidades_do_uf[$a][0]), $sem_acento($cidades_do_uf[$b][0])));
unset($cidades_do_uf);

// dados do site para o Google (16/11): nome do site e marca nos resultados
$ld_site = ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'Acha Fácil!', 'alternateName' => 'Acha Fácil',
    'url' => url_publica(), 'inLanguage' => 'pt-BR'];
$ld_org = ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'Acha Fácil!', 'url' => url_publica(),
    'logo' => url_publica('assets/logo.png'), 'slogan' => 'O comércio da sua cidade, pertinho de você.'];
// descrição até ~155 caracteres (o Google corta perto de 160)
$mil = $total >= 1000 ? number_format(intdiv($total, 1000), 0, ',', '.') . ' mil' : (string)$total;
pagina_inicio('Acha Fácil! O comércio da sua cidade no seu celular',
    "Guia grátis com $mil comércios em " . count(CIDADES) . ' cidades de ' . implode(' e ', array_keys($por_uf)) . '. Ache restaurante, mercado, oficina ou clínica e chame no WhatsApp.',
    '', ['anuncios' => true, 'head' => web_json_ld($ld_site, $ld_org)]);
?>
<main>
  <section class="hero">
    <div class="conteudo hero-busca">
      <h1>Tudo o que tem na <mark>sua cidade</mark>, num toque.</h1>
      <p class="lead">Ache restaurante, mercado, oficina, clínica ou loja e chame no WhatsApp, ligue ou veja como chegar.</p>
      <?php // largura da cidade = maior nome cadastrado (cresce sozinha quando entra cidade nova; mínimo o "Escolha a cidade")
        $maior_nome = 19; foreach (CIDADES as $slug => [$nome]) if (!empty($por_cidade[$slug])) $maior_nome = max($maior_nome, mb_strlen($nome)); ?>
      <form class="busca-topo" style="--cidade-largura: <?= min($maior_nome, 34) ?>ch" action="<?= h(url()) ?>" method="get" role="search" id="busca-topo">
        <label for="busca-cidade" class="so-leitor">Cidade</label>
        <select id="busca-cidade" name="cidade" required>
          <option value="">📍 Escolha a cidade</option>
          <?php foreach ($por_uf as $uf => $cidades_uf): ?><optgroup label="<?= h(NOMES_ESTADOS[$uf] ?? $uf) ?>">
            <?php foreach ($cidades_uf as $slug => [$nome]): if (empty($por_cidade[$slug])) continue; ?><option value="<?= h($slug) ?>" data-cidade="<?= h(url_cidade($slug)) ?>" data-buscar="<?= h(url(cidade_web($slug) . '/buscar')) ?>"><?= h($nome) ?></option><?php endforeach ?>
          </optgroup><?php endforeach ?>
        </select>
        <label for="busca-q" class="so-leitor">O que você procura?</label>
        <input id="busca-q" name="q" type="search" placeholder="O que você procura? pizza, farmácia, borracharia…" autocomplete="off" enterkeyhint="search">
        <button class="botao amarelo">🔍 Buscar</button>
      </form>
      <p class="busca-aviso" id="busca-aviso" hidden>Escolha a cidade primeiro.</p>
      <ul class="numeros">
        <li><b><?= $num($total) ?></b> comércios e serviços</li>
        <li><b><?= count(CIDADES) ?></b> <a href="#cidades">cidades</a></li>
      </ul>
      <p class="discreto">Grátis. Não precisa criar conta para procurar.</p>
    </div>
    <svg class="onda" viewBox="0 0 1200 48" preserveAspectRatio="none" aria-hidden="true"><path d="M0 24 C200 0 400 48 600 24 S1000 0 1200 24 V48 H0Z" fill="#fff"/></svg>
  </section>

  <section class="branco" id="cidades" style="padding-top:24px">
    <div class="conteudo">
      <h2>Escolha a sua cidade</h2>
      <p class="intro">Veja os comércios e serviços de cada cidade, com endereço, horário e WhatsApp, direto no site.</p>
      <?php // 20/11 (pedido do usuário): seção estava longa demais com 63 cartões. Busca + 8 mais procuradas + lista compacta.
            // Todas as cidades continuam com link na página (o Google chega às cidades novas por aqui). ?>
      <div class="cid-busca">
        <input type="search" id="cid-filtro" placeholder="Digite o nome da sua cidade..." autocomplete="off" aria-label="Procurar cidade">
      </div>
      <p class="cid-nada" id="cid-nada" hidden>Ainda não chegamos nessa cidade.<?php if ($e_cont = site('contato_email')): ?>
        <a href="mailto:<?= h($e_cont) ?>?subject=<?= rawurlencode('Quero o Acha Fácil na minha cidade') ?>">Peça a sua cidade</a>.<?php endif ?></p>
      <div id="cid-top">
        <h3 class="cidades-uf">Mais procuradas</h3>
        <div class="cidades-lista">
          <?php foreach ($populares as $slug): ?>
            <a href="<?= h(url_cidade($slug)) ?>"><b><?= h(CIDADES[$slug][0] . ' - ' . CIDADES[$slug][2]) ?></b><small><?= $num((int)($por_cidade[$slug] ?? 0)) ?> lugares</small></a>
          <?php endforeach ?>
        </div>
      </div>
      <?php foreach ($por_uf as $uf => $cidades_uf): ?>
        <?php // 20/11 (pedido do usuário): estado fechado, abre no "+"; a lista fica na página (o Google lê mesmo fechada) ?>
        <details class="cid-uf">
          <summary><h3 class="cidades-uf"><?= h(NOMES_ESTADOS[$uf] ?? $uf) ?> <small><?= count($cidades_uf) ?> <?= count($cidades_uf) === 1 ? 'cidade' : 'cidades' ?></small></h3></summary>
          <ul class="cid-compacta">
            <?php foreach ($cidades_uf as $slug => [$nome]): ?>
              <li data-busca="<?= h($sem_acento($nome)) ?>"><a href="<?= h(url_cidade($slug)) ?>"><span><?= h($nome) ?></span><small><?= $num((int)($por_cidade[$slug] ?? 0)) ?></small></a></li>
            <?php endforeach ?>
          </ul>
        </details>
      <?php endforeach ?>
      <script>
      (function () {
        var campo = document.getElementById('cid-filtro'); if (!campo) return;
        var top = document.getElementById('cid-top'), nada = document.getElementById('cid-nada');
        var limpa = function (s) { return s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, ''); };
        campo.addEventListener('input', function () {
          var q = limpa(campo.value.trim()), achou = 0;
          top.hidden = q !== '';
          document.querySelectorAll('.cid-uf').forEach(function (bloco) {
            var n = 0;
            bloco.querySelectorAll('li').forEach(function (li) { var ok = !q || li.dataset.busca.indexOf(q) !== -1; li.hidden = !ok; if (ok) n++; });
            bloco.hidden = n === 0; achou += n;
            if (q) { if (!('antes' in bloco.dataset)) bloco.dataset.antes = bloco.open ? '1' : ''; bloco.open = n > 0; }   // busca abre o estado
            else if ('antes' in bloco.dataset) { bloco.open = bloco.dataset.antes === '1'; delete bloco.dataset.antes; }   // apagou: volta como estava
          });
          nada.hidden = achou > 0;
        });
        campo.addEventListener('keydown', function (e) {   // Enter vai direto para a primeira cidade achada
          if (e.key !== 'Enter') return;
          var a = document.querySelector('.cid-uf:not([hidden]) li:not([hidden]) a'); if (a && campo.value.trim()) { e.preventDefault(); location.href = a.href; }
        });
      })();
      </script>
    </div>
  </section>

  <?php // 19/11 (avaliação do site): a seção só aparece com evento marcado; quando o último passa, some sozinha
        // (o cache é por dia e o admin limpa ao aprovar). Sem evento, fica só a linha para divulgar.
        // Cada evento já leva à agenda da cidade dele, então saiu a 3ª lista com as 22 cidades.
  if (eventos_prontos() && $eventos): ?>
  <section id="eventos">
    <div class="conteudo">
      <h2>Próximos eventos na região</h2>
      <p class="intro">Shows, festas e feiras das cidades do Acha Fácil, em ordem de data.</p>
      <ol class="agenda-lista agenda-inicio">
        <?php foreach ($eventos as $e): ?>
          <li><a class="evento-cartao" href="<?= h(url_evento($e)) ?>">
            <?= evento_folhinha($e) ?>
            <span class="evento-info">
              <b><?= h($e['titulo']) ?></b>
              <span><?= h(evento_quando($e)) ?><?= ($hr = evento_horario($e)) ? ' · ' . h($hr) : '' ?></span>
              <span>📍 <?= h(($e['local_nome'] ? $e['local_nome'] . ', ' : '') . nome_cidade($e['cidade'])) ?></span>
            </span>
          </a></li>
        <?php endforeach ?>
      </ol>
      <?= evento_convite_divulgar() ?>
    </div>
  </section>
  <?php elseif (eventos_prontos()): ?>
  <p class="conteudo divulgar-evento-linha">📅 Vai ter um show, festa ou feira? <a href="<?= h(url('incluir-evento.php')) ?>">Divulgue o evento de graça</a></p>
  <?php endif ?>

  <section id="areas">
    <div class="conteudo">
      <h2>De padaria a oficina</h2>
      <p class="intro">Os comércios de cada cidade organizados por área. Dentro de cada uma, filtre pelo tipo: pizzaria, academia, pet shop…</p>
      <div class="areas">
        <?php foreach ($areas as $a): ?>
          <div class="area"><i style="background:<?= h($a['cor']) ?>"><?= emoji_area($a['icone']) ?></i><span><b><?= h($a['nome']) ?></b><small><?= $num($a['total']) ?> lugares</small></span></div>
        <?php endforeach ?>
      </div>
    </div>
  </section>

  <section class="verde" id="como-funciona">
    <div class="conteudo">
      <h2>Do celular até a porta da loja</h2>
      <p class="intro">Feito para todo mundo usar, com letras grandes e botões fáceis de tocar.</p>
      <div class="passos">
        <div class="passo"><h3>Procure</h3><p>Escreva o que precisa ou toque numa área da tela inicial.</p></div>
        <div class="passo"><h3>Escolha</h3><p>Veja endereço, telefone, Instagram e as estrelas dadas por outros moradores.</p></div>
        <div class="passo"><h3>Chame ou vá</h3><p>WhatsApp, ligação ou rota no mapa, direto com o comércio, sem intermediário.</p></div>
      </div>
    </div>
  </section>

  <section id="seu-negocio">
    <div class="conteudo duas">
      <div>
        <h2>Seu negócio já pode estar no Acha Fácil</h2>
        <p class="intro">Montamos o guia com os dados públicos dos comércios das cidades atendidas. Procure o seu e assuma a sua ficha de graça para corrigir os dados.</p>
        <?= numeros_publicos_html() /* 19/11 (passo 116): números de verdade */ ?>
        <ul class="lista-check">
          <li>Grátis: corrija telefone, WhatsApp, endereço e horário</li>
          <li>No Básico: coloque logo, 2 fotos, descrição e o que você vende ou faz</li>
          <li>No Destaque: selo "Verificado pelo Acha Fácil", responder as avaliações e ver quantas pessoas abriram sua página</li>
        </ul>
        <p class="discreto">Não está no app? <a href="<?= $base ?>/incluir.php">Inclua o seu negócio de graça</a>; a equipe confere antes de publicar.</p>
        <p class="discreto">Não quer aparecer? Procure o seu negócio e toque em "Tirar do app". Retiramos sem perguntas.</p>
      </div>
      <div class="caixa">
        <h3>Procure seu negócio</h3>
        <form class="procurar" action="<?= $base ?>/busca.php" method="get">
          <select name="cidade" aria-label="Cidade do seu comércio" required><?= opcoes_cidades() ?></select>
          <input name="q" placeholder="Nome do seu comércio" aria-label="Nome do seu comércio" required minlength="2">
          <button class="botao amarelo">Procurar</button>
        </form>
        <div class="planos">
          <div class="plano"><h3>Grátis <span class="preco">R$ 0</span></h3><p>Assuma a sua ficha com a conta Google e corrija nome, telefone, WhatsApp, endereço, site e horário. Selo "Dados confirmados pelo dono".</p></div>
          <div class="plano"><h3>Básico <span class="preco"><?= h(preco_plano('basico')) ?> uma vez só</span></h3><p>Tudo do Grátis e mais logo, 2 fotos, descrição, o que você vende ou faz, Instagram, "atende em casa / entrega" e quantas pessoas viram sua ficha. Sem mensalidade.</p></div>
          <div class="plano destaque"><h3>Destaque <span class="preco"><?= h(preco_plano('destaque')) ?>/mês</span></h3><p>Tudo do Básico, até 10 fotos, selo de verificado, responder as avaliações, números completos da sua página e aparecer no topo da tela inicial e da sua área.</p></div>
          <?php if (plano_site_pronto()): ?><div class="plano destaque"><h3>Destaque + Site <span class="preco"><?= h(preco_plano('site')) ?>/mês</span></h3><p>Tudo do Destaque e um site próprio do seu negócio, feito pela equipe do Acha Fácil, com domínio incluso.</p></div><?php endif ?>
        </div>
        <p style="margin:16px 0 0"><a class="botao" href="<?= $base ?>/planos.php">Conferir os planos</a></p>
      </div>
    </div>
  </section>

  <section class="branco">
    <div class="conteudo" style="max-width:820px">
      <h2>Perguntas frequentes</h2>
      <details><summary>Quanto custa para usar o app?</summary><p>Nada. Procurar, ver avaliações e chamar no WhatsApp é grátis.</p></details>
      <details><summary>Preciso criar conta?</summary><p>Não. A conta (só nome, e-mail e senha) serve para quem quer avaliar, comentar ou salvar lugares. Você pode excluir sua conta a qualquer momento pelo próprio app.</p></details>
      <details><summary>De onde vêm os comércios?</summary><p>De informações públicas (nome, endereço, telefone e redes sociais que o próprio comércio divulga na internet). O dono pode assumir o perfil e corrigir tudo, ou pedir para tirar do app pela <a href="<?= $base ?>/busca.php">busca do site</a>.</p></details>
      <details><summary>O Acha Fácil vende ou faz os serviços?</summary><p>Não. O Acha Fácil só mostra quem é quem. Preço, prazo e garantia são combinados direto com o comércio.</p></details>
      <details><summary>Em que ordem aparecem?</summary><p>Quem paga o Destaque aparece primeiro. Depois vêm as fichas completas (dos donos que pagaram o Básico) e os demais. Dentro de cada grupo, a ordem muda todo dia, para ser justa com todos.</p></details>
      <details id="instalar-apk"><summary>Como instalar o APK no Android?</summary>
        <p>1. Toque em <strong>Baixar APK</strong> e espere terminar o download.<br>
        2. Toque na notificação do download (ou abra o arquivo <strong>acha-facil.apk</strong> na pasta Downloads).<br>
        3. Se o celular avisar que é de "fonte desconhecida", toque em <strong>Configurações</strong>, ligue <strong>Permitir desta fonte</strong> e volte.<br>
        4. Toque em <strong>Instalar</strong> e depois em <strong>Abrir</strong>.<br>
        Para atualizar, é só baixar de novo e instalar por cima. No iPhone, o app virá pela App Store.</p></details>
      <details><summary>Achei uma informação errada. O que faço?</summary><p>Fale com a gente: <?= contatos_html() ?>. Se for o dono, <a href="<?= $base ?>/busca.php">procure o seu negócio</a> e corrija os dados de graça.</p></details>
    </div>
  </section>

  <section class="verde" id="baixar">
    <div class="conteudo baixe-app">
      <div>
        <h2>Baixe o Acha Fácil!</h2>
        <p class="intro">O comércio da sua cidade, pertinho de você. No app ainda tem "Perto de você", favoritos e avisos.</p>
        <div class="lojas"><?= botoes_lojas() ?></div>
      </div>
      <div class="celular" aria-hidden="true">
        <div class="ola"><small>Boa tarde!</small>O que você procura hoje?</div>
        <div class="busca">🔍 pizza, borracharia, farmácia…</div>
        <div class="grade"><span>🍽️</span><span>🛒</span><span>🛠️</span><span>🩺</span><span>💄</span><span>🚗</span></div>
        <div class="item"><b>Conhecidos na cidade</b>Os mais procurados da cidade, todo dia uma seleção nova</div>
      </div>
    </div>
  </section>
</main>
<script>
// Busca do topo: vai direto para a busca da cidade e lembra a última cidade escolhida neste navegador
(function () {
  var f = document.getElementById('busca-topo'), sel = document.getElementById('busca-cidade'),
      q = document.getElementById('busca-q'), aviso = document.getElementById('busca-aviso'), CH = 'af_cidade';
  function guardar(v) { try { localStorage.setItem(CH, v); } catch (e) {} }
  try { var v = localStorage.getItem(CH); if (v && sel.querySelector('option[value="' + v + '"]')) sel.value = v; } catch (e) {}
  sel.addEventListener('change', function () { aviso.hidden = true; if (sel.value) { guardar(sel.value); q.focus(); } });
  f.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var op = sel.options[sel.selectedIndex];
    if (!sel.value) { aviso.hidden = false; sel.focus(); return; }
    guardar(sel.value);
    var t = q.value.trim();
    location.href = t.length >= 2 ? op.getAttribute('data-buscar') + '?q=' + encodeURIComponent(t) : op.getAttribute('data-cidade');
  });
  // quem toca numa cidade da lista também fica com ela guardada
  document.querySelectorAll('#cidades .cidades-lista a').forEach(function (a, i) {
    a.addEventListener('click', function () { var op = sel.querySelector('option[data-cidade="' + a.getAttribute('href') + '"]'); if (op) guardar(op.value); });
  });
})();
</script>
<?php pagina_fim();
