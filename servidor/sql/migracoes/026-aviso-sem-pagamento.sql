-- Aviso aos admins de plano escolhido e não pago (passo 54): marca quando o aviso saiu, para sair uma vez só.
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS aviso_sem_pagamento_em DATETIME NULL;
