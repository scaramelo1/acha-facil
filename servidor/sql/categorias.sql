-- Áreas do Acha Fácil (as mesmas da lista de leads). INSERT IGNORE: rodar de novo não desfaz mudanças do admin.
INSERT IGNORE INTO categorias (nome, slug, icone, cor, ordem, na_inicial, ativa) VALUES
('Alimentação', 'alimentacao', 'silverware-fork-knife', '#E8590C', 10, 1, 1),
('Mercados e bebidas', 'mercados', 'cart-outline', '#2B8A3E', 20, 1, 1),
('Serviços', 'servicos', 'tools', '#1864AB', 30, 1, 1),
('Saúde', 'saude', 'heart-pulse', '#C92A2A', 40, 1, 1),
('Beleza', 'beleza', 'lipstick', '#C2255C', 50, 1, 1),
('Moda', 'moda', 'tshirt-crew-outline', '#862E9C', 60, 1, 1),
('Casa e construção', 'casa', 'home-roof', '#A6611A', 70, 1, 1),
('Veículos', 'veiculos', 'car-wrench', '#495057', 80, 1, 1),
('Lojas e varejo', 'lojas', 'shopping-outline', '#0B7285', 90, 1, 1),
('Educação', 'educacao', 'school-outline', '#5F3DC4', 100, 1, 1),
('Esporte e fitness', 'esporte', 'dumbbell', '#D9480F', 110, 1, 1),
('Pet e agro', 'pet', 'paw', '#5C940D', 120, 1, 1),
('Hospedagem e eventos', 'hospedagem', 'bed-outline', '#364FC7', 130, 0, 1),
('Financeiro', 'financeiro', 'bank-outline', '#087F5B', 140, 0, 1),
('Indústria', 'industria', 'factory', '#5C677D', 150, 0, 1),
('Igrejas e entidades', 'entidades', 'hand-heart-outline', '#9C36B5', 160, 0, 1),
('Outros', 'outros', 'dots-horizontal-circle-outline', '#6C757D', 170, 0, 1);
