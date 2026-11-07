<?php
// Cria um administrador (ou troca a senha de um existente) e mostra a senha gerada UMA vez.
// Uso (como root na VPS): php servidor/criar-admin.php email@exemplo.com "Nome"
//        desativar:        php servidor/criar-admin.php email@exemplo.com --desativar
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/inc/base.php';

$email = mb_strtolower(trim($argv[1] ?? ''));
if (!email_valido($email)) { fwrite(STDERR, "Uso: php criar-admin.php email \"Nome\"\n"); exit(1); }
if (($argv[2] ?? '') === '--desativar') {
    q('UPDATE admins SET ativo = 0 WHERE email = ?', [$email]);
    echo "Admin $email desativado.\n";
    exit;
}
$senha = rtrim(strtr(base64_encode(random_bytes(12)), '+/', 'kx'), '=');
$hash = password_hash($senha, PASSWORD_DEFAULT);
if ($a = linha('SELECT id FROM admins WHERE email = ?', [$email])) {
    q('UPDATE admins SET senha_hash = ?, ativo = 1 WHERE id = ?', [$hash, $a['id']]);
    echo "Senha nova de $email\n";
} else {
    $nome = trim($argv[2] ?? '') ?: explode('@', $email)[0];
    inserir('admins', ['nome' => mb_substr($nome, 0, 80), 'email' => $email, 'senha_hash' => $hash]);
    echo "Admin criado: $nome <$email>\n";
}
echo "Senha: $senha\n";
echo 'Entrar em: ' . url_publica('admin/') . "\n";
