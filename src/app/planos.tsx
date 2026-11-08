import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useLocalSearchParams } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { ScrollView, StyleSheet, Text, View } from 'react-native';

import { URL_PLANOS } from '../api/cliente';
import { Cabecalho } from '../componentes/Cabecalho';
import { Botao } from '../componentes/ui';
import { cartao, cores, fonte } from '../tema';

type NomeIcone = keyof typeof MaterialCommunityIcons.glyphMap;

// O que o dono do comércio ganha em cada plano (iguais aos do site, servidor/publico/planos.php).
// Sem preço nem botão de contratar no app (regra das lojas de apps): os preços e a assinatura ficam só no site.
const PLANOS: { chave: string; nome: string; sub: string; icone: NomeIcone; itens: string[] }[] = [
  {
    chave: 'basico',
    nome: 'Básico',
    sub: 'Seu perfil completo e sempre atualizado.',
    icone: 'storefront-outline',
    itens: ['Corrigir nome, telefone, WhatsApp, endereço e horário', 'Colocar fotos do lugar e dos produtos', 'Descrição e o que você vende ou faz'],
  },
  {
    chave: 'destaque',
    nome: 'Destaque',
    sub: 'Seu comércio em evidência na cidade.',
    icone: 'star-outline',
    itens: [
      'Tudo o que tem no Básico',
      'Selo "Verificado pelo Acha Fácil"',
      'Responder as avaliações dos clientes',
      'Ver quantas pessoas abriram sua página e chamaram no WhatsApp',
      'Aparece no topo da tela inicial',
      'Na frente dos outros na área e na busca',
    ],
  },
];

/** "Este comércio é seu?": o que o dono pode fazer, e "Saiba mais no site" (com ?id=, o site já abre naquele comércio). */
export default function TelaParaComercios() {
  const { id } = useLocalSearchParams<{ id?: string }>();
  const saibaMais = () => WebBrowser.openBrowserAsync(`${URL_PLANOS}${id ? `?id=${encodeURIComponent(id)}` : ''}`);

  return (
    <View style={{ flex: 1 }}>
      <ScrollView contentContainerStyle={{ paddingBottom: 24 }}>
        <Cabecalho voltar titulo="Este comércio é seu?" />
        <View style={{ padding: 16, gap: 14 }}>
          <Text style={estilos.intro}>Assuma o perfil do seu comércio no Acha Fácil para manter as informações certas e ser encontrado por mais clientes.</Text>
          {PLANOS.map((p) => (
            <View key={p.chave} style={[estilos.plano, p.chave === 'destaque' && { borderColor: cores.amarelo, borderWidth: 2 }]}>
              <View style={{ flexDirection: 'row', gap: 14, alignItems: 'center' }}>
                <View style={[estilos.icone, p.chave === 'destaque' && { backgroundColor: cores.fundoSuave }]}>
                  <MaterialCommunityIcons name={p.icone} size={36} color={cores.verde} />
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={estilos.nome}>{p.nome}</Text>
                  <Text style={estilos.sub}>{p.sub}</Text>
                </View>
              </View>
              <View style={{ gap: 6, marginTop: 12 }}>
                {p.itens.map((i) => (
                  <View key={i} style={{ flexDirection: 'row', gap: 8, alignItems: 'flex-start' }}>
                    <MaterialCommunityIcons name="check" size={22} color={cores.whatsapp} />
                    <Text style={estilos.item}>{i}</Text>
                  </View>
                ))}
              </View>
            </View>
          ))}
          <Text style={estilos.nota}>Assumir o perfil do seu comércio e pedir para tirar do app não custam nada.</Text>
        </View>
      </ScrollView>
      <View style={estilos.rodape}>
        <Botao titulo="Saiba mais no site" icone="open-in-new" tipo="amarelo" onPress={saibaMais} />
      </View>
    </View>
  );
}

const estilos = StyleSheet.create({
  intro: { fontSize: fonte.normal - 1, color: cores.texto, lineHeight: 25 },
  plano: { ...cartao, padding: 16 },
  icone: { width: 64, height: 64, borderRadius: 16, backgroundColor: cores.verdeClaro, alignItems: 'center', justifyContent: 'center' },
  nome: { fontSize: fonte.grande + 2, fontWeight: '900', color: cores.verde },
  sub: { fontSize: fonte.pequena - 1, color: cores.texto, marginTop: 2 },
  item: { flex: 1, fontSize: fonte.pequena, color: cores.texto, lineHeight: 22 },
  nota: { fontSize: 15, color: cores.textoSuave, textAlign: 'center' },
  rodape: { padding: 16, paddingBottom: 24, borderTopWidth: 1, borderColor: cores.borda, backgroundColor: cores.cartao },
});
