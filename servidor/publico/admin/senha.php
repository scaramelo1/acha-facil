<?php
// Trocar a senha do administrador conectado (pede a senha atual; a nova precisa de pelo menos 10 caracteres)
require __DIR__ . '/../../inc/admin.php';
$admin = exigir_admin();
$erro = null;
if (e_post()) {
    exigir_post();
    $atual = (string)($_POST['atual'] ?? '');
    $nova = (string)($_POST['nova'] ?? '');
    $hash = (string)valor('SELECT senha_hash FROM admins WHERE id = ?', [$admin['id']]);
    if (login_bloqueado()) $erro = 'Muitas tentativas. Espere 15 minutos.';
    elseif (!password_verify($atual, $hash)) { login_falhou(); $erro = 'A senha atual está errada.'; }
    elseif (mb_strlen($nova) < 10) $erro = 'A senha nova precisa de pelo menos 10 caracteres.';
    elseif (mb_strlen($nova) > 200) $erro = 'A senha nova é comprida demais.';
    elseif ($nova !== (string)($_POST['repetir'] ?? '')) $erro = 'As duas senhas novas não são iguais.';
    elseif ($nova === $atual) $erro = 'A senha nova é igual à atual.';
    else {
        q('UPDATE admins SET senha_hash = ? WHERE id = ?', [password_hash($nova, PASSWORD_DEFAULT), $admin['id']]);
        session_regenerate_id(true);
        registrar((int)$admin['id'], 'trocou_senha');
        flash('Senha trocada. Use a nova na próxima vez que entrar.');
        ir('admin/');
    }
}
admin_inicio('Trocar senha', $admin);
?>
<h1>Trocar senha</h1>
<p class="discreto"><?= h($admin['email']) ?></p>
<?php if ($erro): ?><div class="aviso erro"><?= h($erro) ?></div><?php endif ?>
<form method="post" class="estreito">
  <?= campo_csrf() ?>
  <input type="email" name="usuario" value="<?= h($admin['email']) ?>" autocomplete="username" hidden>
  <label for="atual">Senha atual</label>
  <input id="atual" name="atual" type="password" required autocomplete="current-password">
  <label for="nova">Senha nova (pelo menos 10 caracteres)</label>
  <input id="nova" name="nova" type="password" required minlength="10" maxlength="200" autocomplete="new-password">
  <label for="repetir">Repita a senha nova</label>
  <input id="repetir" name="repetir" type="password" required minlength="10" maxlength="200" autocomplete="new-password">
  <p><button class="botao">Trocar senha</button></p>
</form>
<?php admin_fim();
