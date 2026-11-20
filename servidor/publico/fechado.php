<?php
// 20/11/2026 (passo 17 do Plano D, pedido do usuário): o guia público do Acha Fácil fechou. Toda página pública
// (inicial, cidades, áreas, fichas, sitemaps, incluir, baixar APK) cai aqui, fora do Google (X-Robots-Tag no .htaccess),
// SEM redirecionamento (o Google levaria a nota ruim do domínio junto). Inicial = 200; o resto = 410 (sumiu de vez).
// Continuam abertos: admin/ (só admins), api/, google/, painel/ e o que o app em teste usa, termos e privacidade.
$caminho = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
http_response_code(in_array($caminho, ['/', '/index.php', '/acha-facil/', '/acha-facil/index.php'], true) ? 200 : 410);
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: public, max-age=600');
$novo = 'https://fsadesigners.com.br/guia-novo/';   // vira https://localizafacilapp.com.br/ quando o domínio novo estiver no ar
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow"><title>O Acha Fácil agora é Localiza Fácil</title>
<style>
  :root { --verde: #005C38; --amarelo: #F7C600; --creme: #FFF8E7; --texto: #10261b; }
  * { box-sizing: border-box; } html, body { margin: 0; }
  body { min-height: 100vh; display: grid; place-items: center; background: var(--creme); color: var(--texto);
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; padding: 24px 16px; text-align: center; }
  .caixa { max-width: 520px; background: #fff; border-radius: 24px; padding: 36px 24px; box-shadow: 0 10px 30px rgba(0,0,0,.08); }
  .caixa img { width: 96px; height: 96px; }
  h1 { font-size: clamp(1.6rem, 6vw, 2.2rem); line-height: 1.15; margin: 14px 0 10px; color: var(--verde); }
  h1 b { color: #b38f00; } p { font-size: 1.08rem; line-height: 1.55; color: #3b4d43; margin: 0 0 22px; }
  .botao { display: inline-block; background: var(--verde); color: #fff; font-weight: 800; font-size: 1.1rem;
    padding: 14px 28px; border-radius: 999px; text-decoration: none; }
  .botao:hover { background: #004a2d; } small { display: block; margin-top: 18px; color: #6b7a71; }
</style></head>
<body><main class="caixa">
  <img src="https://fsadesigners.com.br/guia-novo/assets/logo.png" alt="" width="96" height="96">
  <h1>O Acha Fácil agora é <b>Localiza Fácil</b></h1>
  <p>O guia de comércios e serviços da sua cidade mudou de nome e de endereço. Os mesmos comércios, com endereço, horário e WhatsApp.</p>
  <a class="botao" href="<?= htmlspecialchars($novo) ?>">Conheça o Localiza Fácil →</a>
  <small>localizafacilapp.com.br</small>
</main></body></html>
