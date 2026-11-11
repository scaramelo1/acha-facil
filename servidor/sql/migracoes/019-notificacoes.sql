-- Notificações para os usuários do app (Expo Push), enviadas pelos administradores no /admin do site ou no app.
-- avisos_publico: todo aparelho com o app (com ou sem conta) que aceitou receber avisos; guarda a última cidade escolhida.
-- notificacoes: histórico do que foi enviado (para quem, quantos aparelhos receberam).
CREATE TABLE IF NOT EXISTS avisos_publico (
  id INT AUTO_INCREMENT PRIMARY KEY,
  token VARCHAR(200) NOT NULL UNIQUE,
  usuario_id INT NULL,
  cidade VARCHAR(40) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (cidade),
  KEY (usuario_id),
  CONSTRAINT fk_aviso_publico_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notificacoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(80) NOT NULL,
  texto VARCHAR(180) NOT NULL,
  cidade VARCHAR(40) NULL,             -- NULL = todas as cidades
  publico VARCHAR(10) NOT NULL DEFAULT 'todos',   -- 'todos' ou 'contas' (só quem entrou com o Google)
  profissional_id INT NULL,            -- tocar abre a ficha deste comércio
  admin_id INT NULL,
  pelo VARCHAR(10) NOT NULL DEFAULT 'site',       -- 'site' ou 'app'
  aparelhos INT NOT NULL DEFAULT 0,
  entregues INT NOT NULL DEFAULT 0,
  falhas INT NOT NULL DEFAULT 0,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
