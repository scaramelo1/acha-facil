-- Passo 65: quem tocou em "Quero saber..." pode pedir para ser avisado quando o comércio completar a ficha.
-- Quando a informação entra (alteração aprovada ou lista nova), a pessoa recebe a notificação uma vez (avisado_em).
CREATE TABLE IF NOT EXISTS avisos_info (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  profissional_id INT NOT NULL,
  tipo VARCHAR(12) NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  avisado_em DATETIME NULL,
  UNIQUE KEY (usuario_id, profissional_id, tipo),
  KEY pendentes (avisado_em, profissional_id),
  CONSTRAINT fk_aviso_info_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
