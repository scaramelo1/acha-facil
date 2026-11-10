-- Passo 20: contador de visualizações para todas as fichas.
-- contatos ganha a ORIGEM (de onde a pessoa veio: app, busca, categoria, perto, destaque, inicio, favoritos, link, web)
-- e PESSOAS (pessoas diferentes no dia; só no tipo 'perfil'). As linhas antigas ficam com origem 'app'.
ALTER TABLE contatos
  ADD COLUMN origem VARCHAR(12) NOT NULL DEFAULT 'app' AFTER dia,
  ADD COLUMN pessoas INT NOT NULL DEFAULT 0 AFTER total,
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (profissional_id, tipo, dia, origem),
  ADD KEY dia_tipo (dia, tipo);
-- antes de 10/11 não havia como separar pessoas: conta cada abertura antiga como uma pessoa (aproximado, poucos dias)
UPDATE contatos SET pessoas = total WHERE tipo = 'perfil';

-- Quem já abriu cada ficha HOJE (código anônimo embaralhado, 16 bytes). Serve só para contar pessoas diferentes por dia
-- e não contar de novo quem reabre em menos de 30 min. Linhas de antes de ontem são apagadas (inc/numeros.php).
CREATE TABLE IF NOT EXISTS vistas (
  profissional_id INT NOT NULL,
  dia DATE NOT NULL,
  visitante BINARY(16) NOT NULL,
  ultima DATETIME NOT NULL,
  PRIMARY KEY (profissional_id, dia, visitante),
  KEY (dia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aparições: quantas vezes a ficha foi MOSTRADA numa lista (inicio, categoria, busca, perto), aberta ou não.
CREATE TABLE IF NOT EXISTS aparicoes (
  profissional_id INT NOT NULL,
  dia DATE NOT NULL,
  onde VARCHAR(12) NOT NULL,
  total INT NOT NULL DEFAULT 0,
  PRIMARY KEY (profissional_id, dia, onde),
  KEY (dia),
  CONSTRAINT fk_apar_prof FOREIGN KEY (profissional_id) REFERENCES profissionais(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
