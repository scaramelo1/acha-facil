-- Plano escolhido pelo dono ao pedir para assumir o perfil (página de planos)
ALTER TABLE pedidos ADD COLUMN plano ENUM('basico','destaque') NULL AFTER tipo;
