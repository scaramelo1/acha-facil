-- Plano "Destaque + Site" (10/11, pedido do usuário): assinatura mensal com tudo do Destaque + landing page própria
-- feita pela FSA e domínio incluso. Chave 'site' (preço em PLANOS_PRECO, inc/guia.php). Conta como Destaque (SQL_DESTAQUE).
ALTER TABLE profissionais MODIFY plano ENUM('basico','destaque','site') NOT NULL DEFAULT 'basico';
ALTER TABLE pedidos MODIFY plano ENUM('basico','destaque','site') NULL;
ALTER TABLE cupons MODIFY planos SET('basico','destaque','site') NOT NULL;
