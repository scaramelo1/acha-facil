<?php
// Versão do APK publicado no site (publicar-apk.sh grava versao.txt) e o link para baixar.
// O app compara com a versão instalada e avisa quando há uma mais nova (src/versao.ts).
require __DIR__ . '/../../inc/api.php';
require_once __DIR__ . '/../../inc/apk.php';
$info = apk_info();
$v = $info['versao'] ?? '';
responder(['versao' => preg_match('/^\d{1,4}(\.\d{1,4}){0,2}$/', $v) ? $v : null, 'link' => $info ? url_publica('baixar-apk.php') : null]);
