import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useLocalSearchParams } from 'expo-router';
import { useEffect, useMemo, useState } from 'react';
import { FlatList, Modal, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';

import { chamar, type ProfissionalResumo } from '../api/cliente';
import { useCidade } from '../cidade';
import { Cabecalho } from '../componentes/Cabecalho';
import { Botao, CartaoProfissional, Carregando, Pilula, Vazio } from '../componentes/ui';
import { cartao, cores, fonte } from '../tema';

interface Filtros {
  categoria: string | null;
  bairro: string | null;
  ordem: 'relevancia' | 'az';
  comTelefone: boolean;
}
const SEM_FILTRO: Filtros = { categoria: null, bairro: null, ordem: 'relevancia', comTelefone: false };

export default function TelaBusca() {
  // pode chegar com um termo pronto (atalhos da tela inicial)
  const { q } = useLocalSearchParams<{ q?: string }>();
  const { cidade } = useCidade();
  const [termo, setTermo] = useState(q ?? '');
  const [itens, setItens] = useState<ProfissionalResumo[] | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const [tipo, setTipo] = useState<string | null>(null);
  const [filtros, setFiltros] = useState<Filtros>(SEM_FILTRO);
  const [abrirFiltros, setAbrirFiltros] = useState(false);

  // busca enquanto digita, esperando a pessoa parar por meio segundo; termo novo zera os filtros
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
          setTipo(null);
          setFiltros(SEM_FILTRO);
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
  // tipos mais comuns nos resultados viram pílulas
  const tipos = useMemo(() => contar((itens ?? []).map((p) => p.tipo)).slice(0, 8), [itens]);
  const lista = useMemo(() => {
    const l = (itens ?? []).filter(
      (p) =>
        (!tipo || p.tipo === tipo) &&
        (!filtros.categoria || p.categoria === filtros.categoria) &&
        (!filtros.bairro || p.bairro === filtros.bairro) &&
        (!filtros.comTelefone || p.temTelefone || p.temWhatsapp),
    );
    return filtros.ordem === 'az' ? [...l].sort((a, b) => a.nome.localeCompare(b.nome, 'pt-BR')) : l;
  }, [itens, tipo, filtros]);
  const nFiltros = Number(!!filtros.categoria) + Number(!!filtros.bairro) + Number(filtros.ordem === 'az') + Number(filtros.comTelefone);

  return (
    <View style={{ flex: 1 }}>
      <Cabecalho voltar />
      <View style={{ paddingHorizontal: 16, paddingTop: 18 }}>
        <Text style={estilos.titulo} numberOfLines={2}>
          {curto ? 'O que você procura?' : `Resultados para "${termo.trim()}"`}
        </Text>
        <Text style={estilos.sub}>
          {curto || itens === null
            ? `Procurando em ${cidade.nome}`
            : `${lista.length >= 50 ? 'Os 50 primeiros' : lista.length} ${lista.length === 1 ? 'comércio encontrado' : 'comércios encontrados'} em ${cidade.nome}`}
        </Text>

        <View style={estilos.linhaBusca}>
          <View style={estilos.campo}>
            <MaterialCommunityIcons name="magnify" size={26} color={cores.texto} />
            <TextInput
              autoFocus={!q}
              value={termo}
              onChangeText={setTermo}
              placeholder="Ex.: pizza, farmácia, borracharia"
              placeholderTextColor={cores.textoSuave}
              returnKeyType="search"
              style={estilos.campoTexto}
              accessibilityLabel="Digite o que você procura"
            />
            {termo.length > 0 && (
              <Pressable accessibilityRole="button" accessibilityLabel="Limpar" hitSlop={10} onPress={() => setTermo('')}>
                <MaterialCommunityIcons name="close" size={24} color={cores.texto} />
              </Pressable>
            )}
          </View>
          <Pressable
            accessibilityRole="button"
            accessibilityLabel={`Filtros${nFiltros ? `, ${nFiltros} ligados` : ''}`}
            disabled={!itens?.length}
            onPress={() => setAbrirFiltros(true)}
            style={({ pressed }) => [estilos.botaoFiltro, (pressed || !itens?.length) && { opacity: 0.6 }]}
          >
            <MaterialCommunityIcons name="filter-outline" size={22} color={cores.verde} />
            <Text style={estilos.botaoFiltroTexto}>Filtros{nFiltros ? ` (${nFiltros})` : ''}</Text>
          </Pressable>
        </View>
      </View>

      {curto ? (
        <Vazio icone="store-search-outline" texto="Digite o nome, o tipo de negócio (pizzaria, oficina...) ou o bairro." />
      ) : erro ? (
        <Vazio icone="wifi-off" texto={erro} />
      ) : itens === null ? (
        <Carregando />
      ) : (
        <FlatList
          data={lista}
          keyExtractor={(p) => String(p.id)}
          contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 32, flexGrow: 1 }}
          keyboardShouldPersistTaps="handled"
          ListHeaderComponent={
            tipos.length > 1 ? (
              <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginHorizontal: -16 }} contentContainerStyle={{ paddingHorizontal: 16, paddingVertical: 14, gap: 8 }}>
                <Pilula texto="Todos" ativo={tipo === null} onPress={() => setTipo(null)} />
                {tipos.map(([nome]) => (
                  <Pilula key={nome} texto={nome} ativo={tipo === nome} onPress={() => setTipo(tipo === nome ? null : nome)} />
                ))}
              </ScrollView>
            ) : (
              <View style={{ height: 14 }} />
            )
          }
          renderItem={({ item }) => <CartaoProfissional p={item} />}
          ListEmptyComponent={<Vazio icone="store-search-outline" texto={nFiltros || tipo ? 'Nada encontrado com esses filtros.' : 'Nada encontrado. Tente outra palavra.'} />}
        />
      )}

      <TelaFiltros
        visivel={abrirFiltros}
        itens={itens ?? []}
        atuais={filtros}
        aoFechar={() => setAbrirFiltros(false)}
        aoAplicar={(f) => {
          setFiltros(f);
          setAbrirFiltros(false);
        }}
      />
    </View>
  );
}

/** ["Pizzaria", "Bar", "Pizzaria"] -> [["Pizzaria", 2], ["Bar", 1]] (sem vazios, do mais comum ao menos). */
function contar(valores: (string | null)[]) {
  const m = new Map<string, number>();
  for (const v of valores) if (v) m.set(v, (m.get(v) ?? 0) + 1);
  return [...m.entries()].sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0], 'pt-BR'));
}

/** "Filtrar resultados": área, bairro, ordem e "com telefone", sobre os resultados que já vieram. */
function TelaFiltros({
  visivel,
  itens,
  atuais,
  aoFechar,
  aoAplicar,
}: {
  visivel: boolean;
  itens: ProfissionalResumo[];
  atuais: Filtros;
  aoFechar: () => void;
  aoAplicar: (f: Filtros) => void;
}) {
  const [f, setF] = useState(atuais);
  const [aberto, setAberto] = useState<'categoria' | 'bairro' | null>(null);
  const categorias = useMemo(() => contar(itens.map((p) => p.categoria)), [itens]);
  const bairros = useMemo(() => contar(itens.map((p) => p.bairro)), [itens]);

  return (
    <Modal visible={visivel} animationType="slide" onRequestClose={aoFechar} onShow={() => setF(atuais)} statusBarTranslucent>
      <View style={{ flex: 1, backgroundColor: cores.fundo }}>
        <Cabecalho voltar aoVoltar={aoFechar} />
        <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 24 }}>
          <Text style={estilos.titulo}>Filtrar resultados</Text>

          <Text style={estilos.rotulo}>Categoria</Text>
          <Escolha
            icone="silverware-fork-knife"
            valor={f.categoria ?? 'Todas as categorias'}
            aberto={aberto === 'categoria'}
            aoAbrir={() => setAberto(aberto === 'categoria' ? null : 'categoria')}
            opcoes={[['Todas as categorias', itens.length] as [string, number], ...categorias]}
            aoEscolher={(v) => {
              setF({ ...f, categoria: v === 'Todas as categorias' ? null : v });
              setAberto(null);
            }}
          />

          <Text style={estilos.rotulo}>Bairro</Text>
          {bairros.length === 0 ? (
            <Text style={estilos.sub}>Estes resultados não informam o bairro.</Text>
          ) : (
            <Escolha
              icone="map-marker-outline"
              valor={f.bairro ?? 'Todos os bairros'}
              aberto={aberto === 'bairro'}
              aoAbrir={() => setAberto(aberto === 'bairro' ? null : 'bairro')}
              opcoes={[['Todos os bairros', itens.length] as [string, number], ...bairros]}
              aoEscolher={(v) => {
                setF({ ...f, bairro: v === 'Todos os bairros' ? null : v });
                setAberto(null);
              }}
            />
          )}

          <Text style={estilos.rotulo}>Ordenar por</Text>
          <View style={estilos.grupo}>
            {(
              [
                ['relevancia', 'Mais relevantes'],
                ['az', 'A a Z'],
              ] as const
            ).map(([valor, texto], i) => {
              const ativo = f.ordem === valor;
              return (
                <Pressable
                  key={valor}
                  accessibilityRole="radio"
                  accessibilityState={{ checked: ativo }}
                  onPress={() => setF({ ...f, ordem: valor })}
                  style={[estilos.opcao, i > 0 && { borderTopWidth: 1, borderColor: cores.borda }, ativo && { backgroundColor: cores.fundoSuave }]}
                >
                  <MaterialCommunityIcons name={ativo ? 'radiobox-marked' : 'radiobox-blank'} size={26} color={ativo ? cores.verde : cores.textoSuave} />
                  <Text style={estilos.opcaoTexto}>{texto}</Text>
                </Pressable>
              );
            })}
          </View>

          <Text style={estilos.rotulo}>Outras opções</Text>
          <Pressable
            accessibilityRole="checkbox"
            accessibilityState={{ checked: f.comTelefone }}
            onPress={() => setF({ ...f, comTelefone: !f.comTelefone })}
            style={[estilos.grupo, estilos.opcao]}
          >
            <MaterialCommunityIcons name={f.comTelefone ? 'checkbox-marked' : 'checkbox-blank-outline'} size={26} color={f.comTelefone ? cores.verde : cores.textoSuave} />
            <Text style={estilos.opcaoTexto}>Com telefone ou WhatsApp</Text>
          </Pressable>
        </ScrollView>

        <View style={estilos.rodape}>
          <Botao titulo="Limpar" tipo="contorno" onPress={() => setF(SEM_FILTRO)} estilo={{ flex: 1 }} />
          <Botao titulo="Ver resultados" tipo="amarelo" onPress={() => aoAplicar(f)} estilo={{ flex: 1.4 }} />
        </View>
      </View>
    </Modal>
  );
}

/** Caixa de escolha que abre a lista logo embaixo (sem janelas por cima). */
function Escolha({
  icone,
  valor,
  aberto,
  aoAbrir,
  opcoes,
  aoEscolher,
}: {
  icone: keyof typeof MaterialCommunityIcons.glyphMap;
  valor: string;
  aberto: boolean;
  aoAbrir: () => void;
  opcoes: [string, number][];
  aoEscolher: (v: string) => void;
}) {
  return (
    <View style={estilos.grupo}>
      <Pressable accessibilityRole="button" accessibilityLabel={valor} accessibilityState={{ expanded: aberto }} onPress={aoAbrir} style={estilos.opcao}>
        <MaterialCommunityIcons name={icone} size={24} color={cores.texto} />
        <Text style={[estilos.opcaoTexto, { flex: 1 }]} numberOfLines={1}>
          {valor}
        </Text>
        <MaterialCommunityIcons name={aberto ? 'chevron-up' : 'chevron-down'} size={26} color={cores.texto} />
      </Pressable>
      {aberto &&
        opcoes.map(([nome, n]) => (
          <Pressable key={nome} accessibilityRole="button" onPress={() => aoEscolher(nome)} style={[estilos.opcao, { borderTopWidth: 1, borderColor: cores.borda, paddingLeft: 52 }]}>
            <Text style={[estilos.opcaoTexto, { flex: 1 }, nome === valor && { color: cores.verde, fontWeight: '900' }]} numberOfLines={1}>
              {nome}
            </Text>
            <Text style={estilos.sub}>{n}</Text>
          </Pressable>
        ))}
    </View>
  );
}

const estilos = StyleSheet.create({
  titulo: { fontSize: 26, fontWeight: '900', color: cores.texto },
  sub: { fontSize: fonte.pequena - 1, color: cores.textoSuave, marginTop: 2 },
  linhaBusca: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 14 },
  campo: { ...cartao, flex: 1, flexDirection: 'row', alignItems: 'center', gap: 8, minHeight: 56, paddingHorizontal: 12 },
  campoTexto: { flex: 1, fontSize: fonte.normal, color: cores.texto, minHeight: 52 },
  botaoFiltro: { flexDirection: 'row', alignItems: 'center', gap: 4, minHeight: 56, paddingHorizontal: 14, borderRadius: 16, backgroundColor: cores.amarelo },
  botaoFiltroTexto: { fontSize: fonte.pequena, fontWeight: '900', color: cores.verde },
  rotulo: { fontSize: fonte.media, fontWeight: '900', color: cores.texto, marginTop: 22, marginBottom: 8 },
  grupo: { ...cartao, overflow: 'hidden' },
  opcao: { flexDirection: 'row', alignItems: 'center', gap: 12, minHeight: 56, paddingHorizontal: 14 },
  opcaoTexto: { fontSize: fonte.normal, color: cores.texto, fontWeight: '600' },
  rodape: { flexDirection: 'row', gap: 12, padding: 16, paddingBottom: 28, borderTopWidth: 1, borderColor: cores.borda, backgroundColor: cores.cartao },
});
