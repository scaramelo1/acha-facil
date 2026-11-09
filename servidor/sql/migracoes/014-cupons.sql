-- Cupons de desconto (09/11, pedido do usuário): desconto em % no Básico e/ou no Destaque. No Destaque o desconto vale
-- em TODAS as mensalidades (a assinatura no Mercado Pago já é criada com o valor com desconto). 100% = cortesia:
-- não passa pelo Mercado Pago (Básico ligado para sempre; Destaque vira assinatura 'cortesia').
CREATE TABLE IF NOT EXISTS cupons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(30) NOT NULL UNIQUE,          -- sempre em maiúsculas
  percentual TINYINT UNSIGNED NOT NULL,        -- 1 a 100
  planos SET('basico','destaque') NOT NULL,
  valido_ate DATE NULL,                        -- vazio = sem prazo
  max_usos INT NULL,                           -- pedidos pagos com o cupom; vazio = sem limite
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  observacao VARCHAR(200) NULL,
  admin_id INT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cupom_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- O pedido guarda o cupom e o desconto do momento (mudar o cupom depois não muda quem já usou)
ALTER TABLE pedidos
  ADD COLUMN cupom_id INT NULL AFTER valor,
  ADD COLUMN desconto TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER cupom_id,
  ADD CONSTRAINT fk_pedido_cupom FOREIGN KEY (cupom_id) REFERENCES cupons(id) ON DELETE SET NULL;
