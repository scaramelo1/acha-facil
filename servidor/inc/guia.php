<?php
// Regras do guia: quem aparece, ordem da vitrine, formato dos dados enviados ao app, notas
declare(strict_types=1);

// Aparece no app: aprovado e com assinatura em dia (ou cortesia dada pelo admin).
// Comércios importados da lista de leads aparecem sem assinatura até o dono assumir o perfil.
const VISIVEL = "p.status = 'ativo' AND (p.assinatura IN ('ativa','cortesia') OR p.importado = 1) AND c.ativa = 1";

// Ordem do dia: muda uma vez por dia, para ninguém ficar sempre no topo nem sempre no fim
function semente_dia(): int { return (int)date('Ymd'); }

const CAMPOS_RESUMO = 'p.id, p.nome, p.tipo, p.importado, p.servicos, p.bairro, p.atende_em_casa, p.foto, p.verificado, p.plano,
    p.nota_media, p.total_avaliacoes, p.whatsapp, p.telefone, c.id AS categoria_id, c.nome AS categoria, c.icone, c.cor';

function foto_url(?string $foto): ?string { return $foto ? url_publica('foto.php?f=' . rawurlencode($foto)) : null; }

function prof_resumo(array $p): array {
    return [
        'id' => (int)$p['id'],
        'nome' => $p['nome'],
        'categoria' => $p['categoria'],
        'categoriaId' => (int)$p['categoria_id'],
        'icone' => $p['icone'],
        'cor' => $p['cor'],
        'tipo' => $p['tipo'] ?: $p['categoria'],
        'servicos' => $p['servicos'] ? array_values(array_filter(array_map('trim', explode(',', $p['servicos'])))) : [],
        'bairro' => $p['bairro'],
        'atendeEmCasa' => (bool)$p['atende_em_casa'],
        'foto' => foto_url($p['foto']),
        'verificado' => (bool)$p['verificado'],
        'destaque' => $p['plano'] === 'destaque',
        'nota' => round((float)$p['nota_media'], 1),
        'avaliacoes' => (int)$p['total_avaliacoes'],
        'temWhatsapp' => (bool)$p['whatsapp'],
        'temTelefone' => (bool)$p['telefone'],
        'cadastrado' => !$p['importado'],   // false = veio da lista pública, o dono ainda não assumiu
    ];
}

function categorias_ativas(): array {
    return array_map(fn($c) => [
        'id' => (int)$c['id'], 'nome' => $c['nome'], 'slug' => $c['slug'], 'icone' => $c['icone'], 'cor' => $c['cor'],
        'naInicial' => (bool)$c['na_inicial'], 'total' => (int)$c['total'],
    ], linhas('SELECT c.*, (SELECT COUNT(*) FROM profissionais p WHERE p.categoria_id = c.id AND ' . VISIVEL . ') AS total
        FROM categorias c WHERE c.ativa = 1 ORDER BY c.ordem, c.nome'));
}

function destaques(int $limite = 10): array {
    return array_map('prof_resumo', linhas('SELECT ' . CAMPOS_RESUMO . ' FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE ' . VISIVEL . " AND p.plano = 'destaque' ORDER BY RAND(?) LIMIT $limite", [semente_dia()]));
}

// "Conhecidos na cidade": sorteio do dia entre os 60 mais procurados (sem destaque pago, que já tem vitrine)
function populares(int $limite = 12): array {
    return array_map('prof_resumo', linhas('SELECT * FROM (SELECT ' . CAMPOS_RESUMO . ' FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE ' . VISIVEL . " AND p.plano <> 'destaque' ORDER BY p.popularidade + p.total_avaliacoes * 5 DESC LIMIT 60) t
        ORDER BY RAND(?) LIMIT $limite", [semente_dia()]));
}

// tipos de negócio dentro de uma área (filtros da tela da área), do mais comum ao menos
function tipos_da_categoria(int $categoria_id): array {
    return array_map(fn($t) => ['nome' => $t['tipo'], 'total' => (int)$t['total']], linhas('SELECT p.tipo, COUNT(*) AS total
        FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE ' . VISIVEL . ' AND p.categoria_id = ? AND p.tipo IS NOT NULL
        GROUP BY p.tipo ORDER BY total DESC, p.tipo LIMIT 30', [$categoria_id]));
}

// destaques primeiro, depois quem se cadastrou, depois quem tem nota; rodízio diário dentro de cada grupo
function da_categoria(int $categoria_id): array {
    return array_map('prof_resumo', linhas('SELECT ' . CAMPOS_RESUMO . ' FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE ' . VISIVEL . " AND p.categoria_id = ?
        ORDER BY p.plano = 'destaque' DESC, p.importado ASC, p.total_avaliacoes > 0 DESC, RAND(?)",
        [$categoria_id, semente_dia()]));
}

function buscar(string $termo, int $limite = 50): array {
    $termo = trim(mb_substr($termo, 0, 60));
    if (mb_strlen($termo) < 2) return [];
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $termo) . '%';
    return array_map('prof_resumo', linhas('SELECT ' . CAMPOS_RESUMO . ' FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE ' . VISIVEL . ' AND (p.nome LIKE ? OR p.tipo LIKE ? OR p.servicos LIKE ? OR p.descricao LIKE ? OR p.bairro LIKE ? OR c.nome LIKE ?)
        ORDER BY (c.nome LIKE ? OR p.tipo LIKE ? OR p.nome LIKE ?) DESC, p.plano = \'destaque\' DESC, p.importado ASC, RAND(?) LIMIT ' . $limite,
        [$like, $like, $like, $like, $like, $like, $like, $like, $like, semente_dia()]));
}

function profissional_visivel(int $id): ?array {
    return $id ? linha('SELECT p.*, c.nome AS categoria, c.icone, c.cor FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE p.id = ? AND ' . VISIVEL, [$id]) : null;
}

function prof_completo(array $p, ?int $usuario_id): array {
    $r = prof_resumo($p);
    $p += ['site' => null, 'mapa' => null];
    $r += [
        'responsavel' => $p['responsavel'],
        'descricao' => $p['descricao'],
        'telefone' => $p['telefone'],
        'telefoneFormatado' => $p['telefone'] ? telefone_formatado($p['telefone']) : null,
        'whatsapp' => $p['whatsapp'] ? link_whatsapp($p['whatsapp'], 'Olá! Encontrei você no Acha Fácil.') : null,
        'instagram' => $p['instagram'] ? 'https://instagram.com/' . ltrim($p['instagram'], '@') : null,
        'endereco' => $p['endereco'],
        'mapa' => $p['mapa'] ?: ($p['endereco'] ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($p['endereco'] . ', São João da Boa Vista - SP') : null),
        'site' => $p['site'],
        'linkAssumir' => $p['importado'] ? url_publica('perfil.php?id=' . $p['id'] . '&acao=assumir') : null,
        'linkRemover' => $p['importado'] ? url_publica('perfil.php?id=' . $p['id'] . '&acao=remover') : null,
        'horario' => $p['horario'],
        'favorito' => $usuario_id ? (bool)valor('SELECT 1 FROM favoritos WHERE usuario_id = ? AND profissional_id = ?', [$usuario_id, $p['id']]) : false,
        'minhaAvaliacao' => null,
        'comentarios' => array_map(fn($a) => [
            'id' => (int)$a['id'], 'autor' => primeiro_nome($a['autor']), 'estrelas' => (int)$a['estrelas'],
            'comentario' => $a['comentario'], 'resposta' => $a['resposta'], 'data' => data_br($a['criado_em']),
        ], linhas("SELECT a.*, u.nome AS autor FROM avaliacoes a JOIN usuarios u ON u.id = a.usuario_id
            WHERE a.profissional_id = ? AND a.status = 'publicada' ORDER BY a.comentario IS NULL, a.atualizado_em DESC LIMIT 30", [$p['id']])),
    ];
    if ($usuario_id) {
        $m = linha('SELECT estrelas, comentario FROM avaliacoes WHERE profissional_id = ? AND usuario_id = ?', [$p['id'], $usuario_id]);
        if ($m) $r['minhaAvaliacao'] = ['estrelas' => (int)$m['estrelas'], 'comentario' => $m['comentario']];
    }
    return $r;
}

// "Maria Aparecida Silva" → "Maria A." (privacidade de quem comenta)
function primeiro_nome(string $nome): string {
    $p = preg_split('/\s+/', trim($nome));
    return $p[0] . (isset($p[1]) ? ' ' . mb_strtoupper(mb_substr(end($p), 0, 1)) . '.' : '');
}

function recalcular_nota(int $profissional_id): void {
    q("UPDATE profissionais SET
        nota_media = COALESCE((SELECT AVG(estrelas) FROM avaliacoes WHERE profissional_id = ? AND status = 'publicada'), 0),
        total_avaliacoes = (SELECT COUNT(*) FROM avaliacoes WHERE profissional_id = ? AND status = 'publicada')
        WHERE id = ?", [$profissional_id, $profissional_id, $profissional_id]);
}

function contar_contato(int $profissional_id, string $tipo): void {
    if (!in_array($tipo, ['perfil', 'whatsapp', 'ligar', 'instagram', 'mapa', 'site'], true)) return;
    q('INSERT INTO contatos (profissional_id, tipo, dia, total) VALUES (?,?,CURDATE(),1) ON DUPLICATE KEY UPDATE total = total + 1',
        [$profissional_id, $tipo]);
}
