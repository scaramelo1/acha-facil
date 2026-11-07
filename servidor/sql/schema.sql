-- Acha Fácil — tabelas (só cria o que falta; mudanças futuras vão em sql/migracoes/NNN-*.sql)

CREATE TABLE IF NOT EXISTS categorias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL UNIQUE,
  icone VARCHAR(60) NOT NULL DEFAULT 'briefcase-outline',   -- nome do ícone MaterialCommunityIcons no app
  ordem INT NOT NULL DEFAULT 100,
  na_inicial TINYINT(1) NOT NULL DEFAULT 1,                  -- aparece como botão na tela inicial
  ativa TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Profissional ou comércio do Acha Fácil. Aparece no app quando status = 'ativo' e a assinatura está em dia (ou é cortesia).
CREATE TABLE IF NOT EXISTS profissionais (
  id INT AUTO_INCREMENT PRIMARY KEY,
  categoria_id INT NOT NULL,
  nome VARCHAR(120) NOT NULL,                 -- nome que aparece no app (pessoa ou negócio)
  responsavel VARCHAR(120) NULL,
  descricao TEXT NULL,
  servicos VARCHAR(500) NULL,                 -- lista curta separada por vírgula
  telefone VARCHAR(20) NULL,
  whatsapp VARCHAR(20) NULL,
  instagram VARCHAR(60) NULL,
  endereco VARCHAR(200) NULL,
  bairro VARCHAR(80) NULL,
  atende_em_casa TINYINT(1) NOT NULL DEFAULT 0,
  horario VARCHAR(200) NULL,
  foto VARCHAR(120) NULL,                     -- arquivo em dados_dir/fotos
  email VARCHAR(160) NULL UNIQUE,             -- login do painel do profissional
  senha_hash VARCHAR(255) NULL,
  status ENUM('pendente','ativo','pausado','recusado') NOT NULL DEFAULT 'pendente',
  verificado TINYINT(1) NOT NULL DEFAULT 0,   -- selo "Verificado pelo Acha Fácil"
  plano ENUM('basico','destaque') NOT NULL DEFAULT 'basico',
  assinatura ENUM('sem','ativa','atrasada','cancelada','cortesia') NOT NULL DEFAULT 'sem',
  assinatura_ate DATE NULL,
  mp_assinatura VARCHAR(64) NULL,             -- id da assinatura (preapproval) no Mercado Pago
  exemplo TINYINT(1) NOT NULL DEFAULT 0,      -- cadastro de demonstração; apagar antes de lançar
  nota_media DECIMAL(3,2) NOT NULL DEFAULT 0,
  total_avaliacoes INT NOT NULL DEFAULT 0,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (categoria_id, status),
  KEY (plano, status),
  FULLTEXT KEY busca (nome, descricao, servicos, bairro),
  CONSTRAINT fk_prof_cat FOREIGN KEY (categoria_id) REFERENCES categorias(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quem usa o app para avaliar, comentar e salvar profissionais (navegar não precisa de conta)
CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(80) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  senha_hash VARCHAR(255) NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessões do app: o app guarda o token; o banco só guarda o hash
CREATE TABLE IF NOT EXISTS sessoes_app (
  token_hash CHAR(64) PRIMARY KEY,
  usuario_id INT NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (usuario_id),
  CONSTRAINT fk_sess_usu FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Uma avaliação por pessoa por profissional (pode editar); o admin pode ocultar
CREATE TABLE IF NOT EXISTS avaliacoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  profissional_id INT NOT NULL,
  usuario_id INT NOT NULL,
  estrelas TINYINT NOT NULL,
  comentario VARCHAR(1000) NULL,
  status ENUM('publicada','oculta') NOT NULL DEFAULT 'publicada',
  resposta VARCHAR(1000) NULL,                -- resposta do profissional
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY (profissional_id, usuario_id),
  CONSTRAINT fk_aval_prof FOREIGN KEY (profissional_id) REFERENCES profissionais(id) ON DELETE CASCADE,
  CONSTRAINT fk_aval_usu FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS favoritos (
  usuario_id INT NOT NULL,
  profissional_id INT NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id, profissional_id),
  CONSTRAINT fk_fav_usu FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_fav_prof FOREIGN KEY (profissional_id) REFERENCES profissionais(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cliques por dia (perfil aberto, WhatsApp, ligação): o profissional vê o retorno do anúncio
CREATE TABLE IF NOT EXISTS contatos (
  profissional_id INT NOT NULL,
  tipo ENUM('perfil','whatsapp','ligar','instagram','mapa') NOT NULL,
  dia DATE NOT NULL,
  total INT NOT NULL DEFAULT 0,
  PRIMARY KEY (profissional_id, tipo, dia),
  CONSTRAINT fk_cont_prof FOREIGN KEY (profissional_id) REFERENCES profissionais(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tentativas_login (
  ip VARCHAR(45) NOT NULL,
  quando DATETIME NOT NULL,
  KEY (ip, quando)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(80) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  senha_hash VARCHAR(255) NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
