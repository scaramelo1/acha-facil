-- Pré-carregamento do Chrome (passo 55): a entrada veio do Chrome baixando a página antes do clique (servidor do
-- Google *.fetch.tunnel.googlezip.net ou cabeçalho Sec-Purpose: prefetch). Pode ter sido vista ou não.
ALTER TABLE entradas_hora ADD COLUMN IF NOT EXISTS pre TINYINT(1) NOT NULL DEFAULT 0;
