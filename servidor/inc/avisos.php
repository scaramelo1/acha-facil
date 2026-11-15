<?php
// Avisos no celular dos administradores, pelo serviço de push da Expo (não precisa de chave no servidor; o FCM do
// Android fica configurado no projeto EAS). Só vai para aparelhos de contas do app cujo e-mail está em admins.
// Nunca derruba quem chamou: qualquer falha só vai para o registro de erros.
declare(strict_types=1);
require_once __DIR__ . '/base.php';

/** Id do admin ligado a esta conta do app (mesmo e-mail, verificado pelo Google), ou null. */
function admin_do_usuario(?array $u): ?int {
    if (!$u || empty($u['email'])) return null;
    $id = valor('SELECT id FROM admins WHERE email = ? AND ativo = 1', [mb_strtolower((string)$u['email'])]);
    return $id === null ? null : (int)$id;
}

function avisar_admins(string $titulo, string $texto, array $dados = []): void {
    try {
        $tokens = array_column(linhas('SELECT DISTINCT t.token FROM avisos_aparelhos t JOIN usuarios u ON u.id = t.usuario_id AND u.ativo = 1
            JOIN admins a ON a.email = u.email AND a.ativo = 1'), 'token');
        expo_enviar($tokens, $titulo, $texto, $dados + ['tela' => 'admin'], 'admin');
    } catch (Throwable $e) {
        error_log('acha-facil aviso: ' . $e->getMessage());
    }
}

/** Mudança 024 aplicada (pedidos.usuario_id)? */
function pedido_usuario_pronto(): bool {
    static $ok = null;
    try { return $ok ??= (bool)valor("SELECT 1 FROM migracoes WHERE nome = '024-pedido-usuario.sql'"); } catch (PDOException) { return $ok = false; }
}

/** Conta de quem está fazendo o pedido no site (entrou com o Google), para gravar em pedidos.usuario_id. */
function pedido_usuario_atual(): ?int {
    if (!pedido_usuario_pronto()) return null;
    require_once __DIR__ . '/conta-web.php';
    $u = usuario_web();
    return $u ? (int)$u['id'] : null;
}

/** Aviso no celular de uma pessoa (todos os aparelhos em que ela entrou no app). Nunca derruba quem chamou. */
function avisar_usuario(?int $usuario_id, string $titulo, string $texto, array $dados = []): int {
    if (!$usuario_id) return 0;
    try {
        $tokens = array_column(linhas('SELECT token FROM avisos_aparelhos WHERE usuario_id = ?', [$usuario_id]), 'token');
        try { $tokens = array_merge($tokens, array_column(linhas('SELECT token FROM avisos_publico WHERE usuario_id = ?', [$usuario_id]), 'token')); }
        catch (PDOException) {}
        return expo_enviar(array_values(array_unique($tokens)), $titulo, $texto, $dados, 'novidades')[0];
    } catch (Throwable $e) {
        error_log('acha-facil aviso usuario: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Manda o mesmo aviso para uma lista de endereços Expo Push, de 100 em 100. Aparelho que desinstalou o app
 * (DeviceNotRegistered) sai das duas tabelas de aparelhos. Devolve [entregues, falhas]. Não lança erro.
 */
function expo_enviar(array $tokens, string $titulo, string $texto, array $dados, string $canal): array {
    $ok = 0;
    $falhas = 0;
    if (!$tokens) return [0, 0];
    $msgs = array_map(fn($t) => ['to' => $t, 'title' => mb_substr($titulo, 0, 80), 'body' => mb_substr($texto, 0, 180),
        'data' => $dados, 'sound' => 'default', 'priority' => 'high', 'channelId' => $canal], array_values($tokens));
    foreach (array_chunk($msgs, 100) as $lote) {
        try {
            $c = curl_init('https://exp.host/--/api/v2/push/send');
            curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
                CURLOPT_POSTFIELDS => json_encode($lote, JSON_UNESCAPED_UNICODE)]);
            $r = curl_exec($c);
            $st = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
            curl_close($c);
            $j = is_string($r) ? json_decode($r, true) : null;
            if ($st !== 200 || !is_array($j)) { error_log("acha-facil aviso: Expo respondeu $st"); $falhas += count($lote); continue; }
            foreach ($lote as $i => $msg) {
                $recibo = $j['data'][$i] ?? null;
                if (($recibo['status'] ?? '') === 'ok') { $ok++; continue; }
                $falhas++;
                // aparelho que desinstalou o app ou trocou de endereço: tira da lista
                if (($recibo['details']['error'] ?? '') === 'DeviceNotRegistered') {
                    q('DELETE FROM avisos_aparelhos WHERE token = ?', [$msg['to']]);
                    try { q('DELETE FROM avisos_publico WHERE token = ?', [$msg['to']]); } catch (PDOException) {}
                } elseif ($recibo) error_log('acha-facil aviso: ' . ($recibo['message'] ?? 'erro'));
            }
        } catch (Throwable $e) {
            error_log('acha-facil aviso: ' . $e->getMessage());
            $falhas += count($lote);
        }
    }
    return [$ok, $falhas];
}
