<?php
require __DIR__ . '/../../inc/api.php';
metodo('POST');
q('DELETE FROM sessoes_app WHERE token_hash = ?', [token_hash((string)($_SERVER['HTTP_X_TOKEN'] ?? ''))]);
responder(['ok' => true]);
