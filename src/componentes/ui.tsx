import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import { useCallback, useEffect, useRef, useState, type ComponentProps, type ReactNode } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';

import type { ProfissionalResumo } from '../api/cliente';
import { cores, fonte, TOQUE } from '../tema';

export type NomeIcone = ComponentProps<typeof MaterialCommunityIcons>['name'];

/** Ícone vindo do servidor (nome em texto); usa um genérico se o nome não existir. */
export function Icone({ nome, tamanho = 28, cor = cores.texto }: { nome: string; tamanho?: number; cor?: string }) {
  const valido = nome in MaterialCommunityIcons.glyphMap ? (nome as NomeIcone) : 'briefcase-outline';
  return <MaterialCommunityIcons name={valido} size={tamanho} color={cor} />;
}

/**
 * Carrega dados da API com estado de carregando/erro e função para tentar de novo.
 * `chave` identifica o que está sendo carregado (ex.: `categoria-3`); quando ela muda, carrega de novo.
 */
export function useCarregar<T>(buscar: () => Promise<T>, chave: string) {
  const [dados, setDados] = useState<T | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const [carregando, setCarregando] = useState(true);
  const [versao, setVersao] = useState(0);
  const buscarAtual = useRef(buscar);

  useEffect(() => {
    buscarAtual.current = buscar;
  });

  useEffect(() => {
    let cancelado = false;
    buscarAtual
      .current()
      .then(
        (d) => {
          if (cancelado) return;
          setDados(d);
          setErro(null);
        },
        (e: unknown) => {
          if (!cancelado) setErro(e instanceof Error ? e.message : 'Não foi possível carregar.');
        },
      )
      .finally(() => {
        if (!cancelado) setCarregando(false);
      });
    return () => {
      cancelado = true;
    };
  }, [chave, versao]);

  const recarregar = useCallback(() => {
    setCarregando(true);
    setVersao((v) => v + 1);
  }, []);

  return { dados, setDados, erro, carregando, recarregar };
}

export function Carregando() {
  return (
    <View style={estilos.centro}>
      <ActivityIndicator size="large" color={cores.destaque} />
    </View>
  );
}

export function Falha({ mensagem, tentar }: { mensagem: string; tentar: () => void }) {
  return (
    <View style={estilos.centro}>
      <MaterialCommunityIcons name="wifi-off" size={48} color={cores.textoSuave} />
      <Text style={[estilos.texto, { textAlign: 'center', marginVertical: 16 }]}>{mensagem}</Text>
      <Botao titulo="Tentar de novo" icone="refresh" onPress={tentar} />
    </View>
  );
}

export function Vazio({ icone, texto }: { icone: NomeIcone; texto: string }) {
  return (
    <View style={estilos.centro}>
      <MaterialCommunityIcons name={icone} size={48} color={cores.textoSuave} />
      <Text style={[estilos.texto, { textAlign: 'center', marginTop: 16, color: cores.textoSuave }]}>{texto}</Text>
    </View>
  );
}

type TipoBotao = 'principal' | 'whatsapp' | 'contorno' | 'perigo';

export function Botao({
  titulo,
  icone,
  onPress,
  tipo = 'principal',
  desativado,
  estilo,
}: {
  titulo: string;
  icone?: NomeIcone;
  onPress: () => void;
  tipo?: TipoBotao;
  desativado?: boolean;
  estilo?: StyleProp<ViewStyle>;
}) {
  const fundo = { principal: cores.verde, whatsapp: cores.whatsapp, contorno: cores.fundo, perigo: cores.fundo }[tipo];
  const cor = { principal: '#fff', whatsapp: '#fff', contorno: cores.texto, perigo: cores.erro }[tipo];
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={titulo}
      disabled={desativado}
      onPress={onPress}
      style={({ pressed }) => [
        estilos.botao,
        { backgroundColor: fundo, opacity: desativado ? 0.5 : pressed ? 0.8 : 1 },
        (tipo === 'contorno' || tipo === 'perigo') && { borderWidth: 2, borderColor: tipo === 'perigo' ? cores.erro : cores.texto },
        estilo,
      ]}
    >
      {icone && <MaterialCommunityIcons name={icone} size={26} color={cor} />}
      <Text style={[estilos.botaoTexto, { color: cor }]}>{titulo}</Text>
    </Pressable>
  );
}

export function Estrelas({ nota, tamanho = 20 }: { nota: number; tamanho?: number }) {
  return (
    <View style={{ flexDirection: 'row' }} accessibilityLabel={`Nota ${nota.toFixed(1)} de 5`}>
      {[1, 2, 3, 4, 5].map((i) => (
        <MaterialCommunityIcons
          key={i}
          name={nota >= i ? 'star' : nota >= i - 0.5 ? 'star-half-full' : 'star-outline'}
          size={tamanho}
          color={cores.estrela}
        />
      ))}
    </View>
  );
}

/** Foto do profissional ou, sem foto, o ícone da categoria num círculo. */
export function Avatar({ foto, icone, tamanho = 64 }: { foto: string | null; icone: string; tamanho?: number }) {
  const forma = { width: tamanho, height: tamanho, borderRadius: tamanho / 2 };
  if (foto) return <Image source={foto} style={[forma, { backgroundColor: cores.fundoSuave }]} contentFit="cover" />;
  return (
    <View style={[forma, { backgroundColor: cores.fundoSuave, alignItems: 'center', justifyContent: 'center' }]}>
      <Icone nome={icone} tamanho={tamanho * 0.5} />
    </View>
  );
}

export function SeloVerificado({ compacto }: { compacto?: boolean }) {
  return (
    <View style={estilos.selo} accessibilityLabel="Verificado pelo Acha Fácil">
      <MaterialCommunityIcons name="check-decagram" size={18} color={cores.destaque} />
      <Text style={estilos.seloTexto}>{compacto ? 'Verificado' : 'Verificado pelo Acha Fácil'}</Text>
    </View>
  );
}

export function CartaoProfissional({ p }: { p: ProfissionalResumo }) {
  const router = useRouter();
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`${p.nome}, ${p.categoria}`}
      onPress={() => router.push(`/profissional/${p.id}`)}
      style={({ pressed }) => [estilos.cartao, pressed && { backgroundColor: cores.fundoSuave }]}
    >
      <Avatar foto={p.foto} icone={p.icone} />
      <View style={{ flex: 1, gap: 4 }}>
        <Text style={estilos.cartaoNome}>{p.nome}</Text>
        <Text style={estilos.cartaoSub} numberOfLines={1}>
          {p.categoria}
          {p.bairro ? ` · ${p.bairro}` : ''}
        </Text>
        {p.avaliacoes > 0 ? (
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
            <Estrelas nota={p.nota} tamanho={18} />
            <Text style={estilos.cartaoSub}>({p.avaliacoes})</Text>
          </View>
        ) : null}
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
          {p.verificado && <SeloVerificado compacto />}
          {p.atendeEmCasa && <Etiqueta texto="Vai até você" icone="home-outline" />}
        </View>
      </View>
      <MaterialCommunityIcons name="chevron-right" size={30} color={cores.textoSuave} />
    </Pressable>
  );
}

export function Etiqueta({ texto, icone }: { texto: string; icone?: NomeIcone }) {
  return (
    <View style={estilos.etiqueta}>
      {icone && <MaterialCommunityIcons name={icone} size={16} color={cores.texto} />}
      <Text style={estilos.etiquetaTexto}>{texto}</Text>
    </View>
  );
}

export function Titulo({ children }: { children: ReactNode }) {
  return <Text style={estilos.titulo}>{children}</Text>;
}

export const estilos = StyleSheet.create({
  centro: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24 },
  texto: { fontSize: fonte.normal, color: cores.texto, lineHeight: 26 },
  titulo: { fontSize: fonte.grande, fontWeight: '800', color: cores.texto, marginTop: 24, marginBottom: 12 },
  botao: {
    minHeight: TOQUE,
    borderRadius: 14,
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
  },
  botaoTexto: { fontSize: fonte.media, fontWeight: '700' },
  cartao: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    padding: 14,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: cores.borda,
    backgroundColor: cores.fundo,
    marginBottom: 12,
  },
  cartaoNome: { fontSize: fonte.media, fontWeight: '700', color: cores.texto },
  cartaoSub: { fontSize: fonte.pequena, color: cores.textoSuave },
  selo: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  seloTexto: { fontSize: 14, fontWeight: '700', color: cores.destaque },
  etiqueta: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: cores.fundoSuave,
    borderRadius: 8,
    paddingHorizontal: 8,
    paddingVertical: 2,
  },
  etiquetaTexto: { fontSize: 14, color: cores.texto },
  campo: {
    minHeight: TOQUE,
    borderWidth: 2,
    borderColor: cores.borda,
    borderRadius: 14,
    paddingHorizontal: 16,
    fontSize: fonte.media,
    color: cores.texto,
    backgroundColor: cores.fundo,
  },
  rotulo: { fontSize: fonte.normal, fontWeight: '700', color: cores.texto, marginBottom: 6, marginTop: 16 },
});
