import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useEffect, useState } from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';

import { chamar, URL_MEU_NEGOCIO, type ProfissionalResumo } from '../api/cliente';
import { useCidade } from '../cidade';
import { Cabecalho } from '../componentes/Cabecalho';
import { Botao, Carregando, Foto, Vazio } from '../componentes/ui';
import { cartao, cores, fonte } from '../tema';

/**
 * "Procurar meu negócio": o dono acha o comércio dele na cidade escolhida, marca e toca em
 * "Sou responsável por este negócio", que leva à tela "Este comércio é seu?" (e dali ao site, onde ele assume o perfil).
 */
export default function TelaMeuNegocio() {
  const router = useRouter();
  const { cidade } = useCidade();
  const [termo, setTermo] = useState('');
  const [itens, setItens] = useState<ProfissionalResumo[] | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const [escolhido, setEscolhido] = useState<ProfissionalResumo | null>(null);

  useEffect(() => {
    const t = termo.trim();
    if (t.length < 2) return;
    let cancelado = false;
    const espera = setTimeout(async () => {
      try {
        const r = await chamar<{ profissionais: ProfissionalResumo[] }>(`busca.php?q=${encodeURIComponent(t)}`);
        if (!cancelado) {
          setItens(r.profissionais);
          setErro(null);
          setEscolhido(null);
        }
      } catch (e) {
        if (!cancelado) setErro(e instanceof Error ? e.message : 'Não foi possível buscar.');
      }
    }, 450);
    return () => {
      cancelado = true;
      clearTimeout(espera);
    };
  }, [termo, cidade.slug]);

  const curto = termo.trim().length < 2;

  return (
    <View style={{ flex: 1 }}>
      <FlatList
        data={curto ? [] : (itens ?? [])}
        keyExtractor={(p) => String(p.id)}
        contentContainerStyle={{ paddingBottom: 24, flexGrow: 1 }}
        keyboardShouldPersistTaps="handled"
        ListHeaderComponent={
          <View>
            <Cabecalho voltar titulo="Seu comércio pode estar aqui" busca={{ texto: 'Buscar pelo nome do comércio', valor: termo, aoMudar: setTermo, autoFocus: true }} />
            {!curto && itens && itens.length > 0 && <Text style={estilos.secao}>Resultados da busca</Text>}
          </View>
        }
        renderItem={({ item: p }) => {
          const marcado = escolhido?.id === p.id;
          return (
            <Pressable
              accessibilityRole="radio"
              accessibilityState={{ checked: marcado }}
              accessibilityLabel={`${p.nome}, ${p.tipo}`}
              onPress={() => setEscolhido(marcado ? null : p)}
              style={[estilos.cartao, marcado && estilos.marcado]}
            >
              <Foto foto={p.foto} icone={p.icone} cor={p.cor} largura={104} altura={80} raio={0} />
              <View style={{ flex: 1 }}>
                <Text style={estilos.nome} numberOfLines={2}>
                  {p.nome}
                </Text>
                <Text style={estilos.tipo} numberOfLines={1}>
                  {p.tipo}
                  {p.bairro ? ` · ${p.bairro}` : ''}
                </Text>
                {p.cadastrado && <Text style={estilos.ja}>Já tem responsável</Text>}
              </View>
              <MaterialCommunityIcons
                name={marcado ? 'check-circle' : 'chevron-right'}
                size={marcado ? 30 : 26}
                color={marcado ? cores.verde : cores.texto}
                style={{ marginRight: 10 }}
              />
            </Pressable>
          );
        }}
        ListEmptyComponent={
          curto ? (
            <Vazio icone="storefront-outline" texto={`Digite o nome do seu comércio em ${cidade.nome}.`} />
          ) : erro ? (
            <Vazio icone="wifi-off" texto={erro} />
          ) : itens === null ? (
            <Carregando />
          ) : (
            <Vazio icone="store-search-outline" texto="Não achamos com esse nome. Tente só uma parte do nome." />
          )
        }
        ListFooterComponent={
          <Pressable accessibilityRole="link" onPress={() => WebBrowser.openBrowserAsync(URL_MEU_NEGOCIO)} style={estilos.naoAchei} hitSlop={8}>
            <Text style={estilos.link}>Não encontrei meu comércio</Text>
            <MaterialCommunityIcons name="chevron-right" size={22} color={cores.verde} />
          </Pressable>
        }
      />
      <View style={estilos.rodape}>
        <Botao
          titulo="Sou responsável por este negócio"
          tipo="amarelo"
          desativado={!escolhido || escolhido.cadastrado}
          onPress={() => escolhido && router.push(`/planos?id=${escolhido.id}`)}
        />
        {!escolhido && <Text style={estilos.dica}>Toque no seu comércio na lista para escolher.</Text>}
      </View>
    </View>
  );
}

const estilos = StyleSheet.create({
  secao: { fontSize: fonte.grande, fontWeight: '900', color: cores.texto, marginTop: 20, marginBottom: 12, marginHorizontal: 16 },
  cartao: { ...cartao, flexDirection: 'row', alignItems: 'center', gap: 12, marginHorizontal: 16, marginBottom: 12, overflow: 'hidden', borderWidth: 2 },
  marcado: { borderColor: cores.verde, backgroundColor: cores.verdeClaro },
  nome: { fontSize: fonte.normal + 1, fontWeight: '900', color: cores.texto },
  tipo: { fontSize: fonte.pequena - 1, color: cores.textoSuave, marginTop: 2 },
  ja: { fontSize: 14, fontWeight: '800', color: cores.destaque, marginTop: 2 },
  naoAchei: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', minHeight: 48, marginTop: 8 },
  link: { color: cores.verde, fontSize: fonte.pequena + 1, fontWeight: '800', textDecorationLine: 'underline' },
  rodape: { padding: 16, paddingBottom: 24, borderTopWidth: 1, borderColor: cores.borda, backgroundColor: cores.cartao },
  dica: { fontSize: 14, color: cores.textoSuave, textAlign: 'center', marginTop: 8 },
});
