<?php
// Entrada da administração (e-mail e senha da tabela admins; criar com: php servidor/criar-admin.php)
require __DIR__ . '/../../inc/admin.php';
iniciar_sessao();
if (admin_atual()) ir('admin/');
$erro = null;
if (e_post()) {
    exigir_post();
    if (login_bloqueado()) $erro = 'Muitas tentativas. Espere 15 minutos.';
    else {
        $a = linha('SELECT id, senha_hash FROM admins WHERE email = ? AND ativo = 1', [mb_strtolower((string)txt('email', 160))]);
        if ($a && password_verify((string)($_POST['senha'] ?? ''), $a['senha_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$a['id'];
            registrar((int)$a['id'], 'entrou');
            ir('admin/');
        }
        login_falhou();
        $erro = 'E-mail ou senha errados.';
    }
}
admin_inicio('Entrar', null);
?>
<h1>Administração</h1>
<?php if ($erro): ?><div class="aviso erro"><?= h($erro) ?></div><?php endif ?>
<form method="post" class="estreito">
  <?= campo_csrf() ?>
  <label for="email">E-mail</label>
  <input id="email" name="email" type="email" required autocomplete="username" value="<?= h($_POST['email'] ?? '') ?>">
  <label for="senha">Senha</label>
  <input id="senha" name="senha" type="password" required autocomplete="current-password">
  <p><button class="botao">Entrar</button></p>
</form>
<?php admin_fim();
