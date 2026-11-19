import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';

import { chamar, pedirInfo, type TipoPedidoInfo } from '../api/cliente';
import { Armazenamento } from '../armazenamento';
import { ligarAvisos } from '../avisos';
import { useConta } from '../conta';
import { cores, fonte } from '../tema';

const TEXTO_AVISO: Record<TipoPedidoInfo, string> = {
  horario: 'o horário',
  fotos: 'fotos',
  sobre: 'o que vende ou faz',
  whatsapp: 'o WhatsApp',
};

/**
 * Espaço trancado na ficha incompleta (passo 59): mostra o que falta e o botão "Pedir ... ao comércio" (antes "Quero saber...", trocado em 17/11).
 * O pedido é contado no servidor (api/quero.php) e vira argumento para o dono completar a ficha.
 * Passo 65: depois do pedido, pergunta se a pessoa quer ser avisada quando a informação entrar
 * (precisa da conta; sem conta, vai para Entrar e o pedido de aviso é feito ao voltar).
 * O celular lembra quem já pediu, para não mostrar o botão de novo.
 */
export function Trancado({ id, tipo, titulo, texto, botao }: { id: number; tipo: TipoPedidoInfo; titulo: string; texto: string; botao: string }) {
  const { usuario } = useConta();
  const router = useRouter();
  const chave = `quero_${id}_${tipo}`;
  const chaveAviso = `avisar_${id}_${tipo}`;
  const chavePendente = `avisar_pendente_${id}_${tipo}`;
  const [feito, setFeito] = useState(() => Armazenamento.ler(chave) === '1');
  const [avisado, setAvisado] = useState(() => Armazenamento.ler(chaveAviso) === '1');
  const [perguntar, setPerguntar] = useState(false);
  const [enviando, setEnviando] = useState(false);

  const pedir = () => {
    setFeito(true);
    setPerguntar(!avisado);
    Armazenamento.gravar(chave, '1');
    pedirInfo(id, tipo);
  };

  // aviso gravado no servidor: lembra no celular e liga as notificações
  const marcarAvisado = () => {
    Armazenamento.gravar(chaveAviso, '1');
    Armazenamento.apagar(chavePendente);
    setAvisado(true);
    setPerguntar(false);
    ligarAvisos().catch(() => {});
  };

  const pedirAviso = async () => {
    if (!usuario) {
      // entra na conta e volta: o aviso é pedido sozinho (useEffect abaixo)
      Armazenamento.gravar(chavePendente, '1');
      router.push('/entrar');
      return;
    }
    setEnviando(true);
    try {
      await chamar('avisar-info.php', 'POST', { id, tipo });
      marcarAvisado();
    } catch {
      Alert.alert('Não deu certo agora', 'Tente de novo daqui a pouco.');
    } finally {
      setEnviando(false);
    }
  };

  // voltou da tela Entrar já com a conta: faz o pedido de aviso que ficou guardado
  // (o estado só muda na resposta do servidor, nunca direto no efeito)
  useEffect(() => {
    if (!usuario || avisado || Armazenamento.ler(chavePendente) !== '1') return;
    let ativo = true;
    chamar('avisar-info.php', 'POST', { id, tipo })
      .then(() => {
        if (!ativo) return;
        Armazenamento.gravar(chaveAviso, '1');
        Armazenamento.apagar(chavePendente);
        setAvisado(true);
        setPerguntar(false);
        ligarAvisos().catch(() => {});
      })
      .catch(() => {});
    return () => {
      ativo = false;
    };
  }, [usuario, avisado, chavePendente, chaveAviso, id, tipo]);

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
        <>
          <Pressable accessibilityRole="button" onPress={pedir} style={({ pressed }) => [estilos.botao, pressed && { opacity: 0.85 }]}>
            <MaterialCommunityIcons name="hand-wave-outline" size={20} color={cores.verde} />
            <Text style={estilos.botaoTexto}>{botao}</Text>
          </Pressable>
          <Text style={estilos.nota}>Vamos avisar o dono que você quer essa informação.</Text>
        </>
      )}
      {feito && avisado && (
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
          <MaterialCommunityIcons name="bell-ring-outline" size={20} color={cores.verde} />
          <Text style={[estilos.ok, { flex: 1 }]}>Combinado! Você recebe um aviso quando o comércio colocar {TEXTO_AVISO[tipo]}.</Text>
        </View>
      )}
      {feito && !avisado && perguntar && (
        <View style={estilos.pergunta}>
          {/* 19/11: convite mais claro (a vantagem e "1 toque, sem senha") */}
          <Text style={estilos.perguntaTexto}>Quer saber quando colocarem {TEXTO_AVISO[tipo]}?</Text>
          <Text style={estilos.texto}>
            {usuario ? 'A gente te avisa no celular assim que a informação entrar.' : 'Entre com o Google e receba o aviso no celular. É 1 toque, sem senha.'}
          </Text>
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
            <Pressable
              accessibilityRole="button"
              disabled={enviando}
              onPress={pedirAviso}
              style={({ pressed }) => [estilos.botaoSim, (pressed || enviando) && { opacity: 0.8 }]}
            >
              <MaterialCommunityIcons name="bell-outline" size={18} color={cores.verde} />
              <Text style={estilos.botaoTexto}>{usuario ? 'Sim, me avise' : 'Entrar e me avisar'}</Text>
            </Pressable>
            <Pressable accessibilityRole="button" onPress={() => setPerguntar(false)} style={({ pressed }) => [estilos.botao, pressed && { opacity: 0.85 }]}>
              <Text style={estilos.botaoTexto}>Agora não</Text>
            </Pressable>
          </View>
        </View>
      )}
    </View>
  );
}

const estilos = StyleSheet.create({
  caixa: { borderWidth: 2, borderStyle: 'dashed', borderColor: cores.borda, borderRadius: 16, padding: 14, gap: 8, marginTop: 12 },
  titulo: { fontSize: fonte.normal, fontWeight: '900', color: cores.texto },
  texto: { fontSize: fonte.pequena, color: cores.textoSuave },
  nota: { fontSize: fonte.pequena - 1, color: cores.textoSuave },
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
  botaoSim: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    minHeight: 48,
    borderRadius: 24,
    backgroundColor: cores.amarelo,
    paddingHorizontal: 16,
  },
  botaoTexto: { fontSize: fonte.pequena, fontWeight: '900', color: cores.verde },
  ok: { fontSize: fonte.pequena, fontWeight: '700', color: cores.verde },
  pergunta: { backgroundColor: cores.fundoSuave, borderRadius: 14, padding: 12, gap: 8 },
  perguntaTexto: { fontSize: fonte.pequena, fontWeight: '800', color: cores.texto },
});
