<?php
require __DIR__ . '/../../inc/api.php';
metodo('GET');
responder(['profissionais' => buscar((string)($_GET['q'] ?? ''), cidade_pedida())]);
