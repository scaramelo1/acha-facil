-- Suporte (passo 53): pessoas com conta Google mandam mensagens para a equipe, como chamados. Uma conversa por chamado.
CREATE TABLE IF NOT EXISTS chamados (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  profissional_id INT NULL,
  assunto VARCHAR(40) NOT NULL,
  status ENUM('aberto','respondido','fechado') NOT NULL DEFAULT 'aberto',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (usuario_id, atualizado_em),
  KEY (status, atualizado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chamados_mensagens (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  chamado_id INT NOT NULL,
  admin_id INT NULL,
  texto TEXT NOT NULL,
  ip VARCHAR(45) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (chamado_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
