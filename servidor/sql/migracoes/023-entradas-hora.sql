-- Data e hora de cada entrada no site (passo 42): uma linha por entrada, para a tabela "Páginas onde entraram"
-- de /admin/entradas.php mostrar quando foi. A tabela entradas (022) continua com os totais por dia.
CREATE TABLE IF NOT EXISTS entradas_hora (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  quando DATETIME NOT NULL,
  origem VARCHAR(12) NOT NULL,
  caminho VARCHAR(190) NOT NULL,
  pagina VARCHAR(12) NOT NULL,
  cidade VARCHAR(40) NULL,
  KEY (origem, quando),
  KEY (quando)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
