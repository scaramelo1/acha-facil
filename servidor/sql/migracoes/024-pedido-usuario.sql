-- Conta de quem fez o pedido (quando entrou com o Google no site): para avisar no celular quando o pedido for aceito.
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS usuario_id INT NULL AFTER profissional_id, ADD KEY IF NOT EXISTS (usuario_id);
