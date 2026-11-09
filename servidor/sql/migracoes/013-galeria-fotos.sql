-- Logo + galeria (09/11, pedido do usuário): "foto" passa a ser o LOGO do comércio (listas e círculo do perfil) e
-- "fotos" guarda a galeria que vira o carrossel da capa no perfil: lista JSON com os nomes dos arquivos (em fotos/),
-- na ordem em que aparecem. Muda só pela moderação, como os outros campos do dono.
ALTER TABLE profissionais ADD COLUMN fotos TEXT NULL AFTER foto;
