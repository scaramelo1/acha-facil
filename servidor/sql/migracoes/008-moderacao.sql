-- Moderação: nada do que o dono do comércio muda vai direto para o app. Cada mudança vira uma "alteração"
-- pendente, e só um administrador aplica (ou recusa). O dono entra no painel por um link secreto que o admin
-- gera depois de confirmar (por telefone/WhatsApp) que ele é o responsável.

-- Acesso do dono ao painel (link secreto; guardamos só o hash). Um comércio pode ter mais de um acesso.
CREATE TABLE IF NOT EXISTS acessos_dono (
  id INT AUTO_INCREMENT PRIMARY KEY,
  profissional_id INT NOT NULL,
  pedido_id INT NULL,
  nome VARCHAR(120) NOT NULL,
  telefone VARCHAR(20) NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  admin_id INT NULL,                    -- quem liberou
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_uso DATETIME NULL,
  KEY (profissional_id),
  CONSTRAINT fk_acesso_prof FOREIGN KEY (profissional_id) REFERENCES profissionais(id) ON DELETE CASCADE,
  CONSTRAINT fk_acesso_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE SET NULL,
  CONSTRAINT fk_acesso_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alteração pedida pelo dono: só os campos que mudaram (novos e como estavam), até um admin decidir.
CREATE TABLE IF NOT EXISTS alteracoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  profissional_id INT NOT NULL,
  acesso_id INT NULL,
  novos JSON NOT NULL,                  -- {"campo": "valor novo"}; foto = nome do arquivo novo (em fotos/)
  anteriores JSON NOT NULL,             -- {"campo": "valor quando o dono pediu"}
  status ENUM('pendente','aprovada','recusada') NOT NULL DEFAULT 'pendente',
  motivo VARCHAR(500) NULL,             -- por que foi recusada (o dono vê no painel)
  admin_id INT NULL,
  ip VARCHAR(45) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decidido_em DATETIME NULL,
  KEY (status, criado_em),
  KEY (profissional_id),
  CONSTRAINT fk_alt_prof FOREIGN KEY (profissional_id) REFERENCES profissionais(id) ON DELETE CASCADE,
  CONSTRAINT fk_alt_acesso FOREIGN KEY (acesso_id) REFERENCES acessos_dono(id) ON DELETE SET NULL,
  CONSTRAINT fk_alt_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- O que cada admin fez (aprovar, recusar, liberar acesso, tirar do app...)
CREATE TABLE IF NOT EXISTS registro_admin (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NULL,
  acao VARCHAR(40) NOT NULL,
  profissional_id INT NULL,
  detalhe VARCHAR(500) NULL,
  ip VARCHAR(45) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (criado_em),
  CONSTRAINT fk_reg_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
