# Capturas de tela da Play Store do Acha Fácil, desenhadas a partir do código das telas (src/app, src/componentes,
# src/tema.ts) com os dados reais da API (São João da Boa Vista). Não há emulador nem navegador na VPS, então as
# medidas (dp) e cores são as mesmas do app e o desenho é feito com o Pillow.
#
# Uso: python3 assets/loja/telas.py <pasta com fontes e json> <pasta de saída>
#   fontes: roboto-400/500/700/900.ttf, roboto-900i.woff, mdi.ttf, mdi.json (MaterialCommunityIcons)
#   dados:  inicio.json, perto-restaurantes.json (respostas de api/inicio.php e api/perto.php)
import json
import os
import sys

from PIL import Image, ImageDraw, ImageFilter, ImageFont

RAIZ = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
ENT, SAI = sys.argv[1], sys.argv[2]
os.makedirs(SAI, exist_ok=True)

C = dict(fundo='#F7F8F4', cartao='#FFFFFF', fundoSuave='#FFF6D1', texto='#123222', textoSuave='#5B675F',
         borda='#E2E6DF', verde='#005C38', verdeEscuro='#073D25', destaque='#00804F', verdeClaro='#E6F3EA',
         amarelo='#F7C600', whatsapp='#1C9A3C', estrela='#E8A400')

GLIFOS = json.load(open(os.path.join(ENT, 'mdi.json')))
_fontes = {}


def fonte(peso, tam):
    arq = {400: 'roboto-400.ttf', 500: 'roboto-500.ttf', 600: 'roboto-500.ttf', 700: 'roboto-700.ttf',
           800: 'roboto-700.ttf', 900: 'roboto-900.ttf', 'i': 'roboto-900i.woff', 'mdi': 'mdi.ttf'}[peso]
    k = (arq, tam)
    if k not in _fontes:
        _fontes[k] = ImageFont.truetype(os.path.join(ENT, arq), tam)
    return _fontes[k]


def rgba(cor, alfa=255):
    cor = cor.lstrip('#')
    return tuple(int(cor[i:i + 2], 16) for i in (0, 2, 4)) + (alfa,)


def cv(cor):
    return tuple(cor) if isinstance(cor, tuple) else rgba(cor)


class Tela:
    """Desenha em dp (como o React Native); s = pixels por dp."""

    def __init__(self, larg, alt, s):
        self.W, self.H, self.s = larg, alt, s
        self.img = Image.new('RGBA', (round(larg * s), round(alt * s)), rgba(C['fundo']))
        self.d = ImageDraw.Draw(self.img)

    def p(self, v):
        return round(v * self.s)

    def caixa(self, x, y, w, h, r=0, cor=None, borda=None, lb=1, cantos=None, sombra=False):
        if sombra:
            cam = Image.new('RGBA', self.img.size, (0, 0, 0, 0))
            ImageDraw.Draw(cam).rounded_rectangle([self.p(x), self.p(y + 2), self.p(x + w), self.p(y + h + 2)],
                                                  self.p(r), fill=(18, 50, 34, 22))
            self.img.alpha_composite(cam.filter(ImageFilter.GaussianBlur(self.p(4))))
        cam = Image.new('RGBA', self.img.size, (0, 0, 0, 0))
        ImageDraw.Draw(cam).rounded_rectangle(
            [self.p(x), self.p(y), self.p(x + w) - 1, self.p(y + h) - 1], self.p(r),
            fill=cv(cor) if cor else None,
            outline=rgba(borda) if isinstance(borda, str) else borda, width=max(1, self.p(lb)) if borda else 0,
            corners=cantos)
        self.img.alpha_composite(cam)

    def recorte(self, x, y, w, h, r, cantos=None):
        m = Image.new('L', self.img.size, 0)
        ImageDraw.Draw(m).rounded_rectangle([self.p(x), self.p(y), self.p(x + w) - 1, self.p(y + h) - 1], self.p(r),
                                            fill=255, corners=cantos)
        return m

    def colar(self, im, x, y, mascara=None):
        cam = Image.new('RGBA', self.img.size, (0, 0, 0, 0))
        cam.paste(im, (self.p(x), self.p(y)))
        if mascara is not None:
            a = cam.split()[3]
            from PIL import ImageChops
            cam.putalpha(ImageChops.multiply(a, mascara))
        self.img.alpha_composite(cam)

    def icone(self, nome, x, y, tam, cor):
        f = fonte('mdi', self.p(tam))
        self.d.text((self.p(x + tam / 2), self.p(y + tam / 2)), chr(GLIFOS[nome]), font=f, fill=cv(cor), anchor='mm')

    def largura(self, txt, tam, peso):
        return fonte(peso, self.p(tam)).getlength(txt) / self.s

    def linhas(self, txt, tam, peso, maxw, maxl=99):
        palavras, out, atual = txt.split(), [], ''
        for pal in palavras:
            t = (atual + ' ' + pal).strip()
            if self.largura(t, tam, peso) <= maxw or not atual:
                atual = t
            else:
                out.append(atual)
                atual = pal
        out.append(atual)
        if len(out) > maxl:
            out = out[:maxl]
            ult = out[-1]
            while self.largura(ult + '…', tam, peso) > maxw and ult:
                ult = ult[:-1]
            out[-1] = ult.rstrip() + '…'
        for i, l in enumerate(out):
            while self.largura(l, tam, peso) > maxw and len(l) > 1:
                l = l[:-2] + '…'
                out[i] = l
        return out

    def texto(self, txt, x, y, tam, peso=400, cor=None, maxw=None, maxl=99, lh=None, alinhar='l', sombra=False):
        """Escreve e devolve a altura usada (dp). y = topo da caixa de texto."""
        cor = cor or C['texto']
        lh = lh or round(tam * 1.18)
        ls = self.linhas(txt, tam, peso, maxw, maxl) if maxw else [txt]
        f = fonte(peso, self.p(tam))
        for i, l in enumerate(ls):
            base = y + i * lh + lh / 2 + tam * 0.36
            px = x
            if alinhar == 'c':
                px = x + (maxw - self.largura(l, tam, peso)) / 2
            elif alinhar == 'r':
                px = x - self.largura(l, tam, peso)
            if sombra:
                cam = Image.new('RGBA', self.img.size, (0, 0, 0, 0))
                ImageDraw.Draw(cam).text((self.p(px), self.p(base + 1)), l, font=f, fill=(0, 0, 0, 110), anchor='ls')
                self.img.alpha_composite(cam.filter(ImageFilter.GaussianBlur(self.p(3))))
            self.d.text((self.p(px), self.p(base)), l, font=f, fill=cv(cor), anchor='ls')
        return len(ls) * lh


# ---------- peças do app ----------

PAISAGEM = Image.open(os.path.join(RAIZ, 'assets/ilustracoes/cabecalho.png')).convert('RGBA')
TRACO = Image.open(os.path.join(RAIZ, 'assets/ilustracoes/traco.png')).convert('RGBA')


def traco(t, x, y, larg):
    im = TRACO.resize((t.p(larg), t.p(larg / 10)), Image.LANCZOS)
    t.colar(im, x, y)


def barra_status(t, claro=True):
    cor = '#FFFFFF' if claro else C['texto']
    t.texto('9:41', 16, 4, 14, 500, cor)
    x = t.W - 16
    for nome, tam in (('battery-80', 17), ('network-strength-4', 15), ('wifi', 16)):
        x -= tam + 3
        t.icone(nome, x, 12 - tam / 2 + 1, tam, cor)


def cabecalho(t, titulo=None, busca=None, voltar=False):
    topo = 24
    y = topo + 10
    altura = y + 48
    if titulo:
        altura += 20 + 35 * len(t.linhas(titulo, 30, 900, (t.W - 32) * 0.78)) + 4 + 8.8
    if busca:
        altura += 20 + 56
    altura += 22 if (titulo or busca) else 14
    # fundo verde + paisagem (cover, encostada à direita)
    t.caixa(0, 0, t.W, altura, 26, C['verdeEscuro'], cantos=(False, False, True, True))
    w, h = t.p(t.W), t.p(altura)
    esc = max(w / PAISAGEM.width, h / PAISAGEM.height)
    im = PAISAGEM.resize((round(PAISAGEM.width * esc), round(PAISAGEM.height * esc)), Image.LANCZOS)
    im = im.crop((im.width - w, (im.height - h) // 2, im.width, (im.height - h) // 2 + h))
    t.colar(im, 0, 0, t.recorte(0, 0, t.W, altura, 26, cantos=(False, False, True, True)))
    barra_status(t)
    x = 16
    if voltar:
        t.icone('arrow-left', x, y + 10, 28, '#FFFFFF')
        x += 50
    # marca
    t.texto('Acha Fácil!', x, y + 6, 24, 'i', '#FFFFFF', lh=30)
    traco(t, x, y + 33, 24 * 3.9)
    # pílula da cidade
    nome = 'São João da Boa Vista'
    # a pílula encolhe (flexShrink) até caber ao lado da marca, e no máximo 58% da linha
    livre = t.W - 16 - (x + t.largura('Acha Fácil!', 24, 'i') + 10)
    pw = min(57 + t.largura(nome, 14, 700), (t.W - 32) * 0.58, livre)
    tw = pw - 57
    px = t.W - 16 - pw
    t.caixa(px, y + 4, pw, 40, 20, (0, 0, 0, 46), (255, 255, 255, 191), 1.5)
    t.icone('map-marker', px + 10, y + 4 + 11.5, 17, '#FFFFFF')
    t.texto(nome, px + 30, y + 4 + 11, 14, 700, '#FFFFFF', maxw=tw + 1, maxl=1, lh=18)
    t.icone('chevron-down', px + 30 + tw + 3, y + 4 + 11, 18, '#FFFFFF')
    y += 48
    if titulo:
        y += 20
        y += t.texto(titulo, 16, y, 30, 900, '#FFFFFF', maxw=(t.W - 32) * 0.78, lh=35, sombra=True)
        traco(t, 16, y + 4, 88)
        y += 4 + 8.8
    if busca:
        y += 20
        t.caixa(16, y, t.W - 32, 56, 28, C['amarelo'])
        t.icone('magnify', 34, y + 15, 26, C['verde'])
        t.texto(busca, 70, y + 18, 17, 600, C['verde'], maxw=t.W - 32 - 70, maxl=1, lh=20)
        y += 56
    return altura


def foto(t, x, y, w, h, icone, cor, r=0, cantos=None):
    cam = Tela(w, h, t.s)
    cam.img = Image.new('RGBA', (t.p(w), t.p(h)), rgba(cor))
    cam.d = ImageDraw.Draw(cam.img)
    lado = min(w, h)
    cam.caixa(w - lado * 0.75, -lado * 0.45, lado * 1.1, lado * 1.1, lado * 0.55, (255, 255, 255, 36))
    cam.icone(icone if icone in GLIFOS else 'storefront-outline', (w - lado * 0.46) / 2, (h - lado * 0.46) / 2, lado * 0.46, (255, 255, 255, 242))
    t.colar(cam.img, x, y, t.recorte(x, y, w, h, r, cantos))


def titulo_secao(t, y, texto, acao=None):
    """Título de seção (marginTop 24, marginBottom 12); devolve o y depois dele."""
    y += 24
    maxw = t.W - 32 - (110 if acao else 0)
    n = len(t.linhas(texto, 22, 900, maxw))
    h = max(40, 27 * n)
    t.texto(texto, 16, y + (h - 27 * n) / 2, 22, 900, maxw=maxw, lh=27)
    if acao:
        w = t.largura(acao, 16, 800)
        t.texto(acao, t.W - 16 - 22 - w, y + 10, 16, 800, C['destaque'], lh=20)
        t.icone('chevron-right', t.W - 16 - 22, y + 9, 22, C['destaque'])
    return y + h + 12


def barra_abas(t, ativa):
    baixo = 12
    h = 76 + baixo
    y = t.H - h
    t.caixa(0, y - 3, t.W, 3, 0, (18, 50, 34, 12))
    t.caixa(0, y, t.W, h, 0, C['cartao'])
    t.caixa(0, y, t.W, 1, 0, C['borda'])
    abas = [('Início', 'home-outline', 'home'), ('Categorias', 'view-grid-outline', 'view-grid'),
            ('Favoritos', 'heart-outline', 'heart'), ('Perfil', 'account-outline', 'account')]
    larg = t.W / 4
    for i, (nome, ic, ica) in enumerate(abas):
        cx = larg * i + larg / 2
        on = i == ativa
        cor = C['verde'] if on else C['texto']
        t.icone(ica if on else ic, cx - 14, y + 10 + 3, 28, cor)
        w = t.largura(nome, 14, 900 if on else 600)
        t.texto(nome, cx - w / 2, y + 10 + 33, 14, 900 if on else 600, cor, lh=17)
        if on:
            t.caixa(cx - 20, y + 10 + 52, 40, 3, 2, C['verde'])
    # barra de gestos do Android
    t.caixa(t.W / 2 - 54, t.H - 7, 108, 4, 2, (18, 50, 34, 90))


def contato(t, x, y, p):
    if not p['temWhatsapp'] and not p['temTelefone']:
        return x
    t.caixa(x, y + 5, 10, 10, 5, C['whatsapp'] if p['temWhatsapp'] else C['destaque'])
    txt = 'Tem WhatsApp' if p['temWhatsapp'] else 'Tem telefone'
    t.texto(txt, x + 16, y + 1, 15, 800, C['whatsapp'], lh=18)
    return x + 16 + t.largura(txt, 15, 800) + 6


def textoDistancia(km):
    if km < 1:
        return f"{max(50, round(km * 1000 / 50) * 50)} m"
    return (f"{km:.1f}".replace('.', ',') if km < 10 else str(round(km))) + ' km'


def cartao_prof(t, y, p, distancia=None):
    """CartaoProfissional (ui.tsx); devolve a altura + marginBottom 12."""
    x, w = 16, t.W - 32
    larg_txt = w - 108 - 12 - 12 - 28 - 8
    nome_l = t.linhas(p['nome'], 19, 900, larg_txt, 2)
    h = max(92, 10 + 23 * len(nome_l) + 2 + 20 + 2 + 4 + 22 + 10)
    t.caixa(x, y, w, h, 16, C['cartao'], C['borda'], 1, sombra=True)
    foto(t, x + 1, y + 1, 108, h - 2, p['icone'], p['cor'], 15, cantos=(True, False, False, True))
    tx = x + 108 + 12
    ty = y + (h - (23 * len(nome_l) + 2 + 20 + 6 + 22)) / 2
    ty += t.texto(p['nome'], tx, ty, 19, 900, maxw=larg_txt, maxl=2, lh=23) + 2
    sub = p['tipo'] + (f" · {p['bairro']}" if p.get('bairro') else '')
    ty += t.texto(sub, tx, ty, 16, 400, C['textoSuave'], maxw=larg_txt, maxl=1, lh=20) + 6
    cx = tx
    if distancia is not None:
        dt = textoDistancia(distancia)
        dw = 8 + 15 + 2 + t.largura(dt, 14, 800) + 8
        t.caixa(cx, ty, dw, 21, 10, C['verdeClaro'])
        t.icone('map-marker-outline', cx + 8, ty + 3, 15, C['verde'])
        t.texto(dt, cx + 8 + 17, ty + 2, 14, 800, C['verde'], lh=17)
        cx += dw + 6
    if p.get('verificado'):
        sw = 8 + 16 + 4 + t.largura('Verificado', 14, 800) + 8
        t.caixa(cx, ty, sw, 21, 10, C['verdeClaro'])
        t.icone('check-decagram', cx + 8, ty + 2.5, 16, C['verde'])
        t.texto('Verificado', cx + 28, ty + 2, 14, 800, C['verde'], lh=17)
        cx += sw + 6
    if p.get('avaliacoes'):
        t.icone('star', cx, ty + 2, 17, C['estrela'])
        nt = f"{p['nota']:.1f}".replace('.', ',')
        t.texto(nt, cx + 19, ty + 1, 16, 400, C['textoSuave'], lh=20)
        cx += 19 + t.largura(nt, 16, 400) + 6
    if cx + 120 > x + w - 36:
        pass
    contato(t, cx, ty, p)
    t.icone('chevron-right', x + w - 8 - 28, y + h / 2 - 14, 28, C['texto'])
    return h + 12


def pilula(t, x, y, texto, icone, ativo):
    w = 18 + (26 if icone else 0) + t.largura(texto, 16, 800) + 18
    t.caixa(x, y, w, 46, 23, C['verde'] if ativo else C['cartao'], C['verde'] if ativo else C['borda'], 1)
    ix = x + 18
    if icone:
        t.icone(icone, ix, y + 13, 20, '#FFFFFF' if ativo else C['verde'])
        ix += 26
    t.texto(texto, ix, y + 13, 16, 800, '#FFFFFF' if ativo else C['texto'], lh=20)
    return w


# ---------- dados ----------

INICIO = json.load(open(os.path.join(ENT, 'inicio.json')))
PERTO = json.load(open(os.path.join(ENT, 'perto-restaurantes.json')))
CURTO = {'mercados': 'Mercados', 'casa': 'Casa', 'lojas': 'Lojas', 'esporte': 'Esporte', 'pet': 'Pet e agro',
         'hospedagem': 'Hospedagem', 'entidades': 'Igrejas'}
# marcas grandes e nomes com emoji ficam fora das imagens da loja
EVITAR = ("bob's", 'spani', 'by up hotel', 'recupera ia')
RECOMENDADOS = [p for p in INICIO['populares'] if not any(e in p['nome'].lower() for e in EVITAR)]


# ---------- telas ----------

def tela_inicio(W, H, s):
    t = Tela(W, H, s)
    y = cabecalho(t, 'Descubra o melhor da cidade', 'Buscar comércios ou serviços')
    y = titulo_secao(t, y, 'Explore por categoria', 'Ver todas')
    areas = sorted([c for c in INICIO['categorias'] if c['total'] > 0], key=lambda c: not c['naInicial'])
    x = 16
    for c in areas:
        if x > W:
            break
        foto(t, x, y, 108, 100, c['icone'], c['cor'], 14)
        t.caixa(x, y + 100 - 30, 108, 30, 14, (0, 0, 0, 71), cantos=(False, False, True, True))
        t.texto(CURTO.get(c['slug'], c['nome']), x + 8, y + 100 - 30 + 6, 15, 800, '#FFFFFF', maxw=92, maxl=1, lh=18)
        x += 118
    y += 100 + 18
    # convite "Seu comércio aqui" (ninguém em destaque ainda)
    t.caixa(16, y, W - 32, 150, 16, C['amarelo'])
    t.caixa(32, y + 41, 68, 68, 34, '#FFFFFF')
    t.icone('storefront-outline', 32 + 15, y + 41 + 15, 38, C['verde'])
    tx = 32 + 68 + 14
    ty = y + 150 / 2 - (24 + 20 + 8 + 44) / 2 - 6
    ty += t.texto('Seu comércio aqui', tx, ty, 20, 900, C['verde'], lh=24)
    ty += t.texto('Apareça em destaque para toda a cidade.', tx, ty, 16, 400, C['verde'], maxw=W - 16 - 16 - tx, lh=20) + 8
    bw = 14 + t.largura('Saiba mais', 16, 800) + 2 + 20 + 8
    t.caixa(tx, ty, bw, 44, 22, C['verde'])
    t.texto('Saiba mais', tx + 14, ty + 12, 16, 800, '#FFFFFF', lh=20)
    t.icone('chevron-right', tx + 14 + t.largura('Saiba mais', 16, 800) + 2, ty + 12, 20, '#FFFFFF')
    y += 150
    y = titulo_secao(t, y, 'Recomendados em São João da Boa Vista')
    x = 16
    for p in RECOMENDADOS:
        if x > W:
            break
        nl = t.linhas(p['nome'], 16, 900, 124, 2)
        h = 92 + 8 + 20 * 2 + 2 + 17 + 8 + 2
        t.caixa(x, y, 140, h, 16, C['cartao'], C['borda'], 1, sombra=True)
        foto(t, x + 1, y + 1, 138, 92, p['icone'], p['cor'], 15, cantos=(True, True, False, False))
        t.texto(p['nome'], x + 8, y + 100, 16, 900, maxw=124, maxl=2, lh=20)
        t.texto(p['tipo'], x + 8, y + 100 + 20 * len(nl) + 2, 14, 400, C['textoSuave'], maxw=124, maxl=1, lh=17)
        x += 150
    y += 92 + 8 + 20 * 2 + 2 + 17 + 8 + 2
    # telas altas (tablet): a inicial continua com "Perto de você"
    if y < H - 200:
        y = secao_perto(t, y)
    barra_abas(t, 0)
    return t.img


def secao_perto(t, y):
    y = titulo_secao(t, y, 'Perto de você')
    x = 16
    pil = [('Tudo', 'map-marker-radius-outline', False)] + [(a['rotulo'], a['icone'], a['id'] == 'restaurantes') for a in PERTO['atalhos']]
    for rot, ic, on in pil:
        if x > t.W:
            break
        x += pilula(t, x, y, rot, ic, on) + 8
    y += 46 + 14
    for p in [p for p in PERTO['profissionais'] if not any(e in p['nome'].lower() for e in EVITAR)]:
        if y > t.H:
            break
        y += cartao_prof(t, y, p, p['distancia'])
    return y


def tela_categorias(W, H, s):
    t = Tela(W, H, s)
    y = cabecalho(t, 'Categorias', 'Buscar comércios ou serviços') + 4 + 12
    col = 2
    larg = (W - 32 - 12 * (col - 1)) / col
    for i, c in enumerate(INICIO['categorias']):
        x = 16 + (i % col) * (larg + 12)
        yy = y + (i // col) * (96 + 10 + 20 + 20 + 10 + 12)
        if yy > H:
            break
        h = 96 + 10 + 20 + 20 + 10
        t.caixa(x, yy, larg, h, 16, C['cartao'], C['borda'], 1, sombra=True)
        foto(t, x + 1, yy + 1, larg - 2, 96, c['icone'], c['cor'], 15, cantos=(True, True, False, False))
        t.texto(c['nome'], x + 10, yy + 106, 17, 900, maxw=larg - 20, maxl=1, lh=20)
        tot = 'Em breve' if c['total'] == 0 else '1 lugar' if c['total'] == 1 else f"{c['total']:,}".replace(',', '.') + ' lugares'
        t.texto(tot, x + 10, yy + 106 + 22, 15, 600, C['textoSuave'], lh=18)
    barra_abas(t, 1)
    return t.img


def tela_perto(W, H, s):
    """Início rolada até "Perto de você", com o atalho Restaurantes escolhido."""
    t = Tela(W, H, s)
    t.caixa(0, 0, W, 24, 0, C['fundo'])
    y = 24 + 6
    secao_perto(t, y - 24)
    barra_status(t, claro=False)
    barra_abas(t, 0)
    return t.img


# Comércio de exemplo (ilustrativo: os comércios importados ainda não têm avaliações nem selo)
EXEMPLO = dict(nome='Pizzaria Bella Massa', tipo='Pizzaria', icone='pizza', cor='#E8590C', verificado=True, nota=4.8,
               avaliacoes=37, endereco='Rua Exemplo, 123', bairro='Centro', cidade='São João',
               telefone='(19) 99999-0000', horario='Ter. a dom., das 18h às 23h30')


def tela_comercio(W, H, s):
    t = Tela(W, H, s)
    p = EXEMPLO
    y = cabecalho(t, voltar=True)
    y += 16
    foto(t, 16, y, W - 32, 200, p['icone'], p['cor'], 16)
    y += 200 + 16
    larg = W - 32 - 56 - 12
    y0 = y
    y += t.texto(p['nome'], 16, y, 28, 900, maxw=larg, lh=33) + 4
    y += t.texto(p['tipo'], 16, y, 18, 400, C['textoSuave'], lh=22) + 4
    sw = 8 + 16 + 4 + t.largura('Verificado', 14, 800) + 8
    t.caixa(16, y + 2, sw, 21, 10, C['verdeClaro'])
    t.icone('check-decagram', 24, y + 4.5, 16, C['verde'])
    t.texto('Verificado', 44, y + 4, 14, 800, C['verde'], lh=17)
    ex = 16 + sw + 8
    for i in range(5):
        t.icone('star' if p['nota'] >= i + 1 else 'star-half-full', ex + i * 18, y + 3, 18, C['estrela'])
    t.texto(f"{p['nota']:.1f}".replace('.', ',') + f" ({p['avaliacoes']})", ex + 96, y + 4, 15, 400, C['textoSuave'], lh=18)
    y += 25
    t.caixa(W - 16 - 56, y0, 56, 56, 16, C['cartao'], C['borda'], 1.5)
    t.icone('heart', W - 16 - 56 + 13, y0 + 13, 30, C['verde'])
    y += 16
    bw = (W - 32 - 10) / 2
    t.caixa(16, y, bw, 56, 28, C['amarelo'])
    for i, (tit, ic, fundo, cor, borda) in enumerate((('Como chegar', 'navigation-variant', C['amarelo'], C['verde'], None),
                                                       ('Compartilhar', 'share-variant-outline', C['cartao'], C['verde'], C['verde']))):
        bx = 16 + i * (bw + 10)
        t.caixa(bx, y, bw, 56, 28, fundo, borda, 2)
        tw = 26 + 8 + t.largura(tit, 18, 800)
        t.icone(ic, bx + (bw - tw) / 2, y + 15, 26, cor)
        t.texto(tit, bx + (bw - tw) / 2 + 34, y + 17, 18, 800, cor, lh=22)
    y += 56 + 12
    t.caixa(16, y, W - 32, 56, 28, C['whatsapp'])
    tw = 28 + 12 + t.largura('Conversar no WhatsApp', 20, 800)
    t.icone('whatsapp', (W - tw) / 2, y + 14, 28, '#FFFFFF')
    t.texto('Conversar no WhatsApp', (W - tw) / 2 + 40, y + 16, 20, 800, '#FFFFFF', lh=24)
    y += 56 + 14
    linhas = [('map-marker', p['endereco'], f"{p['bairro']} · {p['cidade']}", True),
              ('phone', p['telefone'], 'Ligar', True),
              ('instagram', 'Ver Instagram', None, True),
              ('clock-outline', 'Horário de funcionamento', p['horario'], False)]
    ch = sum(64 for _ in linhas)
    t.caixa(16, y, W - 32, ch, 16, C['cartao'], C['borda'], 1, sombra=True)
    ly = y
    for i, (ic, txt, det, seta) in enumerate(linhas):
        t.icone(ic, 30, ly + 19, 26, C['verde'])
        hh = 21 + (19 if det else 0)
        ty = ly + (64 - hh) / 2
        t.texto(txt, 70, ty, 17, 800, maxw=W - 32 - 100, maxl=1, lh=21)
        if det:
            t.texto(det, 70, ty + 21, 15, 400, C['textoSuave'], maxw=W - 32 - 100, maxl=1, lh=19)
        if seta:
            t.icone('chevron-right', W - 16 - 14 - 26, ly + 19, 26, C['texto'])
        if i < len(linhas) - 1:
            t.caixa(30, ly + 63, W - 32 - 28, 1, 0, C['borda'])
        ly += 64
    y += ch + 24
    # avaliações
    t.icone('star-outline', 16, y + 9, 26, C['texto'])
    t.texto('Avaliações', 50, y + 8, 22, 900, lh=27)
    aw = 32 + t.largura('Avaliar', 16, 900)
    t.caixa(W - 16 - aw, y, aw, 44, 22, C['amarelo'])
    t.texto('Avaliar', W - 16 - aw + 16, y + 12, 16, 900, C['verde'], lh=20)
    y += 44 + 8
    for autor, data, est, com in (('Mariana S.', '02/11/2026', 5, 'Massa fininha e entrega rapidinha. A melhor de calabresa da cidade!'),
                                  ('Carlos R.', '28/10/2026', 5, 'Atendimento ótimo pelo WhatsApp.')):
        h = 14 + 26 + 6 + 18 + 6 + 26 * len(t.linhas(com, 18, 400, W - 32 - 28)) + 14
        t.caixa(16, y + 10, W - 32, h, 16, C['cartao'], C['borda'], 1, sombra=True)
        t.texto(autor, 30, y + 24, 18, 800, lh=26)
        t.texto(data, W - 30, y + 28, 15, 400, C['textoSuave'], lh=18, alinhar='r')
        for i in range(est):
            t.icone('star', 30 + i * 18, y + 24 + 32, 18, C['estrela'])
        t.texto(com, 30, y + 24 + 32 + 24, 18, 400, maxw=W - 32 - 28, lh=26)
        y += 10 + h
    return t.img


# ---------- moldura e legenda ----------

def montar(tela_img, legenda, larg, alt, nome, larg_tela, topo, raio=44, borda=14):
    fundo = Image.new('RGBA', (larg, alt), rgba(C['verdeEscuro']))
    # paisagem de fundo, bem escura
    esc = max(larg / PAISAGEM.width, alt * 0.55 / PAISAGEM.height)
    pais = PAISAGEM.resize((round(PAISAGEM.width * esc), round(PAISAGEM.height * esc)), Image.LANCZOS)
    pais = pais.crop((pais.width - larg, 0, pais.width, pais.height))
    # some aos poucos no topo, para não fazer emenda com o verde de cima
    mascara = Image.linear_gradient('L').resize(pais.size)
    from PIL import ImageChops
    pais.putalpha(ImageChops.multiply(pais.split()[3], mascara.point(lambda v: min(255, v * 2))))
    fundo.alpha_composite(pais, (0, alt - pais.height))
    d = ImageDraw.Draw(fundo)
    # legenda
    k = larg / 1080
    f = fonte(900, round(62 * k))
    ls, y = legenda.split('\n'), round(70 * k)
    for l in ls:
        w = f.getlength(l)
        d.text(((larg - w) / 2, y), l, font=f, fill=rgba('#FFFFFF'), anchor='la')
        y += round(76 * k)
    tw = round(150 * k)
    tr = TRACO.resize((tw, tw // 10), Image.LANCZOS)
    fundo.alpha_composite(tr, ((larg - tw) // 2, y + round(4 * k)))
    # celular
    esc = larg_tela / tela_img.width
    tela = tela_img.resize((larg_tela, round(tela_img.height * esc)), Image.LANCZOS)
    x = (larg - larg_tela) // 2
    b = round(borda * k)
    r = round(raio * k)
    sombra = Image.new('RGBA', fundo.size, (0, 0, 0, 0))
    ImageDraw.Draw(sombra).rounded_rectangle([x - b, topo - b + 12, x + larg_tela + b, topo + tela.height + b + 12], r + b, fill=(0, 0, 0, 120))
    fundo.alpha_composite(sombra.filter(ImageFilter.GaussianBlur(round(24 * k))))
    d.rounded_rectangle([x - b, topo - b, x + larg_tela + b, topo + tela.height + b], r + b, fill=rgba('#0E1512'))
    m = Image.new('L', tela.size, 0)
    ImageDraw.Draw(m).rounded_rectangle([0, 0, tela.width - 1, tela.height - 1], r, fill=255)
    fundo.paste(tela, (x, topo), m)
    fundo.convert('RGB').save(os.path.join(SAI, nome), optimize=True)
    return os.path.join(SAI, nome)


TELAS = [
    (tela_inicio, 'Tudo da sua cidade\nem um só lugar', '1-inicio'),
    (tela_categorias, 'Mais de 4.500 comércios\nem 17 categorias', '2-categorias'),
    (tela_perto, 'Veja o que tem\nperto de você', '3-perto'),
    (tela_comercio, 'Chame no WhatsApp\nem um toque', '4-comercio'),
]

if __name__ == '__main__':
    feitos = []
    # celular: tela 360x760 dp (19:9) numa imagem 1080x1920 (9:16)
    for fn, leg, nome in TELAS:
        feitos.append(montar(fn(360, 760, 2), leg, 1080, 1920, f'celular-{nome}.png', 720, 300))
    # tablet de 7": tela 600x960 dp; tablet de 10": 800x1280 dp
    for fn, leg, nome in TELAS:
        feitos.append(montar(fn(600, 960, 1.5), leg, 1080, 1920, f'tablet7-{nome}.png', 900, 300, raio=30))
        feitos.append(montar(fn(800, 1280, 1.5), leg, 1440, 2560, f'tablet10-{nome}.png', 1200, 400, raio=30))
    print('\n'.join(feitos))
