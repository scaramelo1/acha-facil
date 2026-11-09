<?php
// Moderação: o dono do comércio nunca muda o app direto. O painel do dono só cria "alterações" pendentes; um
// administrador confere (antes x depois) e aprova ou recusa. Só aprovar_alteracao() grava em profissionais.
// O dono entra no painel assim que a assinatura do plano é confirmada (pela página da assinatura), ou por um link
// que o admin libera (casos sem pagamento, como cortesia). O que ele muda só vai ao app depois da aprovação.
declare(strict_types=1);
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/guia.php';

/** Campos que o dono pode pedir para mudar: campo => [rótulo, tamanho máximo]. Nada fora desta lista é aplicado. */
const CAMPOS_DONO = [
    'nome' => ['Nome do comércio', 120],
    'descricao' => ['Sobre o comércio', 2000],
    'servicos' => ['Produtos e serviços', 500],
    'telefone' => ['Telefone', 20],
    'whatsapp' => ['WhatsApp', 20],
    'instagram' => ['Instagram', 60],
    'site' => ['Site', 200],
    'endereco' => ['Endereço', 200],
    'bairro' => ['Bairro', 80],
    'horario' => ['Horário (texto)', 400],   // pedidos antigos; hoje é gerado a partir de "horarios"
    'horarios' => ['Horário de funcionamento', 1000],
    'atende_em_casa' => ['Atende em casa', 1],
    'foto' => ['Logo', 120],
    'fotos' => ['Fotos do comércio', 2000],   // lista JSON dos arquivos da galeria (carrossel da capa)
];
const MAX_FOTOS = 10;   // fotos na galeria de cada comércio

// ---------- registro ----------
function registrar(?int $admin_id, string $acao, ?int $profissional_id = null, ?string $detalhe = null): void {
    inserir('registro_admin', ['admin_id' => $admin_id, 'acao' => $acao, 'profissional_id' => $profissional_id,
        'detalhe' => $detalhe === null ? null : mb_substr($detalhe, 0, 500), 'ip' => ip()]);
}

function mostrar_flash(): void {
    foreach ($_SESSION['flash'] ?? [] as [$tipo, $msg]) echo '<div class="aviso ' . h($tipo) . '">' . h($msg) . '</div>';
    unset($_SESSION['flash']);
}

// ---------- administradores ----------
function admin_atual(): ?array {
    iniciar_sessao();
    $id = (int)($_SESSION['admin_id'] ?? 0);
    return $id ? linha('SELECT id, nome, email FROM admins WHERE id = ? AND ativo = 1', [$id]) : null;
}
function exigir_admin(): array {
    $a = admin_atual();
    if (!$a) ir('admin/entrar.php');
    header('Cache-Control: no-store');
    return $a;
}

// ---------- acesso do dono ----------
/** Cria o link secreto do painel para o dono (só o hash fica no banco). Devolve o endereço completo. */
function liberar_acesso(int $profissional_id, ?int $pedido_id, string $nome, ?string $telefone, int $admin_id): string {
    $token = bin2hex(random_bytes(24));
    inserir('acessos_dono', ['profissional_id' => $profissional_id, 'pedido_id' => $pedido_id, 'nome' => mb_substr($nome, 0, 120),
        'telefone' => $telefone, 'token_hash' => hash('sha256', $token), 'admin_id' => $admin_id]);
    registrar($admin_id, 'liberou_acesso', $profissional_id, $nome);
    return url_publica('painel/?t=' . $token);
}

/**
 * Entrada pelo pagamento: pedido de assumir pago no Mercado Pago (assinatura do Destaque autorizada ou Básico aprovado). Cria o acesso na primeira
 * vez (sem admin) e marca o pedido como resolvido. Devolve o id do acesso, ou null (não pago ou acesso cortado).
 */
function acesso_pelo_pagamento(string $codigo): ?int {
    if (!preg_match('/^[a-f0-9]{64}$/', $codigo)) return null;
    $pe = linha("SELECT * FROM pedidos WHERE codigo = ? AND tipo = 'assumir' AND profissional_id IS NOT NULL AND mp_status IN ('authorized','approved')", [$codigo]);
    if (!$pe) return null;
    $a = linha('SELECT id, ativo FROM acessos_dono WHERE pedido_id = ? ORDER BY id DESC LIMIT 1', [$pe['id']]);
    if ($a) return (int)$a['ativo'] ? (int)$a['id'] : null;   // cortado pelo admin: não volta sozinho
    $id = inserir('acessos_dono', ['profissional_id' => $pe['profissional_id'], 'pedido_id' => $pe['id'], 'nome' => $pe['nome'],
        'telefone' => $pe['telefone'], 'token_hash' => hash('sha256', random_bytes(32))]);
    q("UPDATE pedidos SET status = 'feito' WHERE id = ? AND status = 'novo'", [$pe['id']]);
    registrar(null, 'acesso_pago', (int)$pe['profissional_id'], $pe['nome'] . ' (plano ' . $pe['plano'] . ')');
    return $id;
}

/** Dono conectado no painel (acesso ativo + comércio), ou null. */
function dono_atual(): ?array {
    iniciar_sessao();
    $id = (int)($_SESSION['dono_acesso'] ?? 0);
    if (!$id) return null;
    $a = linha('SELECT a.id AS acesso_id, a.nome AS dono_nome, p.* FROM acessos_dono a JOIN profissionais p ON p.id = a.profissional_id
                WHERE a.id = ? AND a.ativo = 1', [$id]);
    if (!$a) unset($_SESSION['dono_acesso']);
    return $a;
}

// ---------- alterações ----------
/** Normaliza o que o dono digitou; devolve [valores, erro]. */
function limpar_campos_dono(array $post): array {
    $v = [];
    foreach (CAMPOS_DONO as $c => [, $max]) {
        if (in_array($c, ['foto', 'fotos', 'horario', 'horarios'], true)) continue;   // vêm à parte
        if ($c === 'atende_em_casa') { $v[$c] = empty($post[$c]) ? '0' : '1'; continue; }
        $t = trim(preg_replace('/[ \t]+/', ' ', str_replace("\r", '', (string)($post[$c] ?? ''))));
        $v[$c] = $t === '' ? null : mb_substr($t, 0, $max);
    }
    if (!$v['nome']) return [$v, 'O nome do comércio não pode ficar vazio.'];
    foreach (['telefone', 'whatsapp'] as $c) {
        if ($v[$c] !== null && !($v[$c] = telefone_normalizado($v[$c]))) return [$v, CAMPOS_DONO[$c][0] . ': digite com DDD.'];
    }
    if ($v['instagram'] !== null) {
        $v['instagram'] = ltrim(preg_replace('#^https?://(www\.)?instagram\.com/#i', '', rtrim($v['instagram'], '/')), '@');
        if (!preg_match('/^[A-Za-z0-9._]{1,30}$/', $v['instagram'])) return [$v, 'Instagram: só o nome de usuário, como @seucomercio.'];
    }
    if ($v['site'] !== null) {
        if (!preg_match('#^https?://#i', $v['site'])) $v['site'] = 'https://' . $v['site'];
        if (!filter_var($v['site'], FILTER_VALIDATE_URL)) return [$v, 'Site: endereço inválido.'];
    }
    return [$v, null];
}

/**
 * Foto enviada pelo dono: confere se é imagem, tira os dados escondidos (GPS etc.) e reduz. Devolve o nome do arquivo.
 * $lado: maior lado em pixels (logo menor, fotos da galeria maiores). Fundo transparente (logo em PNG) vira branco.
 * $quadrada (logo): completa com branco até ficar quadrada, com a imagem no meio (o painel já manda recortada, isto é
 * para quem envia sem o recorte do navegador).
 */
function salvar_foto_enviada(array $f, int $profissional_id, int $lado = 1200, bool $quadrada = false): string {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Não deu para receber a foto. Tente de novo.');
    if ($f['size'] > 8 * 1024 * 1024) throw new RuntimeException('A foto passa de 8 MB.');
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) throw new RuntimeException('Envie uma foto JPG, PNG ou WEBP.');
    $nome = 'p' . $profissional_id . '-' . bin2hex(random_bytes(8)) . '.jpg';
    $destino = config()['dados_dir'] . '/fotos/' . $nome;
    $cmd = 'convert ' . escapeshellarg($f['tmp_name'] . '[0]') . ' -auto-orient -strip -background white -alpha remove -alpha off -resize ' . escapeshellarg($lado . 'x' . $lado . '>')
        . ' -quality 82 ' . escapeshellarg('jpg:' . $destino) . ' 2>&1';
    exec($cmd, $saida, $codigo);
    if ($codigo !== 0 || !is_file($destino)) throw new RuntimeException('Não deu para abrir essa foto. Tente outra.');
    if ($quadrada) quadrar_imagem($destino);
    return $nome;
}
/** Completa a imagem com branco até ficar quadrada, centralizada e com margem (logo que chegou deitada ou em pé). */
function quadrar_imagem(string $arq): void {
    [$w, $h] = @getimagesize($arq) ?: [0, 0];
    if (!$w || $w === $h) return;
    $l = (int)ceil(max($w, $h) * 1.15);   // margem: as pontas do logo não encostam no círculo
    exec('convert ' . escapeshellarg($arq) . ' -background white -gravity center -extent ' . escapeshellarg($l . 'x' . $l)
        . ' -quality 88 ' . escapeshellarg('jpg:' . $arq) . ' 2>&1');
}

function apagar_foto(?string $nome): void {
    if ($nome && preg_match('/^[a-z0-9-]{8,80}\.(jpg|png|webp)$/', $nome)) @unlink(config()['dados_dir'] . '/fotos/' . $nome);
}

/** Galeria como fica gravada (lista JSON) ou null se vazia. */
function fotos_json(array $lista): ?string {
    return $lista ? json_encode(array_values($lista), JSON_UNESCAPED_SLASHES) : null;
}

/** Arquivos de imagem que um conjunto de campos usa (logo + galeria). */
function arquivos_dos_campos(array $campos): array {
    $l = lista_fotos($campos['fotos'] ?? null);
    if (!empty($campos['foto'])) $l[] = $campos['foto'];
    return $l;
}

/** Apaga os arquivos de $velhos que não estão em $ficam (nada que ainda está no app ou pendente some). */
function apagar_fotos_sobrando(array $velhos, array $ficam): void {
    foreach (array_diff($velhos, $ficam) as $f) apagar_foto($f);
}

function alteracao_pendente(int $profissional_id): ?array {
    $a = linha("SELECT * FROM alteracoes WHERE profissional_id = ? AND status = 'pendente' ORDER BY id DESC LIMIT 1", [$profissional_id]);
    if ($a) { $a['novos'] = json_decode($a['novos'], true) ?: []; $a['anteriores'] = json_decode($a['anteriores'], true) ?: []; }
    return $a;
}

/**
 * O dono pede mudanças. Compara com o que está no ar e guarda só o que mudou, como pendente. Um pedido novo
 * substitui o pendente anterior do mesmo comércio (a foto pendente antiga é mantida se não veio outra).
 * Devolve o id da alteração, ou null se nada mudou.
 */
function propor_alteracao(array $prof, int $acesso_id, array $valores, ?string $foto_nova, ?array $fotos = null): ?int {
    $anterior = alteracao_pendente((int)$prof['id']);
    if ($foto_nova === null && isset($anterior['novos']['foto'])) $foto_nova = $anterior['novos']['foto'];
    if ($foto_nova !== null) $valores['foto'] = $foto_nova;
    // galeria: o painel manda a lista inteira (as que ficaram + as novas); null = não mexeu
    if ($fotos !== null) $valores['fotos'] = fotos_json($fotos);
    $novos = $antes = [];
    foreach ($valores as $c => $v) {
        if (!isset(CAMPOS_DONO[$c])) continue;
        $atual = $prof[$c] === null ? null : (string)$prof[$c];
        if ($v !== $atual) { $novos[$c] = $v; $antes[$c] = $atual; }
    }
    db()->beginTransaction();
    if ($anterior) {
        q("DELETE FROM alteracoes WHERE id = ? AND status = 'pendente'", [$anterior['id']]);
        // arquivos do pedido antigo que não estão no novo nem no app
        apagar_fotos_sobrando(arquivos_dos_campos($anterior['novos']), array_merge(arquivos_dos_campos($novos), arquivos_dos_campos($prof)));
    }
    $id = $novos ? inserir('alteracoes', ['profissional_id' => $prof['id'], 'acesso_id' => $acesso_id,
        'novos' => json_encode($novos, JSON_UNESCAPED_UNICODE), 'anteriores' => json_encode($antes, JSON_UNESCAPED_UNICODE), 'ip' => ip()]) : null;
    db()->commit();
    return $id;
}

/** Aplica a alteração no comércio (só os campos de CAMPOS_DONO). Só um admin chama. */
function aprovar_alteracao(int $id, int $admin_id): void {
    db()->beginTransaction();
    $a = linha("SELECT * FROM alteracoes WHERE id = ? AND status = 'pendente' FOR UPDATE", [$id]);
    if (!$a) { db()->rollBack(); throw new RuntimeException('Esta alteração já foi decidida.'); }
    $novos = array_intersect_key(json_decode($a['novos'], true) ?: [], CAMPOS_DONO);
    if (array_key_exists('atende_em_casa', $novos)) $novos['atende_em_casa'] = $novos['atende_em_casa'] ? 1 : 0;
    if (array_key_exists('nome', $novos) && !$novos['nome']) unset($novos['nome']);   // nome nunca fica vazio
    $prof = linha('SELECT id, foto, fotos FROM profissionais WHERE id = ?', [$a['profissional_id']]);
    $dados = $novos;
    // horário por campo: o texto (app antigo, páginas do site) acompanha
    if (!empty($novos['horarios'])) $dados['horario'] = horarios_texto(horarios_ler($novos['horarios']));
    if (array_key_exists('endereco', $novos) || array_key_exists('bairro', $novos)) {
        // endereço novo: a localização antiga não vale mais; geocodificar.php acha de novo
        $dados += ['lat' => null, 'lng' => null, 'geo_origem' => null, 'geo_tentado' => null, 'mapa' => null];
    }
    try {
        if ($dados) atualizar('profissionais', (int)$a['profissional_id'], $dados);
        q("UPDATE alteracoes SET status = 'aprovada', admin_id = ?, decidido_em = NOW() WHERE id = ?", [$admin_id, $id]);
        registrar($admin_id, 'aprovou_alteracao', (int)$a['profissional_id'], implode(', ', array_keys($novos)));
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        error_log('acha-facil aprovar alteração ' . $id . ': ' . $e->getMessage());
        throw new RuntimeException('Não foi possível aplicar esta alteração. Confira os dados ou recuse.');
    }
    // logo ou fotos trocados: apaga os arquivos antigos que saíram do app
    $agora = linha('SELECT foto, fotos FROM profissionais WHERE id = ?', [$a['profissional_id']]);
    apagar_fotos_sobrando(arquivos_dos_campos($prof), arquivos_dos_campos($agora));
}

function recusar_alteracao(int $id, int $admin_id, string $motivo): void {
    $a = linha("SELECT * FROM alteracoes WHERE id = ? AND status = 'pendente'", [$id]);
    if (!$a) throw new RuntimeException('Esta alteração já foi decidida.');
    q("UPDATE alteracoes SET status = 'recusada', motivo = ?, admin_id = ?, decidido_em = NOW() WHERE id = ? AND status = 'pendente'", [$motivo, $admin_id, $id]);
    $prof = linha('SELECT foto, fotos FROM profissionais WHERE id = ?', [$a['profissional_id']]);
    apagar_fotos_sobrando(arquivos_dos_campos(json_decode($a['novos'], true) ?: []), arquivos_dos_campos($prof ?: []));
    registrar($admin_id, 'recusou_alteracao', (int)$a['profissional_id'], $motivo);
}

/** Valor para mostrar ao admin/dono (foto vira miniatura). */
function valor_campo_html(string $campo, ?string $v): string {
    if ($v === null || $v === '') return '<span class="discreto">(vazio)</span>';
    if ($campo === 'foto') return '<img class="mini-foto" src="' . h(foto_url($v)) . '" alt="Logo">';
    if ($campo === 'fotos') {
        $l = lista_fotos($v);
        return $l ? '<span class="galeria">' . implode('', array_map(fn($f, $i) => '<img class="mini-foto" src="' . h(foto_url($f)) . '" alt="Foto ' . ($i + 1) . '">', $l, array_keys($l))) . '</span>'
            : '<span class="discreto">(sem fotos)</span>';
    }
    if ($campo === 'horarios') return implode('<br>', array_map('h', horarios_linhas(horarios_ler($v)))) ?: '<span class="discreto">(vazio)</span>';
    if ($campo === 'atende_em_casa') return $v === '1' ? 'Sim' : 'Não';
    if ($campo === 'telefone' || $campo === 'whatsapp') return h(telefone_formatado($v));
    return nl2br(h($v));
}
