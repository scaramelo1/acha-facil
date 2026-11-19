import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useFocusEffect, useLocalSearchParams, useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useCallback, useRef, useState } from 'react';
import { Alert, Image, Linking, Pressable, ScrollView, Share, StyleSheet, Text, View } from 'react-native';

import { chamar, ORIGENS, reais, registrarContato, type Oferta, type Origem, type Profissional } from '../../api/cliente';
import { Cabecalho } from '../../componentes/Cabecalho';
import { CapaComercio } from '../../componentes/CapaComercio';
import { Horarios } from '../../componentes/Horarios';
import { Trancado } from '../../componentes/Trancado';
import { Botao, Estrelas, Etiqueta, Falha, Carregando, SeloVerificado, estilos as ui, useCarregar } from '../../componentes/ui';
import { useConta } from '../../conta';
import { deveConvidar, fecharConvite, lembrarContato } from '../../pedirAvaliacao';
import { cartao, cores, fonte, RAIO } from '../../tema';

type NomeIcone = keyof typeof MaterialCommunityIcons.glyphMap;

// Aberturas desta tela ja contadas: so a 1a carga conta como visualizacao; recargas (voltar de avaliar,
// entrar na conta) vao com origem 'nenhuma'.
const aberturasContadas = new Set<string>();

export default function TelaProfissional() {
  const { id, origem: origemParam, avaliar: avaliarParam } = useLocalSearchParams<{ id: string; origem?: string; avaliar?: string }>();
  const origem: Origem = ORIGENS.includes(origemParam as Origem) ? (origemParam as Origem) : 'link';
  const [abertura] = useState(() => `${Date.now()}-${Math.random()}`);
  const { usuario } = useConta();
  const router = useRouter();
  const { dados: p, setDados, erro, carregando, recarregar } = useCarregar(
    () => {
      const contar = !aberturasContadas.has(abertura);
      aberturasContadas.add(abertura);
      return chamar<{ profissional: Profissional }>(`profissional.php?id=${id}&origem=${contar ? origem : 'nenhuma'}`).then((r) => r.profissional);
    },
    `${id}-${usuario?.id ?? 0}`,
  );
  // 19/11 (passo 107): convite "Como foi?" (veio do aviso de amanhã ou tocou em WhatsApp/Ligar há 2+ horas)
  const [convite, setConvite] = useState(() => avaliarParam === '1' || deveConvidar(Number(id)));
  // volta da tela de avaliar: atualiza as avaliações (a primeira vez já carregou acima)
  const primeira = useRef(true);
  useFocusEffect(
    useCallback(() => {
      if (primeira.current) primeira.current = false;
      else recarregar();
    }, [recarregar]),
  );

  if (!p)
    return (
      <View style={{ flex: 1 }}>
        <Cabecalho voltar />
        {carregando ? <Carregando /> : <Falha mensagem={erro ?? 'Não foi possível carregar.'} tentar={recarregar} />}
      </View>
    );

  const abrir = (tipo: 'whatsapp' | 'ligar' | 'instagram' | 'mapa' | 'site', link: string) => {
    registrarContato(p.id, tipo);
    if (tipo === 'whatsapp' || tipo === 'ligar') lembrarContato({ id: p.id, nome: p.nome, jaAvaliou: !!p.minhaAvaliacao }).catch(() => {});
    Linking.openURL(link).catch(() => Alert.alert('Não foi possível abrir', 'Confira se o aplicativo está instalado.'));
  };
  // passo 59: o convite ao dono fica só no cartao "Este comércio é seu?", logo abaixo dos espaços trancados
  const temHorario = !!(p.horarios?.length || p.horario);
  // 19/11: "Olha esse lugar" com o link da ficha no site (abre para quem nao tem o app); conta como 'compartilhar'
  const compartilhar = async () => {
    const link = p.linkSite ?? 'https://achafacilapp.com.br/';
    try {
      const r = await Share.share({ message: `Olha esse lugar: ${p.nome}${p.tipo ? ` (${p.tipo})` : ''}, em ${p.cidade}\n${link}` });
      if (r.action === Share.sharedAction) registrarContato(p.id, 'compartilhar');
    } catch {
      // cancelou ou o celular nao deixou: nada a fazer
    }
  };

  return (
    <ScrollView contentContainerStyle={{ paddingBottom: 48 }}>
      <Cabecalho voltar />
      {/* capa: carrossel com as fotos do comércio e o logo num círculo por cima (sem fotos, a cor da área) */}
      <CapaComercio fotos={p.fotos ?? []} logo={p.foto} icone={p.icone} cor={p.cor} nome={p.nome} />

      <View style={{ paddingHorizontal: 16 }}>
        <View style={estilos.cabeca}>
          <View style={{ flex: 1, gap: 4 }}>
            <Text style={estilos.nome}>{p.nome}</Text>
            <Text style={estilos.tipo}>{p.tipo}</Text>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 8 }}>
              {p.verificado && <SeloVerificado compacto />}
              {!p.verificado && !p.destaque && p.donoConfirmado && <Etiqueta texto="Dados confirmados pelo dono" icone="check-circle-outline" />}
              {p.avaliacoes > 0 && (
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                  <Estrelas nota={p.nota} tamanho={18} />
                  <Text style={estilos.sub}>
                    {p.nota.toFixed(1).replace('.', ',')} ({p.avaliacoes})
                  </Text>
                </View>
              )}
            </View>
          </View>
          <View style={{ gap: 8 }}>
            <BotaoSalvar p={p} aoMudar={(f) => setDados({ ...p, favorito: f })} />
            {usuario?.admin && <BotaoEditar id={p.id} aoVoltar={recarregar} />}
          </View>
        </View>

        {p.oferta ? <QuadroOferta oferta={p.oferta} /> : null}

        <View style={estilos.acoes}>
          {p.mapa && <Botao titulo="Como chegar" icone="navigation-variant" tipo="amarelo" onPress={() => abrir('mapa', p.mapa!)} estilo={{ flex: 1, paddingHorizontal: 8 }} />}
          <Botao titulo="Compartilhar" icone="share-variant-outline" tipo="contorno" onPress={compartilhar} estilo={{ flex: 1, paddingHorizontal: 8 }} />
        </View>

        {p.whatsapp && (
          <Pressable accessibilityRole="button" onPress={() => abrir('whatsapp', p.whatsapp!)} style={({ pressed }) => [estilos.whats, pressed && { opacity: 0.88 }]}>
            <MaterialCommunityIcons name="whatsapp" size={28} color="#fff" />
            <Text style={estilos.whatsTexto}>Conversar no WhatsApp</Text>
          </Pressable>
        )}
        {!p.whatsapp && !p.telefone && (
          <View style={estilos.aviso}>
            <MaterialCommunityIcons name="phone-outline" size={24} color={cores.verde} />
            <Text style={[ui.texto, { flex: 1 }]}>Telefone ainda não informado</Text>
          </View>
        )}
        {!p.whatsapp && (
          <Trancado id={p.id} tipo="whatsapp" titulo="WhatsApp" texto="O comércio ainda não informou o WhatsApp." botao="Pedir o WhatsApp ao comércio" />
        )}
        {!p.fotos?.length && (
          <Trancado id={p.id} tipo="fotos" titulo="Fotos" texto="Ainda sem fotos do lugar, dos produtos ou do cardápio." botao="Pedir fotos ao comércio" />
        )}
        {!p.descricao && p.servicos.length === 0 && (
          <Trancado
            id={p.id}
            tipo="sobre"
            titulo="O que faz ou vende"
            texto="O comércio ainda não contou o que vende ou faz."
            botao="Pedir detalhes ao comércio"
          />
        )}

        {p.descricao ? <Text style={[ui.texto, { marginTop: 16 }]}>{p.descricao}</Text> : null}

        {p.servicos.length > 0 && (
          <>
            <Text style={estilos.secao}>O que faz</Text>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
              {p.servicos.map((s) => (
                <View key={s} style={estilos.servico}>
                  <Text style={{ fontSize: fonte.pequena, fontWeight: '700', color: cores.verde }}>{s}</Text>
                </View>
              ))}
            </View>
          </>
        )}

        <View style={estilos.caixa}>
          {p.endereco || p.bairro ? (
            <Linha
              icone="map-marker"
              texto={p.endereco ?? p.bairro ?? ''}
              detalhe={[p.endereco ? p.bairro : null, p.cidade].filter(Boolean).join(' · ')}
              onPress={p.mapa ? () => abrir('mapa', p.mapa!) : undefined}
            />
          ) : null}
          {p.telefoneFormatado ? <Linha icone="phone" texto={p.telefoneFormatado} detalhe={p.telefoneCentral ? 'Central de atendimento da rede' : 'Ligar'} onPress={() => abrir('ligar', `tel:${p.telefone}`)} /> : null}
          {p.instagram ? <Linha icone="instagram" texto="Ver Instagram" onPress={() => abrir('instagram', p.instagram!)} /> : null}
          {p.site ? <Linha icone="web" texto={siteCurto(p.site)} onPress={() => abrir('site', p.site!)} /> : null}
          {p.atendeEmCasa && <Linha icone="home-outline" texto="Vai até você" />}
          {p.horarios?.length ? (
            <Horarios lista={p.horarios} />
          ) : p.horario ? (
            <Linha icone="clock-outline" texto="Horário de funcionamento" detalhe={p.horario} ultima />
          ) : null}
        </View>
        {!temHorario && (
          <Trancado
            id={p.id}
            tipo="horario"
            titulo="Horário de funcionamento"
            texto="O comércio ainda não informou o horário."
            botao="Pedir o horário ao comércio"
          />
        )}

        {p.linkAssumir && (
          <Pressable
            accessibilityRole="button"
            onPress={() => router.push(`/planos?id=${p.id}`)}
            style={({ pressed }) => [estilos.dono, pressed && { opacity: 0.9 }]}
          >
            <MaterialCommunityIcons name="storefront-outline" size={40} color={cores.verde} />
            <View style={{ flex: 1 }}>
              <Text style={estilos.donoTitulo}>Este comércio é seu?</Text>
              <Text style={estilos.donoTexto}>Cadastre-se grátis e corrija o telefone, o WhatsApp, o endereço e o horário.</Text>
            </View>
            <MaterialCommunityIcons name="chevron-right" size={28} color={cores.verde} />
          </Pressable>
        )}

        {convite && !p.minhaAvaliacao && (
          <View style={estilos.convite}>
            <MaterialCommunityIcons name="star-circle" size={36} color={cores.verde} />
            <View style={{ flex: 1 }}>
              <Text style={estilos.donoTitulo}>Como foi no {p.nome}?</Text>
              <Text style={estilos.donoTexto}>Dê sua nota em estrelas. Ajuda quem procura em {p.cidade}.</Text>
              <Pressable
                accessibilityRole="button"
                onPress={() => {
                  fecharConvite(p.id);
                  setConvite(false);
                  router.push(usuario ? `/avaliar/${p.id}` : '/entrar');
                }}
                style={({ pressed }) => [estilos.avaliar, { alignSelf: 'flex-start', marginTop: 10 }, pressed && { opacity: 0.85 }]}
              >
                <Text style={estilos.avaliarTexto}>{usuario ? 'Avaliar agora' : 'Entrar para avaliar'}</Text>
              </Pressable>
            </View>
            <Pressable
              accessibilityRole="button"
              accessibilityLabel="Fechar"
              hitSlop={10}
              onPress={() => {
                fecharConvite(p.id);
                setConvite(false);
              }}
            >
              <MaterialCommunityIcons name="close" size={22} color={cores.textoSuave} />
            </Pressable>
          </View>
        )}

        <View style={estilos.avalTopo}>
          <MaterialCommunityIcons name="star-outline" size={26} color={cores.texto} />
          <Text style={[estilos.secao, { flex: 1, marginTop: 0 }]}>Avaliações</Text>
          <Pressable
            accessibilityRole="button"
            onPress={() => router.push(usuario ? `/avaliar/${p.id}` : '/entrar')}
            style={({ pressed }) => [estilos.avaliar, pressed && { opacity: 0.85 }]}
          >
            <Text style={estilos.avaliarTexto}>{!usuario ? 'Entrar para avaliar' : p.minhaAvaliacao ? 'Mudar avaliação' : 'Avaliar'}</Text>
          </Pressable>
        </View>
        {p.comentarios.length === 0 && <Text style={estilos.sub}>{p.avaliacoes > 0 ? 'Ainda não há comentários.' : 'Ainda não há avaliações.'}</Text>}
        {p.comentarios.map((c) => (
          <View key={c.id} style={estilos.comentario}>
            <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
              <Text style={[ui.texto, { fontWeight: '800' }]}>{c.autor}</Text>
              <Text style={estilos.sub}>{c.data}</Text>
            </View>
            <Estrelas nota={c.estrelas} tamanho={18} />
            {c.comentario ? <Text style={ui.texto}>{c.comentario}</Text> : null}
            {c.resposta ? (
              <View style={estilos.resposta}>
                <Text style={[estilos.sub, { fontWeight: '800' }]}>Resposta do comércio</Text>
                <Text style={ui.texto}>{c.resposta}</Text>
              </View>
            ) : null}
          </View>
        ))}

        {p.linkRemover && (
          <Text style={estilos.fonte}>
            Informações públicas do comércio.{' '}
            <Text style={{ textDecorationLine: 'underline' }} onPress={() => WebBrowser.openBrowserAsync(p.linkRemover!)}>
              Algo errado ou quer tirar do app?
            </Text>
          </Text>
        )}
      </View>
    </ScrollView>
  );
}

/** Oferta da semana (so Destaque): titulo, detalhes, preco de/por, foto e ate quando vale. */
function QuadroOferta({ oferta: o }: { oferta: Oferta }) {
  return (
    <View style={estilos.oferta}>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
        <MaterialCommunityIcons name="tag-outline" size={20} color={cores.verde} />
        <Text style={estilos.ofertaSelo}>Oferta da semana</Text>
      </View>
      {o.foto ? <Image source={{ uri: o.foto }} style={estilos.ofertaFoto} resizeMode="cover" accessibilityIgnoresInvertColors /> : null}
      <Text style={estilos.ofertaTitulo}>{o.titulo}</Text>
      {o.texto ? <Text style={ui.texto}>{o.texto}</Text> : null}
      {o.precoPor != null && (
        <View style={{ flexDirection: 'row', alignItems: 'baseline', gap: 10, marginTop: 4 }}>
          {o.precoDe != null && <Text style={estilos.ofertaDe}>{reais(o.precoDe)}</Text>}
          <Text style={estilos.ofertaPor}>{reais(o.precoPor)}</Text>
        </View>
      )}
    </View>
  );
}

/** "https://www.exemplo.com.br/pagina" -> "exemplo.com.br" */
function siteCurto(url: string) {
  return url.replace(/^https?:\/\/(www\.)?/, '').replace(/\/.*$/, '');
}

function Linha({ icone, texto, detalhe, onPress, ultima }: { icone: NomeIcone; texto: string; detalhe?: string; onPress?: () => void; ultima?: boolean }) {
  const conteudo = (
    <>
      <MaterialCommunityIcons name={icone} size={26} color={cores.verde} />
      <View style={{ flex: 1 }}>
        <Text style={estilos.linhaTexto}>{texto}</Text>
        {detalhe ? <Text style={estilos.sub}>{detalhe}</Text> : null}
      </View>
      {onPress && <MaterialCommunityIcons name="chevron-right" size={26} color={cores.texto} />}
    </>
  );
  const estilo = [estilos.linha, ultima && { borderBottomWidth: 0 }];
  return onPress ? (
    <Pressable accessibilityRole="link" onPress={onPress} style={estilo}>
      {conteudo}
    </Pressable>
  ) : (
    <View style={estilo}>{conteudo}</View>
  );
}

// Lapis de editar (so administradores): abre /admin/comercio.php no navegador ja com a conta entrada (link de uso unico).
// O que o admin salva vai direto para o app; ao voltar, a ficha recarrega.
function BotaoEditar({ id, aoVoltar }: { id: number; aoVoltar: () => void }) {
  const [abrindo, setAbrindo] = useState(false);
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel="Editar este comércio (administração)"
      disabled={abrindo}
      style={({ pressed }) => [estilos.salvar, estilos.editar, (pressed || abrindo) && { opacity: 0.7 }]}
      onPress={async () => {
        setAbrindo(true);
        try {
          const r = await chamar<{ url: string }>('admin.php', 'POST', { acao: 'link_editar', id });
          await WebBrowser.openBrowserAsync(r.url);
          aoVoltar();
        } catch (e) {
          Alert.alert('Não foi possível abrir a edição', e instanceof Error ? e.message : '');
        } finally {
          setAbrindo(false);
        }
      }}
    >
      <MaterialCommunityIcons name="pencil" size={26} color={cores.verde} />
    </Pressable>
  );
}

function BotaoSalvar({ p, aoMudar }: { p: Profissional; aoMudar: (f: boolean) => void }) {
  const { usuario } = useConta();
  const router = useRouter();
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={p.favorito ? 'Tirar dos favoritos' : 'Guardar nos favoritos'}
      style={({ pressed }) => [estilos.salvar, pressed && { opacity: 0.85 }]}
      onPress={async () => {
        if (!usuario) return router.push('/entrar');
        const novo = !p.favorito;
        aoMudar(novo);
        try {
          await chamar('favoritos.php', 'POST', { id: p.id, salvar: novo });
        } catch (e) {
          aoMudar(!novo);
          Alert.alert('Não foi possível salvar', e instanceof Error ? e.message : '');
        }
      }}
    >
      <MaterialCommunityIcons name={p.favorito ? 'heart' : 'heart-outline'} size={30} color={cores.verde} />
    </Pressable>
  );
}

const estilos = StyleSheet.create({
  cabeca: { flexDirection: 'row', alignItems: 'flex-start', gap: 12, paddingTop: 10 },
  nome: { fontSize: 28, fontWeight: '900', color: cores.texto, lineHeight: 33 },
  tipo: { fontSize: fonte.normal, color: cores.textoSuave },
  sub: { fontSize: fonte.pequena - 1, color: cores.textoSuave },
  salvar: { width: 56, height: 56, borderRadius: 16, borderWidth: 1.5, borderColor: cores.borda, backgroundColor: cores.cartao, alignItems: 'center', justifyContent: 'center' },
  editar: { backgroundColor: cores.amarelo, borderColor: cores.amarelo },
  acoes: { flexDirection: 'row', gap: 10, marginTop: 16 },
  oferta: { backgroundColor: cores.fundoSuave, borderWidth: 2, borderColor: cores.amarelo, borderRadius: RAIO, padding: 14, marginTop: 16, gap: 6 },
  ofertaSelo: { fontSize: fonte.pequena - 1, fontWeight: '900', color: cores.verde },
  ofertaFoto: { width: '100%', maxWidth: 360, aspectRatio: 1, alignSelf: 'center', borderRadius: 14 }, // 19/11: foto da oferta sempre quadrada (800 x 800)
  ofertaTitulo: { fontSize: fonte.media, fontWeight: '900', color: cores.texto },
  ofertaDe: { fontSize: fonte.normal - 1, color: cores.textoSuave, textDecorationLine: 'line-through' },
  ofertaPor: { fontSize: fonte.grande, fontWeight: '900', color: cores.verde },
  whats: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 12, minHeight: 56, borderRadius: 28, backgroundColor: cores.whatsapp, marginTop: 12 },
  whatsTexto: { color: '#fff', fontSize: fonte.media, fontWeight: '800' },
  aviso: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: cores.verdeClaro, borderRadius: 14, paddingHorizontal: 14, minHeight: 56, marginTop: 12 },
  secao: { fontSize: fonte.grande, fontWeight: '900', color: cores.texto, marginTop: 22, marginBottom: 10 },
  servico: { backgroundColor: cores.fundoSuave, borderRadius: 12, paddingHorizontal: 12, paddingVertical: 6 },
  caixa: { ...cartao, paddingHorizontal: 14, marginTop: 14 },
  linha: { flexDirection: 'row', alignItems: 'center', gap: 14, minHeight: 64, paddingVertical: 8, borderBottomWidth: 1, borderColor: cores.borda },
  linhaTexto: { fontSize: fonte.normal - 1, fontWeight: '800', color: cores.texto },
  dono: { flexDirection: 'row', alignItems: 'center', gap: 14, backgroundColor: cores.amarelo, borderRadius: RAIO, padding: 16, marginTop: 16 },
  donoTitulo: { fontSize: fonte.media, fontWeight: '900', color: cores.verde },
  donoTexto: { fontSize: fonte.pequena - 1, color: cores.verde },
  avalTopo: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 24, marginBottom: 8 },
  convite: { flexDirection: 'row', alignItems: 'flex-start', gap: 12, backgroundColor: cores.fundoSuave, borderRadius: 18, padding: 14, marginTop: 18 },
  avaliar: { backgroundColor: cores.amarelo, borderRadius: 22, paddingHorizontal: 16, minHeight: 44, justifyContent: 'center' },
  avaliarTexto: { color: cores.verde, fontSize: fonte.pequena, fontWeight: '900' },
  fonte: { fontSize: 14, color: cores.textoSuave, textAlign: 'center', marginTop: 28 },
  comentario: { ...cartao, padding: 14, gap: 6, marginTop: 10 },
  resposta: { backgroundColor: cores.fundoSuave, borderRadius: 12, padding: 10, marginTop: 4 },
});
