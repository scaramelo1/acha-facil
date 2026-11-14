-- Entradas no site (passo 38): cada vez que alguém CHEGA ao site vindo de fora (Google, outro buscador, rede social,
-- outro site ou endereço digitado), por dia, origem e página de entrada. Quem continua navegando não conta de novo
-- (cookie af_origem de 30 min, sem dado pessoal). Os termos pesquisados no Google não chegam ao site: ficam no Search Console.
CREATE TABLE IF NOT EXISTS entradas (
  dia DATE NOT NULL,
  origem VARCHAR(12) NOT NULL,
  caminho VARCHAR(190) NOT NULL,
  pagina VARCHAR(12) NOT NULL,
  cidade VARCHAR(40) NULL,
  total INT NOT NULL DEFAULT 0,
  PRIMARY KEY (dia, origem, caminho),
  KEY (dia, pagina)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
