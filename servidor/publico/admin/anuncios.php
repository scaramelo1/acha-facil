<?php
// Anúncios do Google AdSense no site (15/11): liga/desliga na hora. Desligado = arquivo "anuncios-desligados" no dados_dir.
// Onde aparecem: inicial, cidade, listas, busca pública, eventos e fichas de quem não paga plano (opção 'anuncios' de pagina_inicio()).
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/pagina.php';
$admin = exigir_admin();

if (e_post()) {
    exigir_post();
    $ligar = ($_POST['acao'] ?? '') === 'ligar';
    $arq = anuncios_arquivo_desligado();
    $ok = $ligar ? (!is_file($arq) || @unlink($arq)) : (@file_put_contents($arq, date('c') . "\n") !== false);
    if (!$ok) flash('Não consegui mudar (permissão da pasta de dados).', 'erro');
    else {
        web_cache_limpar();   // as páginas ficam em cache: refaz para o anúncio entrar ou sair na hora
        registrar((int)$admin['id'], 'anuncios', null, $ligar ? 'ligados' : 'desligados');
        flash($ligar ? 'Anúncios ligados.' : 'Anúncios desligados. Some do site na hora.');
    }
    ir('admin/anuncios.php');
}

$ligados = anuncios_ligados();
admin_inicio('Anúncios', $admin);
?>
<h1>Anúncios do Google (AdSense)</h1>
<div class="quadro">
  <p>Situação: <strong><?= $ligados ? 'ligados' : 'desligados' ?></strong> · código <code><?= h(adsense_cliente()) ?></code></p>
  <form method="post"><?= campo_csrf() ?>
    <input type="hidden" name="acao" value="<?= $ligados ? 'desligar' : 'ligar' ?>">
    <button class="botao"><?= $ligados ? 'Desligar anúncios' : 'Ligar anúncios' ?></button>
  </form>
</div>
<div class="quadro">
  <h2>Onde aparecem</h2>
  <p><strong>Com anúncio:</strong> página inicial, página da cidade, listas por área e tipo, busca da cidade, agenda e páginas de eventos,
    e fichas de comércios que <strong>não</strong> pagam plano.</p>
  <p><strong>Sem anúncio:</strong> fichas de quem paga Básico, Destaque ou Destaque + Site; planos, pagamento, incluir, entrar,
    minha conta, painel, admin, termos e privacidade.</p>
  <p class="discreto">Os lugares e formatos (barra, no meio do texto etc.) se escolhem no próprio AdSense, em "Anúncios" →
    "Por site" → achafacilapp.com.br. Recomendado: anúncios automáticos ligados, com a "vinheta" (tela cheia) desligada.
    Nunca clique nos seus próprios anúncios.</p>
  <p class="discreto">Arquivo de autorização: <a href="/ads.txt" target="_blank" rel="noopener">/ads.txt</a>.</p>
</div>
<?php admin_fim();
