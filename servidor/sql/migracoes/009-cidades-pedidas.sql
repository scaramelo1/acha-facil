-- "Quero minha Cidade!" na tela de escolher a cidade do app: cada pedido fica aqui para a administração ver
-- quais cidades mais pedem o Acha Fácil.
CREATE TABLE IF NOT EXISTS cidades_pedidas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cidade VARCHAR(80) NOT NULL,
  uf CHAR(2) NULL,
  ip VARCHAR(45) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (criado_em),
  KEY (cidade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
