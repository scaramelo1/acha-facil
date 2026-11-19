-- 19/11 (passo 112): hora de cada visualização de ficha e de cada toque (WhatsApp, ligar, mapa...), para a tabela
-- "Por hora" do relatório do dia (/admin/entradas-dia.php). Antes disso só existia o total do dia (tabela contatos).
CREATE TABLE IF NOT EXISTS contatos_hora (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quando DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  profissional_id INT NOT NULL,
  tipo VARCHAR(12) NOT NULL,
  origem VARCHAR(12) NOT NULL DEFAULT 'app',
  pessoa TINYINT(1) NOT NULL DEFAULT 0,
  KEY quando (quando),
  KEY ficha (profissional_id, quando)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
