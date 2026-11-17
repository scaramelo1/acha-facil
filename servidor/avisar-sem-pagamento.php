<?php
// Roda a cada 10 minutos (/etc/cron.d/acha-facil, instalar.sh). Pedido de plano que não foi pago em 30 minutos:
// avisa os admins uma vez ("Cadastro sem pagamento"), para mandarem mensagem à pessoa. Pago, o aviso é o do Mercado Pago.
declare(strict_types=1);
// 17/11 (pedido do usuário, passo 88): DESLIGADO. Os admins só recebem aviso quando a pessoa paga (Mercado Pago,
// inc/mercadopago.php), usa cupom de cortesia ou assume pelo Grátis (perfil.php). Quem entra no pagamento e não paga
// não gera notificação; o pedido continua em /admin como "não pago" para consulta.
exit;
require __DIR__ . '/inc/base.php';
require_once __DIR__ . '/inc/guia.php';
require_once __DIR__ . '/inc/avisos.php';
if (!valor("SELECT 1 FROM migracoes WHERE nome = '026-aviso-sem-pagamento.sql'")) exit;

$pedidos = linhas("SELECT pe.*, p.nome AS comercio, p.cidade FROM pedidos pe LEFT JOIN profissionais p ON p.id = pe.profissional_id
    WHERE pe.plano IS NOT NULL AND pe.tipo = 'assumir' AND pe.status = 'novo' AND pe.aviso_sem_pagamento_em IS NULL
      AND (pe.mp_status IS NULL OR pe.mp_status NOT IN ('authorized','approved'))
      AND pe.criado_em < NOW() - INTERVAL 30 MINUTE AND pe.criado_em > NOW() - INTERVAL 3 DAY
      AND NOT EXISTS (SELECT 1 FROM pedidos o WHERE o.profissional_id = pe.profissional_id AND o.id > pe.id
                      AND o.mp_status IN ('authorized','approved'))
    ORDER BY pe.id");
foreach ($pedidos as $pe) {
    q('UPDATE pedidos SET aviso_sem_pagamento_em = NOW() WHERE id = ?', [$pe['id']]);
    // 17/11: dono pelo Grátis que escolheu um plano pago = troca de plano, não cadastro novo
    $ja_dono = $pe['profissional_id'] && valor('SELECT 1 FROM acessos_dono WHERE profissional_id = ? AND ativo = 1', [$pe['profissional_id']]);
    avisar_admins($ja_dono ? 'Troca de plano sem pagamento' : 'Cadastro sem pagamento', $pe['nome'] . ' escolheu o plano ' . (PLANOS_NOME[$pe['plano']] ?? $pe['plano'])
        . ($pe['comercio'] ? ' (' . $pe['comercio'] . ')' : '') . ($ja_dono ? ', já é dono pelo Grátis,' : '') . ' e não pagou. Nada para ativar. Toque para mandar mensagem.', ['pedido' => (int)$pe['id']]);
}
