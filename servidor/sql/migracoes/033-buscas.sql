-- 19/11 (passo 106): o que as pessoas procuram no site e no app, para o /admin/buscas.php
-- (buscas sem resultado = sinônimo que falta ou comércio que falta na cidade).
CREATE TABLE IF NOT EXISTS buscas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cidade VARCHAR(60) NOT NULL DEFAULT '',
  termo VARCHAR(60) NOT NULL,
  resultados SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  corrigido VARCHAR(60) NULL,
  origem ENUM('app','site') NOT NULL DEFAULT 'app',
  quem BINARY(16) NOT NULL,
  KEY criado (criado_em),
  KEY cidade_criado (cidade, criado_em),
  KEY quem (quem, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
