-- Eventos enviados pelo público em /incluir-evento.php (passo 36): entram escondidos (visivel = 0, status 'pendente')
-- e um administrador aprova em /admin/eventos.php. Os cadastrados pelo admin ficam 'publicado'.
ALTER TABLE eventos
  ADD COLUMN status VARCHAR(10) NOT NULL DEFAULT 'publicado' AFTER visivel,
  ADD COLUMN enviado_nome VARCHAR(120) NULL,
  ADD COLUMN enviado_contato VARCHAR(20) NULL,
  ADD COLUMN enviado_recado VARCHAR(1000) NULL,
  ADD COLUMN enviado_ip VARCHAR(45) NULL,
  ADD KEY (status);
