-- Horário por campo (09/11, pedido do usuário): Segunda a sexta, Sábado, Domingo e Feriados, cada um aberto (abre/fecha,
-- pode fechar depois da meia-noite), fechado ou 24 horas. Lista JSON em "horarios"; "horario" (texto) continua
-- sendo gerado a partir dela para o app antigo e as páginas que só mostram texto.
ALTER TABLE profissionais ADD COLUMN horarios TEXT NULL AFTER horario, MODIFY horario VARCHAR(400) NULL;
