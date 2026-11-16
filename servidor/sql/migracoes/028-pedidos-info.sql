-- Passo 59: botões "Quero saber o horário / ver fotos / saber mais / o WhatsApp" nas fichas sem esses dados.
-- Cada pedido conta 1 por pessoa, por ficha e por tipo, no dia. Vira argumento de venda para o dono completar a ficha.
CREATE TABLE IF NOT EXISTS pedidos_info (
  profissional_id INT NOT NULL,
  tipo VARCHAR(12) NOT NULL,
  dia DATE NOT NULL,
  origem VARCHAR(12) NOT NULL DEFAULT 'app',
  total INT NOT NULL DEFAULT 0,
  PRIMARY KEY (profissional_id, tipo, dia, origem),
  KEY (dia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quem já pediu HOJE (código anônimo embaralhado, como em `vistas`): não contar a mesma pessoa duas vezes no dia.
-- Linhas de antes de ontem são apagadas (inc/numeros.php).
CREATE TABLE IF NOT EXISTS pedidos_info_vistos (
  profissional_id INT NOT NULL,
  tipo VARCHAR(12) NOT NULL,
  dia DATE NOT NULL,
  visitante BINARY(16) NOT NULL,
  PRIMARY KEY (profissional_id, tipo, dia, visitante),
  KEY (dia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
