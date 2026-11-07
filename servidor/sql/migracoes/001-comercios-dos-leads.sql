-- Comércios importados da lista de leads (Google Maps, coleta de 06/11/2026) e novas áreas do app.
-- Importado = aparece sem assinatura até o dono assumir o perfil (aí segue as regras normais).
ALTER TABLE profissionais
  ADD COLUMN importado TINYINT(1) NOT NULL DEFAULT 0 AFTER exemplo,
  ADD COLUMN chave_importacao VARCHAR(160) NULL UNIQUE AFTER importado,   -- nome normalizado, evita repetir na reimportação
  ADD COLUMN tipo VARCHAR(80) NULL AFTER categoria_id,                     -- tipo de negócio (ex.: "Academia", "Padaria")
  ADD COLUMN site VARCHAR(200) NULL AFTER instagram,
  ADD COLUMN mapa VARCHAR(500) NULL AFTER endereco,                        -- link do Google Maps
  ADD COLUMN popularidade INT NOT NULL DEFAULT 0 AFTER total_avaliacoes,   -- nº de avaliações no Google (só para ordenar)
  ADD KEY (importado);

ALTER TABLE categorias ADD COLUMN cor CHAR(7) NOT NULL DEFAULT '#005C38' AFTER icone;

-- Pedidos vindos do site: dono quer assumir o perfil ou pede para tirar do app
CREATE TABLE IF NOT EXISTS pedidos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  profissional_id INT NULL,
  tipo ENUM('assumir','remover') NOT NULL,
  nome VARCHAR(120) NOT NULL,
  telefone VARCHAR(20) NOT NULL,
  mensagem VARCHAR(1000) NULL,
  ip VARCHAR(45) NULL,
  status ENUM('novo','feito','recusado') NOT NULL DEFAULT 'novo',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (status),
  CONSTRAINT fk_ped_prof FOREIGN KEY (profissional_id) REFERENCES profissionais(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sai a demonstração (agora há comércios de verdade) e as categorias antigas, de autônomos, ficam escondidas
DELETE FROM profissionais WHERE exemplo = 1;
UPDATE categorias SET ativa = 0, na_inicial = 0, slug = CONCAT('antiga-', slug) WHERE slug NOT LIKE 'antiga-%';
