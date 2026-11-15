<?php
require __DIR__ . '/../../inc/admin.php';
iniciar_sessao();
unset($_SESSION['admin_id']);
require_once __DIR__ . '/../../inc/conta-web.php';
sair_web();   // sai também da conta Google do site (senão ela abriria a administração de novo)
session_regenerate_id(true);
ir('admin/entrar.php');
