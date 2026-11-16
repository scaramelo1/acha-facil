import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { pedirInfo, type TipoPedidoInfo } from '../api/cliente';
import { Armazenamento } from '../armazenamento';
import { cores, fonte } from '../tema';

/**
 * Espaço trancado na ficha incompleta (passo 59): mostra o que falta e o botão "Quero saber...".
 * O pedido é contado no servidor (api/quero.php) e vira argumento para o dono completar a ficha.
 * O celular lembra quem já pediu, para não mostrar o botão de novo.
 */
export function Trancado({
  id,
  tipo,
  titulo,
  texto,
  botao,
  aoTocarDono,
}: {
  id: number;
  tipo: TipoPedidoInfo;
  titulo: string;
  texto: string;
  botao: string;
  aoTocarDono?: () => void;
}) {
  const chave = `quero_${id}_${tipo}`;
  const [feito, setFeito] = useState(() => Armazenamento.ler(chave) === '1');
  const pedir = () => {
    setFeito(true);
    Armazenamento.gravar(chave, '1');
    pedirInfo(id, tipo);
  };
  return (
    <View style={estilos.caixa}>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
        <MaterialCommunityIcons name="lock-outline" size={20} color={cores.textoSuave} />
        <Text style={estilos.titulo}>{titulo}</Text>
      </View>
      <Text style={estilos.texto}>{texto}</Text>
      {feito ? (
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
          <MaterialCommunityIcons name="check-circle" size={20} color={cores.verde} />
          <Text style={[estilos.ok, { flex: 1 }]}>Pedido anotado! Quando o comércio completar a ficha, a informação aparece aqui.</Text>
        </View>
      ) : (
        <Pressable accessibilityRole="button" onPress={pedir} style={({ pressed }) => [estilos.botao, pressed && { opacity: 0.85 }]}>
          <MaterialCommunityIcons name="hand-wave-outline" size={20} color={cores.verde} />
          <Text style={estilos.botaoTexto}>{botao}</Text>
        </Pressable>
      )}
      {aoTocarDono && (
        <Text style={estilos.dono}>
          É o dono?{' '}
          <Text style={{ fontWeight: '800', color: cores.verde, textDecorationLine: 'underline' }} onPress={aoTocarDono}>
            Complete a sua ficha
          </Text>
        </Text>
      )}
    </View>
  );
}

const estilos = StyleSheet.create({
  caixa: { borderWidth: 2, borderStyle: 'dashed', borderColor: cores.borda, borderRadius: 16, padding: 14, gap: 8, marginTop: 12 },
  titulo: { fontSize: fonte.normal, fontWeight: '900', color: cores.texto },
  texto: { fontSize: fonte.pequena, color: cores.textoSuave },
  botao: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    minHeight: 48,
    borderRadius: 24,
    borderWidth: 2,
    borderColor: cores.verde,
    paddingHorizontal: 16,
    alignSelf: 'flex-start',
  },
  botaoTexto: { fontSize: fonte.pequena, fontWeight: '900', color: cores.verde },
  ok: { fontSize: fonte.pequena, fontWeight: '700', color: cores.verde },
  dono: { fontSize: 14, color: cores.textoSuave },
});
