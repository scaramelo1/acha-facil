-- Cidades no banco (10/11, pedido do usuário): cidade nova entra pelo /admin/cidades.php e aparece no app sem APK novo.
-- O app baixa a lista em api/cidades.php e guarda uma cópia para abrir sem internet.
-- ativa = 0: cidade preparada (comércios importados) que ainda não aparece no app.
CREATE TABLE IF NOT EXISTS cidades (
    slug VARCHAR(40) NOT NULL PRIMARY KEY,
    nome VARCHAR(80) NOT NULL,
    curto VARCHAR(30) NOT NULL,
    uf CHAR(2) NOT NULL,
    lat DECIMAL(9,6) NULL,
    lng DECIMAL(9,6) NULL,
    ativa TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO cidades (slug, nome, curto, uf, lat, lng, ordem) VALUES
    ('sao-joao', 'São João da Boa Vista', 'São João', 'SP', -21.969600, -46.798100, 1),
    ('aguas-da-prata', 'Águas da Prata', 'Águas da Prata', 'SP', -21.937100, -46.717200, 2),
    ('pocos-de-caldas', 'Poços de Caldas', 'Poços de Caldas', 'MG', -21.787800, -46.561400, 3),
    ('vargem-grande-do-sul', 'Vargem Grande do Sul', 'Vargem Grande', 'SP', -21.832200, -46.893600, 4),
    ('aguai', 'Aguaí', 'Aguaí', 'SP', -22.059700, -46.980300, 5);
