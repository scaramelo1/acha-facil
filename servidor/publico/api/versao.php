<?php
// Versão nova do app (src/versao.ts compara com a instalada e avisa):
//   versao/link: APK publicado no site (publicar-apk.sh grava versao.txt) — para quem instalou pelo site.
//   play: versão informada em /admin/app.php e a página do app na Play Store — para quem instalou pela Play.
require __DIR__ . '/../../inc/api.php';
require_once __DIR__ . '/../../inc/apk.php';
$info = apk_info();
$v = $info['versao'] ?? '';
responder([
    'versao' => preg_match('/^\d{1,4}(\.\d{1,4}){0,2}$/', $v) ? $v : null,
    'link' => $info ? url_publica('baixar-apk.php') : null,
    'play' => ['versao' => versao_play(), 'link' => link_play(), 'pacote' => PACOTE_ANDROID],
]);
