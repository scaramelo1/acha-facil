import { useState } from 'react';
import { Alert, Pressable, RefreshControl, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';

import { chamar } from '../api/cliente';
import { Cabecalho } from '../componentes/Cabecalho';
import { Botao, Carregando, Falha, useCarregar } from '../componentes/ui';
import { useConta } from '../conta';
import { cartao, cores, fonte } from '../tema';

// Notificacoes para os usuarios (so administradores). As mesmas regras do /admin/notificacoes.php do site
// (servidor/inc/notificacoes.php): escolhe a cidade (ou todas), quem recebe e, se quiser, a ficha que o toque abre.

type CidadeNotif = { slug: string; nome: string; uf: string; aparelhos: number; contas: number };
type Envio = {
  id: number;
  titulo: string;
  texto: string;
  cidade: string;
  publico: string;
  comercio: string | null;
  admin: string | null;
  pelo: string;
  quando: string;
  aparelhos: number;
  entregues: number;
  falhas: number;
};
type Dados = { pronto: boolean; total: number; contas: number; cidades: CidadeNotif[]; historico: Envio[] };
type Resultado = { aparelhos: number; entregues: number; falhas: number };

const MAX_TITULO = 60;
const MAX_TEXTO = 178;

export default function TelaNotificar() {
  const { usuario } = useConta();
  const { dados, erro, carregando, recarregar } = useCarregar(() => chamar<Dados>('notificar.php'), 'notificar');
  const [titulo, setTitulo] = useState('');
  const [texto, setTexto] = useState('');
  const [cidade, setCidade] = useState('');
  const [soContas, setSoContas] = useState(false);
  const [comercio, setComercio] = useState('');
  const [enviando, setEnviando] = useState(false);

  if (!usuario?.admin) {
    return (
      <View style={{ flex: 1 }}>
        <Cabecalho voltar titulo="Notificações" />
        <Text style={[estilos.texto, { padding: 16 }]}>Só para administradores.</Text>
      </View>
    );
  }

  const escolhida = dados?.cidades.find((c) => c.slug === cidade);
  const quantos = escolhida ? (soContas ? escolhida.contas : escolhida.aparelhos) : soContas ? (dados?.contas ?? 0) : (dados?.total ?? 0);
  const para = escolhida ? escolhida.nome : 'todas as cidades';

  const mandar = async () => {
    setEnviando(true);
    try {
      const r = await chamar<Resultado>('notificar.php', 'POST', {
        titulo: titulo.trim(),
        texto: texto.trim(),
        cidade,
        publico: soContas ? 'contas' : 'todos',
        profissional: comercio.trim(),
      });
      Alert.alert('Notificação enviada', `Chegou em ${r.entregues} de ${r.aparelhos} aparelho(s).` + (r.falhas ? ` ${r.falhas} falharam (quase sempre app desinstalado).` : ''));
      setTitulo('');
      setTexto('');
      setComercio('');
      recarregar();
    } catch (e) {
      Alert.alert('Não deu certo', e instanceof Error ? e.message : 'Tente de novo.');
    } finally {
      setEnviando(false);
    }
  };

  const confirmar = () => {
    if (titulo.trim().length < 3) return Alert.alert('Falta o título', 'Escreva um título com pelo menos 3 letras.');
    if (texto.trim().length < 3) return Alert.alert('Falta o texto', 'Escreva o texto da notificação.');
    Alert.alert('Enviar agora?', `Vai para ${quantos} aparelho(s) de ${para}. Não dá para desfazer.`, [
      { text: 'Cancelar', style: 'cancel' },
      { text: 'Enviar', onPress: mandar },
    ]);
  };

  return (
    <ScrollView
      keyboardShouldPersistTaps="handled"
      contentContainerStyle={{ paddingBottom: 32 }}
      refreshControl={<RefreshControl refreshing={carregando && !!dados} onRefresh={recarregar} colors={[cores.verde]} />}
    >
      <Cabecalho voltar titulo="Notificações" />
      <View style={{ padding: 16 }}>
        {!dados && carregando ? (
          <Carregando />
        ) : erro && !dados ? (
          <Falha mensagem={erro} tentar={recarregar} />
        ) : dados && !dados.pronto ? (
          <Text style={estilos.texto}>Falta aplicar a mudança 019 no servidor (bash servidor/instalar.sh).</Text>
        ) : dados ? (
          <>
            <Text style={estilos.ajuda}>
              Chega em quem tem o app 1.6.0 ou mais novo e aceitou os avisos. Hoje: {dados.total} aparelho(s), {dados.contas} com conta. Aviso demais faz a
              pessoa desligar ou desinstalar.
            </Text>

            <View style={estilos.cartao}>
              <Text style={estilos.rotulo}>
                Título ({titulo.length}/{MAX_TITULO})
              </Text>
              <TextInput
                value={titulo}
                onChangeText={setTitulo}
                maxLength={MAX_TITULO}
                placeholder="Novidade na sua cidade!"
                placeholderTextColor={cores.textoSuave}
                style={estilos.campo}
              />
              <Text style={estilos.rotulo}>
                Texto ({texto.length}/{MAX_TEXTO})
              </Text>
              <TextInput
                value={texto}
                onChangeText={setTexto}
                maxLength={MAX_TEXTO}
                multiline
                placeholder="Ex.: 200 comércios novos no app. Veja quem está perto de você."
                placeholderTextColor={cores.textoSuave}
                style={[estilos.campo, { minHeight: 90, textAlignVertical: 'top' }]}
              />

              <Text style={estilos.rotulo}>Para qual cidade</Text>
              <View style={estilos.pilulas}>
                <Pilula texto={`Todas (${dados.total})`} ativa={cidade === ''} aoTocar={() => setCidade('')} />
                {dados.cidades.map((c) => (
                  <Pilula key={c.slug} texto={`${c.nome} (${c.aparelhos})`} ativa={cidade === c.slug} aoTocar={() => setCidade(c.slug)} />
                ))}
              </View>

              <Text style={estilos.rotulo}>Quem recebe</Text>
              <View style={estilos.pilulas}>
                <Pilula texto="Todos os aparelhos" ativa={!soContas} aoTocar={() => setSoContas(false)} />
                <Pilula texto="Só quem tem conta" ativa={soContas} aoTocar={() => setSoContas(true)} />
              </View>

              <Text style={estilos.rotulo}>Abrir a ficha de um comércio (opcional)</Text>
              <TextInput
                value={comercio}
                onChangeText={setComercio}
                maxLength={300}
                autoCapitalize="none"
                autoCorrect={false}
                placeholder="Número ou link da ficha no site"
                placeholderTextColor={cores.textoSuave}
                style={estilos.campo}
              />
              <Text style={estilos.sub}>Vazio: tocar na notificação só abre o app.</Text>

              <Botao titulo={enviando ? 'Enviando...' : `Enviar para ${quantos}`} icone="send" desativado={enviando || quantos === 0} onPress={confirmar} estilo={{ marginTop: 16 }} />
            </View>

            <Text style={estilos.secao}>Já enviadas</Text>
            {dados.historico.length === 0 && <Text style={estilos.sub}>Nenhuma ainda.</Text>}
            {dados.historico.map((n) => (
              <View key={n.id} style={estilos.cartao}>
                <Text style={estilos.titulo}>{n.titulo}</Text>
                <Text style={estilos.texto}>{n.texto}</Text>
                <Text style={estilos.sub}>
                  {n.quando} · {n.cidade} · {n.publico}
                </Text>
                <Text style={estilos.sub}>
                  Chegou em {n.entregues} de {n.aparelhos}
                  {n.comercio ? ` · abre ${n.comercio}` : ''} · {n.admin ?? '?'} pelo {n.pelo}
                </Text>
              </View>
            ))}
          </>
        ) : null}
      </View>
    </ScrollView>
  );
}

function Pilula({ texto, ativa, aoTocar }: { texto: string; ativa: boolean; aoTocar: () => void }) {
  return (
    <Pressable
      accessibilityRole="radio"
      accessibilityState={{ selected: ativa }}
      accessibilityLabel={texto}
      onPress={aoTocar}
      style={[estilos.pilula, ativa && { backgroundColor: cores.verde, borderColor: cores.verde }]}
    >
      <Text style={[estilos.pilulaTexto, ativa && { color: '#fff' }]}>{texto}</Text>
    </Pressable>
  );
}

const estilos = StyleSheet.create({
  ajuda: { fontSize: fonte.pequena, color: cores.textoSuave, lineHeight: 22, marginBottom: 12 },
  cartao: { ...cartao, padding: 14, marginBottom: 12 },
  secao: { fontSize: fonte.grande, fontWeight: '900', color: cores.texto, marginTop: 14, marginBottom: 10 },
  rotulo: { fontSize: fonte.pequena, fontWeight: '900', color: cores.texto, marginTop: 12, marginBottom: 6 },
  campo: {
    borderWidth: 1,
    borderColor: cores.borda,
    borderRadius: 12,
    padding: 12,
    fontSize: fonte.pequena,
    color: cores.texto,
    backgroundColor: cores.cartao,
  },
  pilulas: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  pilula: { borderWidth: 1, borderColor: cores.borda, borderRadius: 20, paddingHorizontal: 14, paddingVertical: 10, backgroundColor: cores.cartao },
  pilulaTexto: { fontSize: 15, fontWeight: '800', color: cores.texto },
  titulo: { fontSize: fonte.normal, fontWeight: '900', color: cores.texto },
  texto: { fontSize: fonte.pequena, color: cores.texto, lineHeight: 22 },
  sub: { fontSize: 14, color: cores.textoSuave, marginTop: 4 },
});
