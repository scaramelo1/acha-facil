<?php
// Passo 65: "Quer ser avisado quando o comércio colocar o WhatsApp?" Quem pede (com a conta Google) recebe a
// notificação no celular quando a informação entra na ficha, e vê a lista em "Minha conta" no site.
declare(strict_types=1);
require_once __DIR__ . '/guia.php';
require_once __DIR__ . '/numeros.php';
require_once __DIR__ . '/avisos.php';

const AVISO_INFO_TEXTO = ['horario' => 'o horário', 'fotos' => 'fotos', 'sobre' => 'o que vende ou faz', 'whatsapp' => 'o WhatsApp'];

/** A mudança 029 já foi aplicada? Antes disso a pergunta não aparece. */
function avisos_info_prontos(): bool {
    static $ok = null;
    try { return $ok ??= (bool)valor("SELECT 1 FROM migracoes WHERE nome = '029-avisos-info.sql'"); } catch (PDOException) { return $ok = false; }
}

/** A ficha já tem a informação pedida? */
function ficha_tem_info(array $p, string $tipo): bool {
    return match ($tipo) {
        'whatsapp' => trim((string)$p['whatsapp']) !== '',
        'horario' => !empty($p['horarios']) || trim((string)$p['horario']) !== '',
        'fotos' => (bool)lista_fotos($p['fotos'] ?? null),
        'sobre' => trim((string)$p['descricao']) !== '' || trim((string)$p['servicos']) !== '',
        default => false,
    };
}

/** Guarda o pedido de aviso (repetido não duplica). */
function pedir_aviso_info(int $usuario_id, int $profissional_id, string $tipo): void {
    if (!isset(TIPOS_PEDIDO_INFO[$tipo]) || !avisos_info_prontos()) return;
    q('INSERT INTO avisos_info (usuario_id, profissional_id, tipo) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE avisado_em = IF(avisado_em IS NULL, NULL, avisado_em)', [$usuario_id, $profissional_id, $tipo]);
}

/**
 * Confere os pedidos ainda não avisados (só das fichas de $ids, ou de todas) e manda a notificação de quem já tem
 * a informação. Chamado ao aprovar alteração do dono e depois de importar lista. Devolve quantas pessoas foram avisadas.
 */
function avisar_infos_completas(?array $ids = null): int {
    if (!avisos_info_prontos()) return 0;
    $onde = $ids ? ' AND a.profissional_id IN (' . implode(',', array_map('intval', $ids)) . ')' : '';
    $avisados = 0;
    foreach (linhas("SELECT a.id, a.usuario_id, a.tipo, p.id AS pid, p.nome, p.whatsapp, p.horarios, p.horario, p.fotos, p.descricao, p.servicos
        FROM avisos_info a JOIN profissionais p ON p.id = a.profissional_id AND p.status = 'ativo'
        WHERE a.avisado_em IS NULL$onde ORDER BY a.id LIMIT 2000") as $a) {
        if (!ficha_tem_info($a, $a['tipo'])) continue;
        $nome = nome_curto($a['nome'], 40);
        avisar_usuario((int)$a['usuario_id'], "🔔 $nome colocou " . AVISO_INFO_TEXTO[$a['tipo']],
            'Você pediu para ser avisado no Acha Fácil. Toque para ver.', ['tela' => 'profissional', 'id' => (int)$a['pid']]);
        q('UPDATE avisos_info SET avisado_em = NOW() WHERE id = ?', [$a['id']]);   // mesmo sem celular: aparece em "Minha conta"
        $avisados++;
    }
    return $avisados;
}

/** Pedidos de aviso de uma pessoa (Minha conta no site e tela do app). */
function avisos_info_do_usuario(int $usuario_id): array {
    if (!avisos_info_prontos()) return [];
    return linhas("SELECT a.tipo, a.criado_em, a.avisado_em, p.id, p.nome, p.cidade FROM avisos_info a
        JOIN profissionais p ON p.id = a.profissional_id WHERE a.usuario_id = ? ORDER BY a.avisado_em IS NULL, a.avisado_em DESC, a.criado_em DESC LIMIT 100", [$usuario_id]);
}

/** Quantas pessoas esperam aviso de cada tipo numa ficha (relatório para o comerciante). */
function avisos_info_esperando(int $profissional_id): array {
    if (!avisos_info_prontos()) return [];
    return array_map('intval', array_column(linhas('SELECT tipo, COUNT(*) n FROM avisos_info WHERE profissional_id = ? AND avisado_em IS NULL GROUP BY tipo',
        [$profissional_id]), 'n', 'tipo'));
}
