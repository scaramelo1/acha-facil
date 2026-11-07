<?php
// Tela inicial do app: vitrine de destaques + categorias
require __DIR__ . '/../../inc/api.php';
metodo('GET');
responder(['destaques' => destaques(), 'categorias' => categorias_ativas()]);
