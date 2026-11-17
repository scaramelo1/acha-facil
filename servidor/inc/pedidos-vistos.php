<?php
// Pedidos "vistos, aguardando retorno" (17/11, passo 89, pedido do usuário): o admin marca que já viu um pedido não
// resolvido (ex.: escolheu plano e não pagou) e ele sai da contagem de Pendências e da lista do app, sem recusar nem
// resolver. Se o pedido for pago depois, volta a contar. Guardado em <dados_dir>/pedidos-vistos.json ({id: data}),
// sem mudança no banco.
declare(strict_types=1);

function pedidos_vistos_arquivo(): string { return config()['dados_dir'] . '/pedidos-vistos.json'; }

/** @return array<int,string> id do pedido => quando foi marcado */
function pedidos_vistos(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $j = @file_get_contents(pedidos_vistos_arquivo());
    $cache = [];
    foreach ((json_decode((string)$j, true) ?: []) as $id => $quando) $cache[(int)$id] = (string)$quando;
    return $cache;
}

function pedido_marcar_visto(int $id, bool $visto): void {
    $arq = pedidos_vistos_arquivo();
    $f = fopen($arq, 'c+');
    if (!$f) throw new RuntimeException('Não deu para gravar ' . $arq);
    flock($f, LOCK_EX);
    $lista = json_decode((string)stream_get_contents($f), true) ?: [];
    if ($visto) $lista[(string)$id] = date('Y-m-d H:i:s'); else unset($lista[(string)$id]);
    // limpa pedidos que já saíram de "novo" (resolvidos ou recusados)
    if ($lista) {
        $abertos = array_map('intval', array_column(linhas('SELECT id FROM pedidos WHERE status = \'novo\' AND id IN ('
            . implode(',', array_map('intval', array_keys($lista))) . ')'), 'id'));
        $lista = array_intersect_key($lista, array_flip(array_map('strval', $abertos)));
    }
    ftruncate($f, 0); rewind($f);
    fwrite($f, json_encode($lista ?: new stdClass()));
    fflush($f); flock($f, LOCK_UN); fclose($f);
}

/** O pedido conta como pendência? Visto e não pago = não conta. */
function pedido_pendente(array $pe): bool {
    if (($pe['status'] ?? '') !== 'novo') return false;
    if (in_array($pe['mp_status'] ?? '', ['authorized', 'approved'], true)) return true;
    return !isset(pedidos_vistos()[(int)$pe['id']]);
}
