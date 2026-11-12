-- Agenda de eventos por cidade (passo 32): cadastrados pelos administradores em /admin/eventos.php e mostrados no site
-- em /<cidade>/eventos/ (agenda em ordem de data) e /<cidade>/eventos/<nome>-<id> (página do evento).
CREATE TABLE IF NOT EXISTS eventos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cidade VARCHAR(40) NOT NULL,
  titulo VARCHAR(140) NOT NULL,
  data_inicio DATE NOT NULL,
  hora_inicio TIME NULL,               -- NULL = sem hora definida (o dia todo)
  data_fim DATE NULL,                  -- NULL = um dia só
  hora_fim TIME NULL,
  local_nome VARCHAR(140) NULL,        -- ex.: Recinto de Exposições
  endereco VARCHAR(200) NULL,
  preco VARCHAR(80) NULL,              -- texto livre: "Gratuito", "R$ 20 (meia R$ 10)"
  descricao TEXT NULL,
  link_oficial VARCHAR(300) NULL,
  imagem VARCHAR(100) NULL,            -- arquivo em dados_dir/fotos (servido por foto.php)
  visivel TINYINT(1) NOT NULL DEFAULT 1,
  admin_id INT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (cidade, data_inicio),
  KEY (data_fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
