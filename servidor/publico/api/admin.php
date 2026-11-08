<?php
// Administração pelo app (só contas cujo e-mail está em admins).
// GET: pendências (alterações dos donos com antes x depois, pedidos novos, cidades pedidas).
// POST {acao: 'aprovar' | 'recusar', id, motivo}: decide uma alteração (as mesmas regras do /admin do site).
require __DIR__ . '/../../inc/api.php';
require_once __DIR__ . '/../../inc/moderacao.php';
require_once __DIR__ . '/../../inc/avisos.php';
$m = metodo('GET', 'POST');
$u = exigir_usuario();
$admin_id = admin_do_usuario($u) ?? erro('Só para administradores.', 403);

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

responder(['alteracoes' => $alteracoes, 'pedidos' => $pedidos, 'cidades' => $cidades, 'site' => url_publica('admin/')]);
