-- Plano Básico vira pagamento único (R$ 5,99, Checkout do Mercado Pago); o Destaque continua assinatura mensal.
-- basico_pago_em: quando o Básico foi pago; o comércio fica no app com o Básico para sempre (sem mensalidade).
-- mp_pagamento: id do pagamento único no Mercado Pago (o pedido do Destaque continua usando mp_assinatura).
ALTER TABLE profissionais ADD COLUMN IF NOT EXISTS basico_pago_em DATETIME NULL AFTER assinatura_ate;
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS mp_pagamento VARCHAR(64) NULL UNIQUE AFTER mp_assinatura;
