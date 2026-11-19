import * as Notifications from 'expo-notifications';

import { Armazenamento } from './armazenamento';
import { criarCanais } from './avisos';

// Pedir avaliacao depois do contato (19/11, passo 107, pedido do usuario, item 9 da avaliacao).
// Quem toca em WhatsApp ou Ligar na ficha fica guardado no celular. No dia seguinte, as 18h, chega um aviso local
// "Como foi no X?" (sem servidor; so se a pessoa deixou os avisos ligados) que abre a ficha com o convite para avaliar.
// Quem volta a ficha depois de 2 horas tambem ve o convite. Cada comercio pede no maximo 1 vez a cada 30 dias,
// e so fica 1 aviso agendado por vez (o ultimo contato).

interface Contato {
  id: number;
  nome: string;
  em: number; // quando tocou (ms)
  fechado?: boolean; // a pessoa fechou o convite ou ja avaliou
}

const CHAVE = 'contatos-para-avaliar';
const CHAVE_PEDIDOS = 'avaliacao-pedida-em';
const MAX = 20;
const HORA = 60 * 60 * 1000;
const DIA = 24 * HORA;
export const CONVITE_DEPOIS = 2 * HORA;
const CONVITE_ATE = 14 * DIA;
const PEDIR_DE_NOVO = 30 * DIA;
const PREFIXO_AVISO = 'avaliar-';

function lerJson<T>(chave: string, padrao: T): T {
  try {
    const v = Armazenamento.ler(chave);
    return v ? (JSON.parse(v) as T) : padrao;
  } catch {
    return padrao;
  }
}

function contatos(): Contato[] {
  const l = lerJson<Contato[]>(CHAVE, []);
  return Array.isArray(l) ? l : [];
}

/** Dia seguinte as 18h (hora do celular). */
function amanhaAs18(agora: Date): Date {
  const d = new Date(agora);
  d.setDate(d.getDate() + 1);
  d.setHours(18, 0, 0, 0);
  return d;
}

/** Tocou em WhatsApp ou Ligar: guarda e agenda o aviso de amanha. */
export async function lembrarContato(p: { id: number; nome: string; jaAvaliou: boolean }): Promise<void> {
  const agora = Date.now();
  const lista = contatos().filter((c) => c.id !== p.id && agora - c.em < CONVITE_ATE);
  lista.unshift({ id: p.id, nome: p.nome, em: agora, fechado: p.jaAvaliou });
  Armazenamento.gravar(CHAVE, JSON.stringify(lista.slice(0, MAX)));
  if (p.jaAvaliou) return;

  const pedidos = lerJson<Record<string, number>>(CHAVE_PEDIDOS, {});
  if (agora - (pedidos[String(p.id)] ?? 0) < PEDIR_DE_NOVO) return;
  try {
    const permissao = await Notifications.getPermissionsAsync();
    if (!permissao.granted) return;
    await criarCanais();
    // so 1 aviso de avaliacao agendado por vez: tira os anteriores
    const agendados = await Notifications.getAllScheduledNotificationsAsync();
    await Promise.all(
      agendados.filter((n) => n.identifier.startsWith(PREFIXO_AVISO)).map((n) => Notifications.cancelScheduledNotificationAsync(n.identifier)),
    );
    await Notifications.scheduleNotificationAsync({
      identifier: `${PREFIXO_AVISO}${p.id}`,
      content: {
        title: `Como foi no ${p.nome}?`,
        body: 'Dê sua nota em estrelas. Leva 10 segundos e ajuda quem procura na sua cidade.',
        data: { tela: 'avaliar', id: p.id },
      },
      trigger: { type: Notifications.SchedulableTriggerInputTypes.DATE, date: amanhaAs18(new Date(agora)), channelId: 'novidades' },
    });
    pedidos[String(p.id)] = agora;
    Armazenamento.gravar(CHAVE_PEDIDOS, JSON.stringify(pedidos));
  } catch {
    // sem modulo de avisos (Expo Go) ou o celular recusou: fica so o convite na ficha
  }
}

/** Mostrar o convite na ficha? (contato ha mais de 2 horas, ate 14 dias, nao fechado) */
export function deveConvidar(id: number): boolean {
  const c = contatos().find((x) => x.id === id);
  if (!c || c.fechado) return false;
  const passou = Date.now() - c.em;
  return passou >= CONVITE_DEPOIS && passou < CONVITE_ATE;
}

/** Fechou o convite ou ja avaliou: nao mostra mais e tira o aviso agendado. */
export function fecharConvite(id: number): void {
  const lista = contatos().map((c) => (c.id === id ? { ...c, fechado: true } : c));
  Armazenamento.gravar(CHAVE, JSON.stringify(lista));
  Notifications.cancelScheduledNotificationAsync(`${PREFIXO_AVISO}${id}`).catch(() => {});
}
