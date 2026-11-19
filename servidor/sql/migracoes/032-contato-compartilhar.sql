-- 19/11: toque em "Compartilhar no WhatsApp" na ficha do site (conta nos números do dono, fora do total de contatos).
ALTER TABLE contatos MODIFY tipo ENUM('perfil','whatsapp','ligar','instagram','mapa','site','compartilhar') NOT NULL;
