<?php
// Administração pelo app (só contas cujo e-mail está em admins).
// GET: pendências (alterações dos donos com antes x depois, pedidos novos, cidades pedidas).
// POST {acao: 'aprovar' | 'recusar', id, motivo}: decide uma alteração (as mesmas regras do /admin do site).
// POST {acao: 'apagar_cidade', cidade, uf}: tira a cidade da lista do "Quero minha Cidade!".
// POST {acao: 'autorizar_evento' | 'recusar_evento', id}: evento enviado pelo público em /incluir-evento.php.
require __DIR__ . '/../../inc/api.php';
require_once __DIR__ . '/../../inc/moderacao.php';
require_once __DIR__ . '/../../inc/avisos.php';
require_once __DIR__ . '/../../inc/eventos.php';
$m = metodo('GET', 'POST');
$u = exigir_usuario();
$admin_id = admin_do_usuario($u) ?? erro('Só para administradores.', 403);

// eventos enviados pelo público (passo 36): autorizar ou recusar
if ($m === 'POST' && in_array(campo('acao', 20), ['autorizar_evento', 'recusar_evento'], true)) {
    $id = (int)(corpo()['id'] ?? 0);
    $e = campo('acao', 20) === 'autorizar_evento' ? autorizar_evento($id, $admin_id) : recusar_evento($id, $admin_id);
    if (!$e) erro('Esse evento já foi decidido por outro administrador.', 409);
    responder(['ok' => true]);
}
if ($m === 'POST' && campo('acao', 20) === 'apagar_cidade') {
    // tira uma cidade da lista do "Quero minha Cidade!" (todos os pedidos dela)
    $cid = campo('cidade', 80);
    $uf = campo('uf', 2);
    $n = q('DELETE FROM cidades_pedidas WHERE LOWER(cidade) = LOWER(?) AND uf <=> ?', [$cid, $uf === '' ? null : $uf])->rowCount();
    registrar($admin_id, 'apagou_cidade', null, "$cid" . ($uf !== '' ? " ($uf)" : '') . ": $n pedido(s)");
    responder(['ok' => true]);
}
if ($m === 'POST') {
    $id = (int)(corpo()['id'] ?? 0);
    try {
        if (campo('acao', 10) === 'aprovar') aprovar_alteracao($id, $admin_id);
        else {
            $motivo = campo('motivo', 500);
            if ($motivo === '') erro('Escreva o motivo da recusa (o dono vê no painel).');
            recusar_alteracao($id, $admin_id, $motivo);
        }
    } catch (RuntimeException $e) {
        erro($e->getMessage(), 409);
    }
    responder(['ok' => true]);
}

$texto = function (string $c, $v): ?string {
    if ($v === null || $v === '') return null;
    $v = (string)$v;
    if ($c === 'foto') return foto_url($v);
    if ($c === 'horarios') return implode("\n", horarios_linhas(horarios_ler($v))) ?: null;   // um campo por linha
    if ($c === 'fotos') return implode("\n", array_map('foto_url', lista_fotos($v))) ?: null;   // uma URL por linha
    if ($c === 'atende_em_casa') return $v === '1' ? 'Sim' : 'Não';
    if ($c === 'telefone' || $c === 'whatsapp') return telefone_formatado($v);
    return $v;
};

$alteracoes = [];
foreach (linhas("SELECT a.*, d.nome AS dono, d.telefone AS dono_tel, d.pedido_id, d.admin_id AS liberado_por FROM alteracoes a
        LEFT JOIN acessos_dono d ON d.id = a.acesso_id WHERE a.status = 'pendente' ORDER BY a.criado_em") as $a) {
    $p = linha('SELECT * FROM profissionais WHERE id = ?', [$a['profissional_id']]);
    $pago = $a['pedido_id'] ? linha('SELECT nome, telefone, email, plano, mp_status FROM pedidos WHERE id = ?', [$a['pedido_id']]) : null;
    $ja = $a['acesso_id'] && (int)valor("SELECT COUNT(*) FROM alteracoes WHERE acesso_id = ? AND status = 'aprovada'", [$a['acesso_id']]) > 0;
    $campos = [];
    foreach (json_decode($a['novos'], true) ?: [] as $c => $v) {
        if (!isset(CAMPOS_DONO[$c])) continue;
        $campos[] = ['campo' => $c, 'rotulo' => CAMPOS_DONO[$c][0], 'antes' => $texto($c, $p[$c]), 'depois' => $texto($c, $v)];
    }
    $alteracoes[] = [
        'id' => (int)$a['id'], 'comercio' => $p['nome'], 'cidade' => nome_cidade($p['cidade'], true), 'quando' => datahora_br($a['criado_em']),
        'dono' => $a['dono'], 'donoTelefone' => telefone_formatado($a['dono_tel']),
        'pago' => $pago ? ['nome' => $pago['nome'], 'telefone' => telefone_formatado($pago['telefone']), 'email' => $pago['email'] ?? null,
            'plano' => PLANOS_NOME[$pago['plano']] ?? null, 'status' => $pago['mp_status'] ?? null] : null,
        // 1ª mudança de quem entrou só pelo pagamento: conferir pelo telefone que já está no app antes de aprovar
        'conferirTelefone' => !$ja && !$a['liberado_por'] ? (telefone_formatado($p['telefone'] ?: $p['whatsapp']) ?: null) : null,
        'primeira' => !$ja && !$a['liberado_por'],
        'campos' => $campos,
    ];
}

$pedidos = array_map(fn($pe) => [
    'id' => (int)$pe['id'], 'tipo' => $pe['tipo'], 'plano' => $pe['plano'] ? PLANOS_NOME[$pe['plano']] ?? null : null,
    'pago' => in_array($pe['mp_status'] ?? '', ['authorized', 'approved'], true), 'comercio' => $pe['comercio'], 'nome' => $pe['nome'],
    'telefone' => telefone_formatado($pe['telefone']), 'quando' => datahora_br($pe['criado_em']),
], linhas("SELECT pe.*, p.nome AS comercio FROM pedidos pe LEFT JOIN profissionais p ON p.id = pe.profissional_id
    WHERE pe.status = 'novo' ORDER BY pe.criado_em DESC LIMIT 50"));

$cidades = array_map(fn($c) => ['cidade' => $c['cidade'], 'uf' => $c['uf'], 'total' => (int)$c['total'], 'ultimo' => datahora_br($c['ultimo'])],
    linhas('SELECT MIN(cidade) AS cidade, uf, COUNT(*) AS total, MAX(criado_em) AS ultimo FROM cidades_pedidas
        GROUP BY LOWER(cidade), uf ORDER BY ultimo DESC LIMIT 30'));

$eventos = array_map(fn($e) => [
    'id' => (int)$e['id'], 'titulo' => $e['titulo'], 'cidade' => nome_cidade($e['cidade'], true),
    'quando' => ucfirst(evento_quando($e)), 'horario' => evento_horario($e),
    'local' => implode(', ', array_filter([$e['local_nome'], $e['endereco']])) ?: null, 'preco' => $e['preco'],
    'descricao' => $e['descricao'], 'linkOficial' => evento_link_oficial($e), 'imagem' => $e['imagem'] ? foto_url($e['imagem']) : null,
    'enviadoPor' => $e['enviado_nome'], 'telefone' => telefone_formatado($e['enviado_contato']), 'whatsapp' => link_whatsapp((string)$e['enviado_contato'],
        'Olá! Aqui é do Acha Fácil, sobre o evento ' . $e['titulo'] . ' que você enviou.'),
    'recado' => $e['enviado_recado'], 'enviadoEm' => datahora_br($e['criado_em']),
], eventos_pendentes());

responder(['alteracoes' => $alteracoes, 'pedidos' => $pedidos, 'cidades' => $cidades, 'eventos' => $eventos, 'site' => url_publica('admin/')]);
