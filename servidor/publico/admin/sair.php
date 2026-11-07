<?php
require __DIR__ . '/../../inc/admin.php';
iniciar_sessao();
unset($_SESSION['admin_id']);
session_regenerate_id(true);
ir('admin/entrar.php');
