-- Passo 68: quem tocou em "Quero saber..." (uma linha por pedido contado). Com conta (app ou site) guarda a pessoa;
-- sem conta fica usuario_id NULL (anônimo). Só a administração vê (/admin/quem-pediu.php, números da ficha, pessoa).
CREATE TABLE IF NOT EXISTS pedidos_info_pessoas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  profissional_id INT NOT NULL,
  tipo VARCHAR(12) NOT NULL,
  usuario_id INT NULL,
  origem VARCHAR(12) NOT NULL DEFAULT 'app',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ficha (profissional_id, criado_em),
  KEY usuario (usuario_id),
  KEY criado_em (criado_em),
  CONSTRAINT fk_pedinfo_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
