-- Avisos no celular dos administradores (Expo Push): o app de quem é admin (e-mail da conta Google na tabela admins)
-- registra aqui o endereço de aviso do aparelho. Avisa quando alguém paga, pede uma cidade ou manda alterações.
CREATE TABLE IF NOT EXISTS avisos_aparelhos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  token VARCHAR(200) NOT NULL UNIQUE,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (usuario_id),
  CONSTRAINT fk_aviso_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
