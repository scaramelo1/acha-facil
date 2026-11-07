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
        if (!$tokens) return;
        $msgs = array_map(fn($t) => ['to' => $t, 'title' => mb_substr($titulo, 0, 80), 'body' => mb_substr($texto, 0, 180),
            'data' => $dados + ['tela' => 'admin'], 'sound' => 'default', 'priority' => 'high', 'channelId' => 'admin'], $tokens);
        foreach (array_chunk($msgs, 100) as $lote) {
            $c = curl_init('https://exp.host/--/api/v2/push/send');
            curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
                CURLOPT_POSTFIELDS => json_encode($lote, JSON_UNESCAPED_UNICODE)]);
            $r = curl_exec($c);
            $st = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
            curl_close($c);
            $j = is_string($r) ? json_decode($r, true) : null;
            if ($st !== 200 || !is_array($j)) { error_log("acha-facil aviso: Expo respondeu $st"); continue; }
            foreach ($j['data'] ?? [] as $i => $recibo) {
                if (($recibo['status'] ?? '') !== 'error') continue;
                // aparelho que desinstalou o app ou trocou de endereço: tira da lista
                if (($recibo['details']['error'] ?? '') === 'DeviceNotRegistered') q('DELETE FROM avisos_aparelhos WHERE token = ?', [$lote[$i]['to']]);
                else error_log('acha-facil aviso: ' . ($recibo['message'] ?? 'erro'));
            }
        }
    } catch (Throwable $e) {
        error_log('acha-facil aviso: ' . $e->getMessage());
    }
}
