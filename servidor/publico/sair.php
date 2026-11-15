<?php
// Sair da conta no site (passo 44). Só por POST com a chave da sessão (ninguém tira você da conta por um link).
require __DIR__ . '/../inc/base.php';
require_once __DIR__ . '/../inc/conta-web.php';
iniciar_sessao();
exigir_post();
sair_web();
header('Location: ' . url(ltrim(volta_web($_POST['volta'] ?? '/'), '/')));
