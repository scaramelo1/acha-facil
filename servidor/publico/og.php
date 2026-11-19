<?php
// Imagem de compartilhar (og:image) da cidade ou da inicial: 1200x630 com o número de comércios (19/11, avaliação do site).
// ?c=<slug da cidade> ou ?c=inicio. Gera com og-imagem.py (Pillow) na 1ª vez e de novo a cada 7 dias; guarda em
// <dados_dir>/og/. Se não der para gerar, manda para o logo, como era antes.
require __DIR__ . '/../inc/base.php';
require_once __DIR__ . '/../inc/guia.php';

$c = (string)($_GET['c'] ?? 'inicio');
if ($c !== 'inicio' && !isset(CIDADES[$c])) $c = 'inicio';
$dir = rtrim((string)(config()['dados_dir'] ?? '/var/lib/acha-facil'), '/') . '/og';
$arq = "$dir/$c.png";
$num = fn(int $n) => number_format($n, 0, ',', '.');

if (!is_file($arq) || filemtime($arq) < time() - 7 * 86400) {
    @mkdir($dir, 0750, true);
    $trava = @fopen("$dir/.trava", 'c');
    if ($trava && flock($trava, LOCK_EX)) {
        clearstatcache(true, $arq);
        if (!is_file($arq) || filemtime($arq) < time() - 7 * 86400) {   // outro pedido pode ter gerado enquanto esperava
            if ($c === 'inicio') {
                $total = (int)valor('SELECT COUNT(*) FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE ' . visivel());
                $ufs = array_values(array_unique(array_column(CIDADES, 2)));   // na ordem da tabela cidades, como na inicial (SP e MG)
                $l3 = count(CIDADES) . ' cidades de ' . implode(' e ', $ufs);
            } else {
                $total = (int)valor('SELECT COUNT(*) FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE ' . visivel($c));
                $l3 = CIDADES[$c][0] . ' - ' . CIDADES[$c][2];
            }
            exec('python3 ' . escapeshellarg(__DIR__ . '/../og-imagem.py') . ' ' . escapeshellarg($arq) . ' '
                . escapeshellarg($num($total)) . ' ' . escapeshellarg('comércios e serviços em') . ' ' . escapeshellarg($l3) . ' 2>&1', $saida, $erro);
            if ($erro) error_log('og.php: ' . implode(' ', $saida));
        }
        flock($trava, LOCK_UN);
    }
}
if (!is_file($arq)) { header('Location: ' . url_publica('assets/logo.png'), true, 302); exit; }
header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . filesize($arq));
readfile($arq);
