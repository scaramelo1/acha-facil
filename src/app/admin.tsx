import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { useFocusEffect } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useCallback, useState } from 'react';
import { Alert, Linking, RefreshControl, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';

import { chamar, type AlteracaoPendente, type Pendencias } from '../api/cliente';
import { Cabecalho } from '../componentes/Cabecalho';
import { Botao, Carregando, Falha, useCarregar } from '../componentes/ui';
import { useConta } from '../conta';
import { cartao, cores, fonte } from '../tema';

// Administração no app (só e-mails de admin): alterações dos donos para aprovar ou recusar, pedidos novos e cidades
// pedidas. Os avisos no celular abrem esta tela. Pedidos de assumir/tirar do app abrem o /admin do site.
export default function TelaAdmin() {
  const { usuario } = useConta();
  const { dados, erro, carregando, recarregar } = useCarregar(() => chamar<Pendencias>('admin.php'), 'admin');

  // voltando para a tela (ou tocando num aviso com ela aberta): recarrega
  useFocusEffect(
    useCallback(() => {
      recarregar();
    }, [recarregar]),
  );

  if (!usuario?.admin) {
    return (
      <View style={{ flex: 1 }}>
        <Cabecalho voltar titulo="Administração" />
        <Text style={[estilos.texto, { padding: 16 }]}>Só para administradores.</Text>
      </View>
    );
  }

  return (
    <ScrollView
      contentContainerStyle={{ paddingBottom: 32 }}
      refreshControl={<RefreshControl refreshing={carregando && !!dados} onRefresh={recarregar} colors={[cores.verde]} />}
    >
      <Cabecalho voltar titulo="Administração" />
      <View style={{ padding: 16 }}>
        {!dados && carregando ? (
          <Carregando />
        ) : erro && !dados ? (
          <Falha mensagem={erro} tentar={recarregar} />
        ) : dados ? (
          <>
            <Text style={estilos.secao}>Alterações para aprovar ({dados.alteracoes.length})</Text>
            {dados.alteracoes.length === 0 && <Text style={estilos.vazio}>Nada esperando. Tudo em dia!</Text>}
            {dados.alteracoes.map((a) => (
              <CartaoAlteracao key={a.id} a={a} aoDecidir={recarregar} />
            ))}

            <Text style={estilos.secao}>Pedidos de donos ({dados.pedidos.length})</Text>
            {dados.pedidos.length === 0 && <Text style={estilos.vazio}>Nenhum pedido novo.</Text>}
            {dados.pedidos.map((p) => (
              <View key={p.id} style={estilos.cartao}>
                <Text style={estilos.titulo}>
                  {p.tipo === 'remover' ? 'Tirar do app' : p.tipo === 'incluir' ? 'Incluir negócio' : 'Assumir o perfil'}
                  {p.plano ? ` · plano ${p.plano}` : ''}
                  {p.pago ? ' · pago' : ''}
                </Text>
                <Text style={estilos.texto}>{p.comercio ?? 'Negócio novo'}</Text>
                <Text style={estilos.sub}>
                  {p.nome} · {p.telefone} · {p.quando}
                </Text>
                <Botao
                  titulo="Abrir no site"
                  icone="open-in-new"
                  tipo="contorno"
                  onPress={() => WebBrowser.openBrowserAsync(`${dados.site}pedido.php?id=${p.id}`)}
                  estilo={{ marginTop: 10 }}
                />
              </View>
            ))}

            <Text style={estilos.secao}>Quero minha Cidade!</Text>
            {dados.cidades.length === 0 && <Text style={estilos.vazio}>Ninguém pediu ainda.</Text>}
            {dados.cidades.map((c) => (
              <View key={`${c.cidade}-${c.uf ?? ''}`} style={[estilos.cartao, estilos.linha]}>
                <MaterialCommunityIcons name="map-marker-plus" size={26} color={cores.verde} />
                <View style={{ flex: 1 }}>
                  <Text style={estilos.titulo}>
                    {c.cidade}
                    {c.uf ? ` (${c.uf})` : ''}
                  </Text>
                  <Text style={estilos.sub}>Último pedido: {c.ultimo}</Text>
                </View>
                <Text style={estilos.total}>{c.total}</Text>
              </View>
            ))}

            <Botao titulo="Abrir a administração no site" icone="web" tipo="contorno" onPress={() => WebBrowser.openBrowserAsync(dados.site)} estilo={{ marginTop: 16 }} />
          </>
        ) : null}
      </View>
    </ScrollView>
  );
}

/** Uma alteração: como está no app x como vai ficar, com Aprovar e Recusar (recusa pede o motivo). */
function CartaoAlteracao({ a, aoDecidir }: { a: AlteracaoPendente; aoDecidir: () => void }) {
  const [recusando, setRecusando] = useState(false);
  const [motivo, setMotivo] = useState('');
  const [enviando, setEnviando] = useState(false);

  const decidir = async (acao: 'aprovar' | 'recusar') => {
    if (acao === 'recusar' && motivo.trim() === '') return Alert.alert('Falta o motivo', 'Escreva por que a alteração foi recusada. O dono vê no painel.');
    setEnviando(true);
    try {
      await chamar('admin.php', 'POST', { acao, id: a.id, motivo: motivo.trim() });
      aoDecidir();
    } catch (e) {
      Alert.alert('Não deu certo', e instanceof Error ? e.message : 'Tente de novo.');
      setEnviando(false);
    }
  };

  const aprovar = () =>
    Alert.alert('Aprovar e publicar?', `As mudanças de ${a.comercio} vão para o app agora.`, [
      { text: 'Cancelar', style: 'cancel' },
      { text: 'Aprovar', onPress: () => decidir('aprovar') },
    ]);

  return (
    <View style={estilos.cartao}>
      <Text style={estilos.titulo}>{a.comercio}</Text>
      <Text style={estilos.sub}>
        {a.cidade} · pedido por {a.dono ?? '?'} em {a.quando}
      </Text>
      {a.pago && (
        <Text style={estilos.sub}>
          Plano {a.pago.plano ?? ''} pago por {a.pago.nome} · {a.pago.telefone}
        </Text>
      )}

      {a.primeira && (
        <View style={estilos.alerta}>
          <MaterialCommunityIcons name="phone-alert" size={24} color={cores.erro} />
          <View style={{ flex: 1 }}>
            <Text style={estilos.alertaTexto}>
              Primeira mudança deste dono. Antes de aprovar telefone, WhatsApp, endereço ou nome, ligue para o telefone que já está no app e
              confirme que é o responsável.
            </Text>
            {a.conferirTelefone && (
              <Text style={[estilos.alertaTexto, estilos.link]} onPress={() => Linking.openURL(`tel:${a.conferirTelefone?.replace(/\D/g, '')}`)}>
                Ligar para {a.conferirTelefone}
              </Text>
            )}
          </View>
        </View>
      )}

      {a.campos.map((c) => (
        <View key={c.campo} style={estilos.campo}>
          <Text style={estilos.rotulo}>{c.rotulo}</Text>
          <View style={[estilos.valor, estilos.antes]}>
            <Text style={estilos.legenda}>Hoje no app</Text>
            <Valor campo={c.campo} texto={c.antes} />
          </View>
          <View style={[estilos.valor, estilos.depois]}>
            <Text style={estilos.legenda}>Vai ficar</Text>
            <Valor campo={c.campo} texto={c.depois} />
          </View>
        </View>
      ))}

      {recusando ? (
        <>
          <Text style={[estilos.rotulo, { marginTop: 12 }]}>Motivo da recusa (o dono vê)</Text>
          <TextInput
            value={motivo}
            onChangeText={setMotivo}
            maxLength={500}
            multiline
            placeholder="Ex.: o telefone não é do comércio"
            placeholderTextColor={cores.textoSuave}
            style={estilos.motivo}
          />
          <Botao titulo="Recusar" icone="close" tipo="perigo" desativado={enviando} onPress={() => decidir('recusar')} estilo={{ marginTop: 10 }} />
          <Botao titulo="Voltar" tipo="contorno" desativado={enviando} onPress={() => setRecusando(false)} estilo={{ marginTop: 8 }} />
        </>
      ) : (
        <View style={{ gap: 8, marginTop: 12 }}>
          <Botao titulo="Aprovar e publicar" icone="check" desativado={enviando} onPress={aprovar} />
          <Botao titulo="Recusar" icone="close" tipo="perigo" desativado={enviando} onPress={() => setRecusando(true)} />
        </View>
      )}
    </View>
  );
}

function Valor({ campo, texto }: { campo: string; texto: string | null }) {
  if (!texto) return <Text style={[estilos.texto, { color: cores.textoSuave }]}>(vazio)</Text>;
  if (campo === 'foto') return <Image source={texto} style={estilos.foto} contentFit="cover" accessibilityLabel="Foto" />;
  return <Text style={estilos.texto}>{texto}</Text>;
}

const estilos = StyleSheet.create({
  secao: { fontSize: fonte.grande, fontWeight: '900', color: cores.texto, marginTop: 14, marginBottom: 10 },
  vazio: { fontSize: fonte.pequena, color: cores.textoSuave, marginBottom: 8 },
  cartao: { ...cartao, padding: 14, marginBottom: 12 },
  linha: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  titulo: { fontSize: fonte.normal, fontWeight: '900', color: cores.texto },
  texto: { fontSize: fonte.pequena, color: cores.texto, lineHeight: 22 },
  sub: { fontSize: 14, color: cores.textoSuave, marginTop: 2 },
  total: { fontSize: fonte.grande, fontWeight: '900', color: cores.verde },
  alerta: { flexDirection: 'row', gap: 10, backgroundColor: '#FDECEC', borderRadius: 12, padding: 12, marginTop: 10 },
  alertaTexto: { fontSize: 15, color: cores.erro, lineHeight: 21, fontWeight: '700' },
  link: { textDecorationLine: 'underline', marginTop: 6 },
  campo: { marginTop: 12 },
  rotulo: { fontSize: fonte.pequena, fontWeight: '900', color: cores.texto, marginBottom: 6 },
  valor: { borderRadius: 12, padding: 10, marginBottom: 6 },
  antes: { backgroundColor: '#FDF0EF' },
  depois: { backgroundColor: cores.verdeClaro },
  legenda: { fontSize: 13, fontWeight: '800', color: cores.textoSuave, marginBottom: 2 },
  foto: { width: 180, height: 120, borderRadius: 10 },
  motivo: {
    borderWidth: 1,
    borderColor: cores.borda,
    borderRadius: 12,
    padding: 12,
    minHeight: 80,
    fontSize: fonte.pequena,
    color: cores.texto,
    textAlignVertical: 'top',
    backgroundColor: cores.cartao,
  },
});
