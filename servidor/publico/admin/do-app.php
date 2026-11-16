<?php
// Entrada pelo lápis da ficha no app (16/11): código de uso único criado por api/admin.php (acao link_editar).
// Entra no site com a conta do admin (mesmo cookie do "Entrar com o Google") e abre a edição do comércio.
require __DIR__ . '/../../inc/admin.php';
require_once __DIR__ . '/../../inc/conta-web.php';
require_once __DIR__ . '/../../inc/avisos.php';
iniciar_sessao();
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
$d = login_bloqueado() ? null : usar_link_admin_app((string)($_GET['c'] ?? ''));
$u = $d ? linha('SELECT * FROM usuarios WHERE id = ? AND ativo = 1', [$d['u']]) : null;
$admin_id = $u ? admin_do_usuario($u) : null;
if (!$admin_id) {
    login_falhou();
    flash('Este link já foi usado ou passou do prazo. Toque no lápis de novo no app.', 'erro');
    ir('admin/entrar.php');
}
if (!usuario_web() || (int)usuario_web()['id'] !== (int)$u['id']) entrar_web((int)$u['id']);
session_regenerate_id(true);
$_SESSION['admin_id'] = $admin_id;
registrar($admin_id, 'entrou', $d['p'], 'pelo lápis da ficha no app');
ir('admin/comercio.php?id=' . $d['p']);
