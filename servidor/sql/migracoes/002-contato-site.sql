-- Conta também os toques em "Ver site"
ALTER TABLE contatos MODIFY tipo ENUM('perfil','whatsapp','ligar','instagram','mapa','site') NOT NULL;
