-- Contas do app só pelo Google (nome, e-mail e foto vêm do Google; não há mais senha no app)
ALTER TABLE usuarios
  MODIFY senha_hash VARCHAR(255) NULL,
  ADD COLUMN google_id VARCHAR(64) NULL UNIQUE AFTER email,
  ADD COLUMN foto VARCHAR(500) NULL AFTER google_id;

-- Uma entrada pelo Google em andamento: o "estado" vai e volta do Google (contra falsificação);
-- depois de achar a conta, guarda o hash do código de uso único que o app troca pela sessão (vale 5 minutos)
CREATE TABLE IF NOT EXISTS logins_google (
  estado CHAR(64) PRIMARY KEY,
  volta VARCHAR(300) NOT NULL,
  codigo_hash CHAR(64) NULL UNIQUE,
  usuario_id INT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (criado_em),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
