-- Mais de uma cidade: cada comércio é de uma cidade (slug). O app pergunta a cidade ao abrir.
ALTER TABLE profissionais
  ADD COLUMN cidade VARCHAR(40) NOT NULL DEFAULT 'sao-joao' AFTER bairro,
  ADD KEY (cidade, status);
