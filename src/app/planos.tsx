import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useLocalSearchParams } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { Alert, Linking, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { EMAIL_CONTATO, URL_PLANOS } from '../api/cliente';
import { Cabecalho } from '../componentes/Cabecalho';
import { Botao } from '../componentes/ui';
import { cartao, cores, fonte } from '../tema';

type NomeIcone = keyof typeof MaterialCommunityIcons.glyphMap;

// Iguais aos do site (servidor/publico/planos.php); valores ainda "em breve"
const PLANOS: { chave: string; nome: string; sub: string; icone: NomeIcone; itens: string[] }[] = [
  {
    chave: 'basico',
    nome: 'Básico',
    sub: 'Seu perfil completo e sempre atualizado.',
    icone: 'storefront-outline',
    itens: ['Corrigir nome, telefone, WhatsApp, endereço e horário', 'Colocar fotos', 'Descrição e o que você vende ou faz', 'Selo "Verificado"', 'Responder as avaliações'],
  },
  {
    chave: 'destaque',
    nome: 'Destaque',
    sub: 'Seu comércio em evidência na cidade.',
    icone: 'star-outline',
    itens: ['Tudo o que tem no Básico', 'Aparece no topo da tela inicial', 'Na frente dos outros na área e na busca'],
  },
];

/** "Dê mais destaque ao seu negócio": os dois planos. Com ?id=, as condições abrem já para aquele comércio. */
export default function TelaPlanos() {
  const { id } = useLocalSearchParams<{ id?: string }>();
  const condicoes = () => WebBrowser.openBrowserAsync(`${URL_PLANOS}${id ? `?id=${encodeURIComponent(id)}` : ''}`);
  const pedirInformacoes = () => {
    const assunto = encodeURIComponent('Acha Fácil: quero saber dos planos');
    const corpo = encodeURIComponent(`Olá! Quero saber mais sobre os planos do Acha Fácil.${id ? `\nCódigo do comércio no app: ${id}` : ''}\nNome do comércio:\nWhatsApp:`);
    Linking.openURL(`mailto:${EMAIL_CONTATO}?subject=${assunto}&body=${corpo}`).catch(() =>
      Alert.alert('Não foi possível abrir o e-mail', `Fale com a gente pelo e-mail ${EMAIL_CONTATO}.`),
    );
  };

  return (
    <View style={{ flex: 1 }}>
      <ScrollView contentContainerStyle={{ paddingBottom: 24 }}>
        <Cabecalho voltar titulo="Dê mais destaque ao seu negócio" />
        <View style={{ padding: 16, gap: 14 }}>
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
              <Pressable accessibilityRole="button" onPress={condicoes} style={({ pressed }) => [estilos.condicoes, pressed && { opacity: 0.85 }]}>
                <Text style={estilos.condicoesTexto}>Consultar condições</Text>
                <MaterialCommunityIcons name="chevron-right" size={22} color={cores.verde} />
              </Pressable>
            </View>
          ))}
          <Text style={estilos.nota}>Assumir o perfil do seu comércio e pedir para tirar do app não custam nada.</Text>
        </View>
      </ScrollView>
      <View style={estilos.rodape}>
        <Botao titulo="Solicitar informações" tipo="amarelo" onPress={pedirInformacoes} />
      </View>
    </View>
  );
}

const estilos = StyleSheet.create({
  plano: { ...cartao, padding: 16 },
  icone: { width: 64, height: 64, borderRadius: 16, backgroundColor: cores.verdeClaro, alignItems: 'center', justifyContent: 'center' },
  nome: { fontSize: fonte.grande + 2, fontWeight: '900', color: cores.verde },
  sub: { fontSize: fonte.pequena - 1, color: cores.texto, marginTop: 2 },
  item: { flex: 1, fontSize: fonte.pequena, color: cores.texto, lineHeight: 22 },
  condicoes: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 4, minHeight: 50, borderRadius: 25, borderWidth: 2, borderColor: cores.verde, marginTop: 14 },
  condicoesTexto: { fontSize: fonte.pequena + 1, fontWeight: '900', color: cores.verde },
  nota: { fontSize: 15, color: cores.textoSuave, textAlign: 'center' },
  rodape: { padding: 16, paddingBottom: 24, borderTopWidth: 1, borderColor: cores.borda, backgroundColor: cores.cartao },
});
