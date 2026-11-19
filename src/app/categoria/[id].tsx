import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { FlatList, ScrollView, Text, View } from 'react-native';

import { chamar, type ProfissionalResumo, type TipoNegocio } from '../../api/cliente';
import { useCidade } from '../../cidade';
import { Cabecalho } from '../../componentes/Cabecalho';
import { AVISO_ABERTO, CartaoProfissional, Carregando, Falha, Pilula, Vazio, useCarregar } from '../../componentes/ui';
import { cores } from '../../tema';

interface Resposta {
  categoria: { id: number; nome: string; icone: string; cor: string };
  tipos: TipoNegocio[];
  profissionais: ProfissionalResumo[];
}

/** Sem acento e em minúsculas, para a busca dentro da área achar "cafe" em "Café". */
function simples(t: string) {
  return t.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
}

// Uma área (ex.: Alimentação): cabeçalho com o nome e quantos lugares, busca amarela dentro da área e filtros pelo tipo
export default function TelaCategoria() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { cidade } = useCidade();
  const { dados, erro, carregando, recarregar } = useCarregar(() => chamar<Resposta>(`categoria.php?id=${id}`), `categoria-${id}-${cidade.slug}`);
  const [tipo, setTipo] = useState<string | null>(null);
  const [termo, setTermo] = useState('');
  const [aberto, setAberto] = useState(false);

  const c = dados?.categoria;
  const t = simples(termo.trim());
  const lista = (dados?.profissionais ?? []).filter(
    (p) => (!tipo || p.tipo === tipo) && (!aberto || p.abertoAgora === true) && (!t || simples(`${p.nome} ${p.tipo} ${p.bairro ?? ''}`).includes(t)),
  );
  // só mostra filtros que valem a pena (tipos com pelo menos 2 lugares)
  const tipos = (dados?.tipos ?? []).filter((x) => x.total >= 2);
  const total = dados?.profissionais.length ?? 0;

  return (
    <FlatList
      data={lista}
      keyExtractor={(p) => String(p.id)}
      contentContainerStyle={{ paddingBottom: 40, flexGrow: 1 }}
      keyboardShouldPersistTaps="handled"
      refreshing={carregando && !!dados}
      onRefresh={recarregar}
      initialNumToRender={12}
      windowSize={7}
      ListHeaderComponent={
        <View style={{ marginBottom: 14 }}>
          <Cabecalho
            voltar
            titulo={c?.nome ?? ' '}
            subtitulo={dados ? `${total.toLocaleString('pt-BR')} ${total === 1 ? 'lugar' : 'lugares'} em ${cidade.curto}` : undefined}
            busca={{ texto: 'Buscar comércios ou serviços', valor: termo, aoMudar: setTermo }}
          />
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ paddingHorizontal: 16, paddingTop: 16, gap: 8 }}>
            <Pilula texto="Aberto agora" ativo={aberto} onPress={() => setAberto(!aberto)} />
            {tipos.length > 1 && <Pilula texto="Todos" ativo={tipo === null} onPress={() => setTipo(null)} />}
            {tipos.length > 1 &&
              tipos.map((x) => <Pilula key={x.nome} texto={x.nome} ativo={tipo === x.nome} onPress={() => setTipo(tipo === x.nome ? null : x.nome)} />)}
          </ScrollView>
          {aberto && <Text style={{ paddingHorizontal: 16, paddingTop: 10, fontSize: 15, color: cores.textoSuave }}>{AVISO_ABERTO}</Text>}
        </View>
      }
      renderItem={({ item }) => (
        <View style={{ paddingHorizontal: 16 }}>
          <CartaoProfissional p={item} origem="categoria" />
        </View>
      )}
      ListEmptyComponent={
        carregando && !dados ? (
          <Carregando />
        ) : erro && !dados ? (
          <Falha mensagem={erro} tentar={recarregar} />
        ) : (
          <Vazio
            icone="store-search-outline"
            texto={aberto ? 'Nenhum lugar com horário informado está aberto agora.' : termo || tipo ? 'Nada encontrado com esse filtro.' : 'Ainda não há lugares nesta área. Volte em breve!'}
          />
        )
      }
    />
  );
}
