<?php
// Recebe a lista do robô direto do PC (scripts/robo/robo-rodar.ps1), sem login: vale a chave do robô
// (gerada em /admin/novidades.php) no cabeçalho X-Chave-Robo. Confere em modo TESTE e guarda em Novidades;
// com o modo automático ligado, já importa. Avisa os admins no celular. Responde em texto simples.
//   curl.exe -H "X-Chave-Robo: af_..." -F "arquivos[]=@leads_sao_joao.html" [-F cidade=sao-joao] [-F importar=1] https://achafacilapp.com.br/admin/robo-enviar.php
declare(strict_types=1);
require __DIR__ . '/../../inc/base.php';
require_once __DIR__ . '/../../inc/novidades.php';
require_once __DIR__ . '/../../inc/avisos.php';
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
@set_time_limit(900);
ignore_user_abort(true);   // se o PC desligar no meio, a importação termina mesmo assim

function sair(string $msg, int $status): never { http_response_code($status); echo $msg, "\n"; exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') sair('Use POST.', 405);
if (!robo_chave_confere((string)($_SERVER['HTTP_X_CHAVE_ROBO'] ?? ''))) {
    sleep(2);
    error_log('acha-facil robo-enviar: chave errada de ' . ip());
    sair('ERRO: chave do robô errada ou não gerada. Gere em /admin/novidades.php e coloque no robo-config.json do PC.', 403);
}

$f = $_FILES['arquivos'] ?? null;
if (!$f || !is_array($f['name']) || !$f['name'][0]) sair('ERRO: nenhum arquivo recebido (o limite é 30 MB).', 400);
$html = ''; $nomes = [];
foreach ($f['name'] as $i => $nome) {
    if ($f['error'][$i] !== UPLOAD_ERR_OK) sair("ERRO: não recebi $nome (erro {$f['error'][$i]}).", 400);
    $html .= file_get_contents($f['tmp_name'][$i]) . "\n";
    $nomes[] = basename((string)$nome);
}

try {
    $m = nov_criar($html, (string)($_POST['cidade'] ?? ''), 'robô (PC)', $nomes);
} catch (RuntimeException $e) {
    sair('ERRO: ' . $e->getMessage(), 422);
}
$cidade = nome_cidade($m['cidade']);
$base = url_publica('admin/novidades.php?id=' . $m['id']);
$tem_novidade = $m['novos'] + $m['mudaram'] + $m['fecharam'] > 0;

// 20/11: importar=1 (scripts/robo/enviar-listas.ps1) importa na hora, mesmo com o modo automático desligado
if (nov_config()['automatico'] || ($_POST['importar'] ?? '') === '1') {
    try {
        $m = nov_importar($m, null);
        nov_descartar_antigas($m['cidade'], $m['id']);
    } catch (RuntimeException $e) {
        avisar_admins("Robô: erro em $cidade", mb_substr($e->getMessage(), 0, 150));
        sair('ERRO ao importar: ' . $e->getMessage(), 500);
    }
    // 20/11: ligar=1 (enviar-listas.ps1) já liga a cidade no app depois de importar, se ela tiver comércios
    $ligou = '';
    if (($_POST['ligar'] ?? '') === '1' && !(TODAS_CIDADES[$m['cidade']][5] ?? false)) {
        $n = (int)valor("SELECT COUNT(*) FROM profissionais WHERE cidade = ? AND status = 'ativo'", [$m['cidade']]);
        if ($n > 0) {
            q('UPDATE cidades SET ativa = 1 WHERE slug = ?', [$m['cidade']]);
            web_cache_limpar();
            registrar(null, 'ligou_cidade', null, "$cidade (robô, enviar-listas)");
            $w = cidade_web($m['cidade']);
            $ligou = " LIGADA no app ($n comércios). Sitemap para o Search Console: " . url_publica("sitemap-$w-comercios.xml");
        } else $ligou = ' NÃO liguei: a cidade ficou sem comércios.';
    }
    if ($tem_novidade) avisar_admins("Novidades em $cidade", nov_resumo($m) . ' (já no app).' . ($ligou ? ' Cidade ligada.' : ''));
    sair("OK: $cidade importada sozinha. " . nov_resumo($m) . ".$ligou Detalhes: $base", 200);
}

nov_descartar_antigas($m['cidade'], $m['id']);
if ($tem_novidade) avisar_admins("Novidades em $cidade", nov_resumo($m) . '. Confira em Admin > Novidades.');
else { $m['status'] = 'descartada'; $m['descartada_em'] = date('c'); $m['sem_novidade'] = true; @unlink(nov_dir() . "/{$m['id']}.html"); nov_salvar($m); }
sair("OK: $cidade recebida. " . nov_resumo($m) . ($tem_novidade ? ". Esperando você aprovar: $base" : '. Nada novo; nada a fazer.'), 200);
