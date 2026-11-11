<?php
// Notificações para os usuários do app (passo 29). Todo aparelho com o app 1.6.0 ou mais novo que aceitou avisos fica em
// avisos_publico com a última cidade escolhida (com ou sem conta). Os administradores mandam pelo /admin/notificacoes.php
// do site ou pela tela Administração do app; as duas usam enviar_notificacao(). Canal Android "novidades".
// Tudo com reserva até a mudança 019 ser aplicada (notificacoes_prontas()).
declare(strict_types=1);
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/guia.php';
require_once __DIR__ . '/avisos.php';
require_once __DIR__ . '/moderacao.php';   // registrar()

const NOTIF_PUBLICOS = ['todos' => 'Todos os aparelhos', 'contas' => 'Só quem entrou com o Google'];

function notificacoes_prontas(): bool {
    static $ok = null;
    try { return $ok ??= (bool)valor("SELECT 1 FROM migracoes WHERE nome = '019-notificacoes.sql'"); } catch (PDOException) { return $ok = false; }
}

/** Guarda (ou atualiza) o aparelho e a cidade que ele escolheu. Falha em silêncio. */
function guardar_aparelho(string $token, ?int $usuario_id, ?string $cidade): void {
    if (!notificacoes_prontas()) return;
    try {
        $cidade = $cidade !== null && isset(TODAS_CIDADES[$cidade]) ? $cidade : null;
        // usuario_id só troca quando vem uma conta (sair da conta não desliga as novidades do aparelho)
        q('INSERT INTO avisos_publico (token, usuario_id, cidade) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE usuario_id = COALESCE(VALUES(usuario_id), usuario_id), cidade = COALESCE(VALUES(cidade), cidade), atualizado_em = NOW()',
            [$token, $usuario_id, $cidade]);
    } catch (Throwable $e) {
        error_log('acha-facil notificações: ' . $e->getMessage());
    }
}

/** Ao sair da conta: o aparelho continua recebendo novidades, mas sem ligação com a conta. */
function soltar_aparelho(string $token, int $usuario_id): void {
    if (!notificacoes_prontas()) return;
    try { q('UPDATE avisos_publico SET usuario_id = NULL WHERE token = ? AND usuario_id = ?', [$token, $usuario_id]); } catch (Throwable) {}
}

/** Nº do comércio a partir do que o admin digitou: "123", o link da ficha no site (.../c/padaria-123) ou ...?id=123. */
function notif_id_comercio(string $v): ?int {
    $v = trim($v);
    if ($v === '') return null;
    if (preg_match('/^\d+$/', $v)) return (int)$v;
    if (preg_match('/[?&]id=(\d+)/', $v, $m)) return (int)$m[1];
    if (preg_match('~/c/[^/?#]*-(\d+)(?:[/?#]|$)~', $v, $m)) return (int)$m[1];
    return 0;   // não reconheceu: enviar_notificacao() avisa
}

/** Filtro SQL dos aparelhos de um envio. */
function notif_filtro(?string $cidade, string $publico): array {
    $onde = ['1 = 1'];
    $p = [];
    if ($cidade !== null) { $onde[] = 'a.cidade = ?'; $p[] = $cidade; }
    if ($publico === 'contas') $onde[] = 'a.usuario_id IS NOT NULL';
    return [implode(' AND ', $onde), $p];
}

/** Quantos aparelhos recebem: total, por cidade e com conta. */
function notif_totais(): array {
    if (!notificacoes_prontas()) return ['total' => 0, 'contas' => 0, 'cidades' => []];
    $por = [];
    foreach (linhas('SELECT cidade, COUNT(*) AS n, SUM(usuario_id IS NOT NULL) AS c FROM avisos_publico GROUP BY cidade') as $l)
        $por[(string)$l['cidade']] = ['total' => (int)$l['n'], 'contas' => (int)$l['c']];
    return [
        'total' => array_sum(array_column($por, 'total')),
        'contas' => array_sum(array_column($por, 'contas')),
        'cidades' => $por,
    ];
}

/**
 * Confere e manda. Devolve o registro do envio (com aparelhos, entregues e falhas) ou lança RuntimeException com
 * a mensagem para mostrar ao admin.
 */
function enviar_notificacao(string $titulo, string $texto, ?string $cidade, string $publico, ?int $profissional_id, ?int $admin_id, string $pelo): array {
    if (!notificacoes_prontas()) throw new RuntimeException('Falta aplicar a mudança 019 (bash servidor/instalar.sh).');
    $titulo = trim(preg_replace('/\s+/u', ' ', $titulo));
    $texto = trim(preg_replace('/[ \t]+/u', ' ', $texto));
    if (mb_strlen($titulo) < 3) throw new RuntimeException('Escreva um título (pelo menos 3 letras).');
    if (mb_strlen($titulo) > 60) throw new RuntimeException('O título pode ter até 60 letras.');
    if (mb_strlen($texto) < 3) throw new RuntimeException('Escreva o texto da notificação.');
    if (mb_strlen($texto) > 178) throw new RuntimeException('O texto pode ter até 178 letras.');
    if ($cidade !== null && !isset(TODAS_CIDADES[$cidade])) throw new RuntimeException('Cidade desconhecida.');
    if (!isset(NOTIF_PUBLICOS[$publico])) $publico = 'todos';
    $comercio = null;
    if ($profissional_id === 0) throw new RuntimeException('Não entendi o comércio: use o número ou cole o link da ficha no site.');
    if ($profissional_id) {
        $comercio = linha("SELECT id, nome, cidade FROM profissionais WHERE id = ? AND status = 'ativo'", [$profissional_id]);
        if (!$comercio) throw new RuntimeException("Não achei o comércio nº $profissional_id (ou ele está fora do app).");
    }
    // a mesma notificação duas vezes em 10 minutos é quase sempre clique repetido
    if (valor('SELECT 1 FROM notificacoes WHERE titulo = ? AND texto = ? AND cidade <=> ? AND criado_em > NOW() - INTERVAL 10 MINUTE', [$titulo, $texto, $cidade]))
        throw new RuntimeException('Esta mesma notificação já foi enviada há menos de 10 minutos.');

    [$onde, $p] = notif_filtro($cidade, $publico);
    $tokens = array_column(linhas("SELECT a.token FROM avisos_publico a WHERE $onde", $p), 'token');
    if (!$tokens) throw new RuntimeException('Nenhum aparelho para receber ainda (o app precisa ser a versão 1.6.0 ou mais nova e a pessoa aceitar os avisos).');

    $id = inserir('notificacoes', ['titulo' => $titulo, 'texto' => $texto, 'cidade' => $cidade, 'publico' => $publico,
        'profissional_id' => $comercio ? (int)$comercio['id'] : null, 'admin_id' => $admin_id, 'pelo' => $pelo === 'app' ? 'app' : 'site',
        'aparelhos' => count($tokens)]);
    @set_time_limit(300);
    $dados = $comercio ? ['tela' => 'profissional', 'id' => (int)$comercio['id'], 'notificacao' => $id] : ['tela' => 'inicio', 'notificacao' => $id];
    [$ok, $falhas] = expo_enviar($tokens, $titulo, $texto, $dados, 'novidades');
    q('UPDATE notificacoes SET entregues = ?, falhas = ? WHERE id = ?', [$ok, $falhas, $id]);
    registrar($admin_id, 'enviou_notificacao', $comercio ? (int)$comercio['id'] : null,
        "$titulo (" . ($cidade ? nome_cidade($cidade, true) : 'todas as cidades') . ", $ok de " . count($tokens) . ')');
    return ['id' => $id, 'aparelhos' => count($tokens), 'entregues' => $ok, 'falhas' => $falhas];
}

/** Últimos envios, para as telas do site e do app. */
function notif_historico(int $limite = 30): array {
    if (!notificacoes_prontas()) return [];
    return array_map(fn($n) => [
        'id' => (int)$n['id'], 'titulo' => $n['titulo'], 'texto' => $n['texto'],
        'cidade' => $n['cidade'] ? nome_cidade($n['cidade'], true) : 'Todas as cidades',
        'publico' => NOTIF_PUBLICOS[$n['publico']] ?? $n['publico'],
        'comercio' => $n['comercio'], 'profissionalId' => $n['profissional_id'] ? (int)$n['profissional_id'] : null,
        'admin' => $n['admin'], 'pelo' => $n['pelo'], 'quando' => datahora_br($n['criado_em']),
        'aparelhos' => (int)$n['aparelhos'], 'entregues' => (int)$n['entregues'], 'falhas' => (int)$n['falhas'],
    ], linhas('SELECT n.*, p.nome AS comercio, ad.nome AS admin FROM notificacoes n LEFT JOIN profissionais p ON p.id = n.profissional_id
        LEFT JOIN admins ad ON ad.id = n.admin_id ORDER BY n.id DESC LIMIT ' . max(1, $limite)));
}
