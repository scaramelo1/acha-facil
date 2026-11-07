-- Assinatura mensal dos planos pelo Mercado Pago (assinatura recorrente no cartão: "preapproval")
-- O pedido do dono ganha e-mail (o Mercado Pago exige), um código secreto para a página da assinatura e o id da
-- assinatura no Mercado Pago; cada aviso do Mercado Pago fica registrado em eventos_mp (e cada mensalidade paga
-- estende a assinatura uma vez só).
ALTER TABLE pedidos
  ADD COLUMN email VARCHAR(160) NULL AFTER telefone,
  ADD COLUMN codigo CHAR(64) NULL UNIQUE AFTER email,
  ADD COLUMN mp_assinatura VARCHAR(64) NULL UNIQUE AFTER codigo,
  ADD COLUMN mp_status VARCHAR(20) NULL AFTER mp_assinatura,
  ADD COLUMN valor DECIMAL(8,2) NULL AFTER mp_status,
  ADD COLUMN atualizado_em DATETIME NULL AFTER criado_em;

CREATE TABLE IF NOT EXISTS eventos_mp (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NULL,
  tipo VARCHAR(40) NOT NULL,          -- assinatura | mensalidade
  mp_id VARCHAR(64) NOT NULL,         -- id da assinatura ou da mensalidade no Mercado Pago
  status VARCHAR(30) NOT NULL,
  valor DECIMAL(8,2) NULL,
  aplicado TINYINT(1) NOT NULL DEFAULT 0,   -- 1 = esta mensalidade já estendeu a assinatura
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (tipo, mp_id, status),
  KEY (pedido_id),
  CONSTRAINT fk_evmp_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
