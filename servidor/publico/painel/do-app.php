<?php
// Entrada no painel pelo app (17/11, passo 93): "Editar meu comércio" em Meu perfil pede um código de uso único
// (api/dono.php) e abre aqui. Entra no site com a conta do app e vai para o painel do comércio dela.
require __DIR__ . '/../../inc/moderacao.php';
require __DIR__ . '/../../inc/pagina.php';
require_once __DIR__ . '/../../inc/conta-web.php';
iniciar_sessao();
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
$uid = login_bloqueado() ? null : usar_link_painel_app((string)($_GET['c'] ?? ''));
$u = $uid ? linha('SELECT * FROM usuarios WHERE id = ? AND ativo = 1', [$uid]) : null;
$acesso = $u ? acesso_da_conta((int)$u['id']) : null;
if (!$acesso) {
    login_falhou();
    flash('Este link já foi usado ou passou do prazo. Toque de novo em "Editar meu comércio" no app.', 'erro');
    ir('painel/');
}
if (!usuario_web() || (int)usuario_web()['id'] !== (int)$u['id']) entrar_web((int)$u['id']);
session_regenerate_id(true);
unset($_SESSION['dono_saiu']);
$_SESSION['dono_acesso'] = $acesso;
q('UPDATE acessos_dono SET ultimo_uso = NOW() WHERE id = ?', [$acesso]);
ir('painel/');
