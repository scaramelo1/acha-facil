<?php
// Quem chamar hoje (19/11, passo 116, pedido do usuário): comércios SEM dono mais vistos no período, com a mensagem de
// WhatsApp pronta mostrando os números da ficha. O admin marca "✓ Chamei" e o comércio sai da lista por 30 dias.
// Marcados em <dados_dir>/prospeccao-chamados.json ({id: {em, admin, como}}), sem mudança no banco.
declare(strict_types=1);

const PROSPECCAO_ESCONDER_DIAS = 30;

function prospeccao_arquivo(): string { return config()['dados_dir'] . '/prospeccao-chamados.json'; }

/** @return array<int,array{em:string,admin:string,como:string}> */
function prospeccao_chamados(): array {
    $j = json_decode((string)@file_get_contents(prospeccao_arquivo()), true) ?: [];
    $r = [];
    foreach ($j as $id => $v) $r[(int)$id] = (array)$v;
    return $r;
}

function prospeccao_marcar(int $id, ?string $admin, string $como = 'whatsapp'): void {
    $f = fopen(prospeccao_arquivo(), 'c+');
    if (!$f) throw new RuntimeException('Não deu para gravar a lista de chamados.');
    flock($f, LOCK_EX);
    $l = json_decode((string)stream_get_contents($f), true) ?: [];
    if ($admin === null) unset($l[(string)$id]);
    else $l[(string)$id] = ['em' => date('Y-m-d H:i:s'), 'admin' => $admin, 'como' => $como];
    // limpa os antigos (mais de 90 dias)
    foreach ($l as $k => $v) if (strtotime((string)($v['em'] ?? '')) < strtotime('-90 days')) unset($l[$k]);
    ftruncate($f, 0); rewind($f);
    fwrite($f, json_encode($l, JSON_UNESCAPED_UNICODE));
    flock($f, LOCK_UN); fclose($f);
}

/** Celular (dá para WhatsApp): 11 dígitos com 9 depois do DDD. */
function prospeccao_celular(?string $t): ?string {
    $d = so_digitos($t);
    if (strlen($d) === 13 && str_starts_with($d, '55')) $d = substr($d, 2);
    return strlen($d) === 11 && $d[2] === '9' ? $d : null;
}

/** Mensagem pronta para o dono, com os números da ficha. */
function prospeccao_mensagem(array $l, int $dias): string {
    $pedidos = [];
    foreach (['whatsapp' => 'o WhatsApp', 'horario' => 'o horário', 'fotos' => 'fotos', 'sobre' => 'o que vocês fazem ou vendem'] as $k => $txt)
        if ((int)($l["ped_$k"] ?? 0) > 0) $pedidos[] = $txt;
    $lista_ped = count($pedidos) > 1 ? implode(', ', array_slice($pedidos, 0, -1)) . ' e ' . end($pedidos) : ($pedidos[0] ?? '');
    $vis = (int)$l['vis'];
    $msg = 'Olá! Aqui é da equipe do Acha Fácil, o guia de comércios de ' . nome_cidade((string)$l['cidade']) . ' (achafacilapp.com.br). '
        . 'A página do ' . $l['nome'] . ' foi vista ' . $vis . ($vis === 1 ? ' vez' : ' vezes') . ' nos últimos ' . $dias . ' dias'
        . ((int)$l['pedidos'] > 0 ? ', e recebeu ' . (int)$l['pedidos'] . ((int)$l['pedidos'] === 1 ? ' pedido' : ' pedidos') . ' de informação de quem queria saber ' . $lista_ped : '')
        . ".\n\nVocê pode assumir a sua página de graça e corrigir telefone, WhatsApp, endereço e horário (com horário, ela aparece no \"Aberto agora\"). "
        . "É só tocar em \"Cadastrar grátis\" aqui:\n" . url_publica(caminho_ficha((int)$l['id'], (string)$l['nome'], (string)$l['cidade']));
    return $msg;
}
