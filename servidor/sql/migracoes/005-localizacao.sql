-- Localização dos comércios para "Perto de você" (distância até o usuário).
-- Vem do link do Google Maps quando ele traz as coordenadas, ou do endereço (OpenStreetMap/Nominatim, geocodificar.php).
ALTER TABLE profissionais
  ADD COLUMN lat DECIMAL(9,6) NULL AFTER mapa,
  ADD COLUMN lng DECIMAL(9,6) NULL AFTER lat,
  ADD COLUMN geo_origem ENUM('google','endereco','rua','bairro','manual') NULL AFTER lng,   -- precisão aproximada
  ADD COLUMN geo_tentado DATETIME NULL AFTER geo_origem,                                      -- última tentativa (evita repetir)
  ADD KEY (lat, lng);
