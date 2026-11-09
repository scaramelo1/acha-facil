import { MaterialCommunityIcons } from '@expo/vector-icons';
import { StyleSheet, Text, View } from 'react-native';

import type { HorarioCampo } from '../api/cliente';
import { cores, fonte } from '../tema';

/** Campo do horário de um dia da semana (0 = domingo). Feriado o app não sabe: usa o dia da semana. */
function campoDoDia(dia: number): HorarioCampo['campo'] {
  return dia === 0 ? 'dom' : dia === 6 ? 'sab' : 'semana';
}
const minutos = (h: string) => Number(h.slice(0, 2)) * 60 + Number(h.slice(3, 5));

/**
 * Aberto agora? Olha o horário de hoje e também o de ontem, para quem fecha depois da meia-noite
 * (ex.: sexta 11:00 às 03:00 ainda está aberto no sábado à 01:00). null = hoje não foi informado.
 */
function situacaoAgora(lista: HorarioCampo[], agora = new Date()): { aberto: boolean; texto: string } | null {
  const achar = (c: HorarioCampo['campo']) => lista.find((h) => h.campo === c);
  const m = agora.getHours() * 60 + agora.getMinutes();
  const ontem = achar(campoDoDia((agora.getDay() + 6) % 7));
  if (ontem?.situacao === 'aberto' && ontem.abre && ontem.fecha && minutos(ontem.fecha) < minutos(ontem.abre) && m < minutos(ontem.fecha))
    return { aberto: true, texto: `Aberto agora · fecha às ${ontem.fecha}` };
  const hoje = achar(campoDoDia(agora.getDay()));
  if (!hoje) return null;
  if (hoje.situacao === '24h') return { aberto: true, texto: 'Aberto 24 horas' };
  if (hoje.situacao === 'fechado' || !hoje.abre || !hoje.fecha) return { aberto: false, texto: 'Fechado hoje' };
  const a = minutos(hoje.abre);
  const f = minutos(hoje.fecha);
  const aberto = f > a ? m >= a && m < f : m >= a;   // fecha depois da meia-noite: aberto do "abre" até o fim do dia
  if (aberto) return { aberto: true, texto: `Aberto agora · fecha às ${hoje.fecha}` };
  return { aberto: false, texto: m < a ? `Fechado agora · abre às ${hoje.abre}` : 'Fechado agora' };
}

/** Horário de funcionamento com um campo por linha (Segunda a sexta, Sábado, Domingo, Feriados) e se está aberto agora. */
export function Horarios({ lista }: { lista: HorarioCampo[] }) {
  const agora = situacaoAgora(lista);
  const campoHoje = campoDoDia(new Date().getDay());
  return (
    <View style={estilos.caixa}>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 14 }}>
        <MaterialCommunityIcons name="clock-outline" size={26} color={cores.verde} />
        <View style={{ flex: 1 }}>
          <Text style={estilos.titulo}>Horário de funcionamento</Text>
          {agora && <Text style={[estilos.agora, { color: agora.aberto ? cores.verde : cores.erro }]}>{agora.texto}</Text>}
        </View>
      </View>
      <View style={estilos.tabela}>
        {lista.map((h) => {
          const hoje = h.campo === campoHoje;
          return (
            <View key={h.campo} style={estilos.linha} accessibilityLabel={`${h.nome}: ${h.texto}${hoje ? ' (hoje)' : ''}`}>
              <Text style={[estilos.dia, hoje && estilos.hoje]}>{h.nome}</Text>
              <Text style={[estilos.horas, hoje && estilos.hoje, h.situacao === 'fechado' && { color: cores.erro }]}>{h.texto}</Text>
            </View>
          );
        })}
      </View>
      <Text style={estilos.nota}>Em feriados o horário pode ser diferente.</Text>
    </View>
  );
}

const estilos = StyleSheet.create({
  caixa: { paddingVertical: 12 },
  titulo: { fontSize: fonte.normal - 1, fontWeight: '800', color: cores.texto },
  agora: { fontSize: fonte.pequena - 1, fontWeight: '800', marginTop: 2 },
  tabela: { marginTop: 10, marginLeft: 40, gap: 8 },
  linha: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  dia: { width: 132, fontSize: fonte.pequena - 1, color: cores.texto },
  horas: { flex: 1, fontSize: fonte.pequena - 1, color: cores.texto },
  hoje: { fontWeight: '900' },
  nota: { fontSize: 13, color: cores.textoSuave, marginTop: 10, marginLeft: 40 },
});
