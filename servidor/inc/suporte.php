<?php
// Suporte (passo 53): chamados das pessoas com conta Google para a equipe (site /suporte.php, admin /admin/suporte.php).
// A pessoa recebe push quando a equipe responde; os admins recebem push a cada mensagem nova.
declare(strict_types=1);
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/guia.php';
require_once __DIR__ . '/avisos.php';

const SUPORTE_ASSUNTOS = [
    'info_errada' => 'Informação errada num comércio',
    'meu_comercio' => 'Meu comércio / meu plano',
    'pagamento' => 'Pagamento',
    'app' => 'Problema no app ou no site',
    'sugestao' => 'Sugestão',
    'outro' => 'Outro assunto',
];
const SUPORTE_STATUS = ['aberto' => 'Esperando a equipe', 'respondido' => 'Respondido', 'fechado' => 'Encerrado'];

function suporte_pronto(): bool {
    static $ok = null;
    try { return $ok ??= (bool)valor("SELECT 1 FROM migracoes WHERE nome = '025-suporte.sql'"); } catch (PDOException) { return $ok = false; }
}

/** Chamados esperando a equipe (contador do menu do admin). */
function suporte_abertos_contar(): int {
    return suporte_pronto() ? (int)valor("SELECT COUNT(*) FROM chamados WHERE status = 'aberto'") : 0;
}

/** Abre um chamado com a primeira mensagem e avisa os admins. Devolve o id. */
function suporte_abrir(array $u, string $assunto, string $texto, ?int $profissional_id): int {
    db()->beginTransaction();
    $id = inserir('chamados', ['usuario_id' => $u['id'], 'profissional_id' => $profissional_id ?: null, 'assunto' => $assunto]);
    inserir('chamados_mensagens', ['chamado_id' => $id, 'texto' => $texto, 'ip' => ip()]);
    db()->commit();
    $com = $profissional_id ? (string)valor('SELECT nome FROM profissionais WHERE id = ?', [$profissional_id]) : '';
    avisar_admins('Suporte: ' . (SUPORTE_ASSUNTOS[$assunto] ?? 'mensagem'), primeiro_nome($u['nome']) . ($com ? " ($com)" : '') . ': ' . mb_substr($texto, 0, 120),
        ['chamado' => $id]);
    return $id;
}

/** Mensagem num chamado: da pessoa ($admin_id null) ou da equipe. Atualiza a situação e avisa o outro lado. */
function suporte_responder(array $ch, string $texto, ?int $admin_id): void {
    inserir('chamados_mensagens', ['chamado_id' => $ch['id'], 'admin_id' => $admin_id, 'texto' => $texto, 'ip' => ip()]);
    q('UPDATE chamados SET status = ?, atualizado_em = NOW() WHERE id = ?', [$admin_id ? 'respondido' : 'aberto', $ch['id']]);
    if ($admin_id) {
        avisar_usuario((int)$ch['usuario_id'], 'A equipe do Acha Fácil respondeu', mb_substr($texto, 0, 150), ['chamado' => (int)$ch['id']]);
    } else {
        $nome = (string)valor('SELECT nome FROM usuarios WHERE id = ?', [$ch['usuario_id']]);
        avisar_admins('Suporte: nova mensagem', primeiro_nome($nome) . ': ' . mb_substr($texto, 0, 120), ['chamado' => (int)$ch['id']]);
    }
}

function suporte_mensagens(int $chamado_id): array {
    return linhas('SELECT m.*, a.nome AS admin_nome FROM chamados_mensagens m LEFT JOIN admins a ON a.id = m.admin_id
        WHERE m.chamado_id = ? ORDER BY m.id', [$chamado_id]);
}

/** Conversa (site e admin): a equipe de um lado, a pessoa do outro. */
function suporte_conversa_html(array $msgs, string $nome_pessoa): string {
    $h = '<div class="conversa">';
    foreach ($msgs as $m) {
        $equipe = $m['admin_id'] !== null;
        $h .= '<div class="msg ' . ($equipe ? 'equipe' : 'pessoa') . '"><div class="msg-quem">' . h($equipe ? 'Equipe Acha Fácil' : $nome_pessoa)
            . ' · ' . h(datahora_br($m['criado_em'])) . '</div>' . nl2br(h($m['texto'])) . '</div>';
    }
    return $h . '</div>';
}
