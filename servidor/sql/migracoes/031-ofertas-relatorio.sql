-- 19/11 (pedido do usuário):
-- Ofertas da semana: benefício pago à parte de quem tem o Destaque. O dono escreve a oferta no painel, paga por oferta
-- (pagamento único no Mercado Pago, como o Básico), a equipe confere e ela fica no ar por até 7 dias, na ficha e na
-- página da cidade. Recusada: o dono corrige e manda de novo sem pagar outra vez.
CREATE TABLE IF NOT EXISTS ofertas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  profissional_id INT NOT NULL,
  usuario_id INT NULL,
  titulo VARCHAR(80) NOT NULL,
  texto VARCHAR(400) NULL,
  preco_de DECIMAL(8,2) NULL,
  preco_por DECIMAL(8,2) NULL,
  foto VARCHAR(80) NULL,
  dias TINYINT UNSIGNED NOT NULL DEFAULT 7,
  status ENUM('aguardando_pagamento','em_analise','no_ar','recusada','cancelada') NOT NULL DEFAULT 'aguardando_pagamento',
  motivo VARCHAR(300) NULL,
  codigo CHAR(32) NOT NULL,
  valor DECIMAL(8,2) NOT NULL,
  mp_pagamento VARCHAR(64) NULL,
  mp_status VARCHAR(20) NULL,
  pago_em DATETIME NULL,
  inicio DATETIME NULL,
  fim DATETIME NULL,
  admin_id INT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL,
  UNIQUE KEY codigo (codigo),
  UNIQUE KEY mp_pagamento (mp_pagamento),
  KEY no_ar (status, fim),
  KEY ficha (profissional_id, status),
  CONSTRAINT fk_oferta_prof FOREIGN KEY (profissional_id) REFERENCES profissionais (id) ON DELETE CASCADE,
  CONSTRAINT fk_oferta_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL,
  CONSTRAINT fk_oferta_admin FOREIGN KEY (admin_id) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relatório mensal automático para quem paga (Básico ou Destaque): um por comércio por mês, com os números do mês
-- guardados (o aviso no celular e a página do painel mostram os mesmos números).
CREATE TABLE IF NOT EXISTS relatorios_mensais (
  id INT AUTO_INCREMENT PRIMARY KEY,
  profissional_id INT NOT NULL,
  mes CHAR(7) NOT NULL,
  visualizacoes INT NOT NULL DEFAULT 0,
  pessoas INT NOT NULL DEFAULT 0,
  aparicoes INT NOT NULL DEFAULT 0,
  whatsapp INT NOT NULL DEFAULT 0,
  ligar INT NOT NULL DEFAULT 0,
  mapa INT NOT NULL DEFAULT 0,
  avisados INT NOT NULL DEFAULT 0,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY ficha_mes (profissional_id, mes),
  CONSTRAINT fk_relmes_prof FOREIGN KEY (profissional_id) REFERENCES profissionais (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
