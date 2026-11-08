-- Incluir negócio de graça (08/11): o dono manda os dados iniciais (como os da lista de leads), o comércio fica
-- 'pendente' (fora do app) e o pedido tipo 'incluir' espera um admin aprovar ou recusar.
ALTER TABLE pedidos MODIFY tipo ENUM('assumir','remover','incluir') NOT NULL;
