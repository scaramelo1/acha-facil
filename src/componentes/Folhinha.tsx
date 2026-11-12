import { StyleSheet, Text, View } from 'react-native';

import { cores } from '../tema';

/** Folhinha de calendario: dia da semana na faixa verde, o dia grande e o mes embaixo (como no site). */
export function Folhinha({ semana, dia, mes, grande }: { semana: string; dia: string; mes: string; grande?: boolean }) {
  const varios = dia.includes(' a ');
  return (
    <View style={[estilos.caixa, grande && { width: 88 }]} accessible={false}>
      <Text style={estilos.semana}>{semana}</Text>
      <Text style={[estilos.dia, varios && estilos.diaVarios, grande && !varios && { fontSize: 36, lineHeight: 42 }]} numberOfLines={1} adjustsFontSizeToFit>
        {dia}
      </Text>
      <Text style={estilos.mes}>{mes}</Text>
    </View>
  );
}

const estilos = StyleSheet.create({
  caixa: { width: 72, borderRadius: 14, borderWidth: 2, borderColor: cores.verde, backgroundColor: cores.cartao, overflow: 'hidden', alignItems: 'center' },
  semana: { alignSelf: 'stretch', textAlign: 'center', backgroundColor: cores.verde, color: '#fff', fontSize: 13, fontWeight: '900', paddingVertical: 3 },
  dia: { fontSize: 30, lineHeight: 36, fontWeight: '900', color: cores.verde, paddingHorizontal: 4, marginTop: 2 },
  diaVarios: { fontSize: 18, lineHeight: 30 },
  mes: { fontSize: 13, fontWeight: '900', color: cores.textoSuave, paddingBottom: 5 },
});
