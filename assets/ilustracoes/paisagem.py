# Desenha assets/ilustracoes/cabecalho.png (paisagem do cabeçalho) e traco.png (traço amarelo dos títulos).
# Uso: python3 assets/ilustracoes/paisagem.py assets/ilustracoes/cabecalho.png assets/ilustracoes/traco.png (precisa do Pillow)
import math, random, sys
from PIL import Image, ImageDraw, ImageFilter
S = 2; W, H = 1200, 640
w, h = W*S, H*S
random.seed(7)
img = Image.new('RGB', (w, h))
d = ImageDraw.Draw(img)
def mix(a, b, t): return tuple(int(a[i]+(b[i]-a[i])*t) for i in range(3))
hexc = lambda s: tuple(int(s[i:i+2], 16) for i in (1, 3, 5))
topo, base = hexc('#0A3F26'), hexc('#155F39')
for y in range(h): d.line([(0, y), (w, y)], fill=mix(topo, base, y/h))
# brilho suave atrás da igreja (fim de tarde)
glow = Image.new('L', (w, h), 0); gd = ImageDraw.Draw(glow)
gd.ellipse([w*0.62, h*0.05, w*1.05, h*0.75], fill=90); glow = glow.filter(ImageFilter.GaussianBlur(120*S))
img.paste(Image.new('RGB', (w, h), hexc('#3E8E52')), (0, 0), glow)
d = ImageDraw.Draw(img)

def morro(y0, amp, ondas, cor, fase=0.0):
    pts = []
    for i in range(0, w+8, 8):
        x = i/w
        y = y0 + sum(a*math.sin(2*math.pi*f*x + fase + k) for k, (a, f) in enumerate(zip(amp, ondas)))
        pts.append((i, y*h))
    pts += [(w, h), (0, h)]
    d.polygon(pts, fill=cor)
    return pts

def arvores(pts, n, cores, rmin, rmax, faixa=(0, 1), desce=0.06):
    for _ in range(n):
        x = random.uniform(faixa[0], faixa[1])*w
        i = min(int(x/8), len(pts)-3)
        y = pts[i][1] + random.uniform(0, desce)*h
        r = random.uniform(rmin, rmax)*S
        c = random.choice(cores)
        d.ellipse([x-r, y-r, x+r, y+r], fill=c)

far = morro(0.48, [0.05, 0.03], [0.7, 1.9], hexc('#1C6A42'), 0.4)
arvores(far, 160, [hexc('#1F7146'), hexc('#18603B'), hexc('#226F45')], 6, 12, desce=0.04)
mid = morro(0.60, [0.05, 0.025], [0.9, 2.6], hexc('#1E7646'), 2.1)
arvores(mid, 220, [hexc('#2A8A50'), hexc('#1B6A3F'), hexc('#2F9455'), hexc('#237D49')], 9, 18, desce=0.05)

# casinhas brancas com telhado de barro na encosta da direita
def casa(cx, base_y, lw, lh, telha='#B4532F', parede='#F5EFE0'):
    x0, x1 = cx-lw/2*S, cx+lw/2*S
    d.rectangle([x0, base_y-lh*S, x1, base_y], fill=hexc(parede))
    d.polygon([(x0-4*S, base_y-lh*S), (cx, base_y-(lh+lw*0.45)*S), (x1+4*S, base_y-lh*S)], fill=hexc(telha))
    d.rectangle([cx-lw*0.12*S, base_y-lh*0.55*S, cx+lw*0.12*S, base_y], fill=hexc('#0B5D36'))
for cx, by, lw, lh in [(0.66, 0.66, 44, 26), (0.71, 0.69, 36, 22), (0.585, 0.70, 40, 24), (0.93, 0.71, 46, 26), (0.52, 0.735, 34, 20)]:
    casa(cx*w, by*h, lw, lh)

# igreja amarela (fachada com torre central), na direita
cx, chao = 0.80*w, 0.66*h
def R(x0, y0, x1, y1, c): d.rectangle([cx+x0*S, chao-y1*S, cx+x1*S, chao-y0*S], fill=hexc(c))
amarelo, branco, telha, verde = '#F2C230', '#FFF8E6', '#B4532F', '#0B5D36'
R(-62, 0, 62, 120, amarelo)                     # corpo
d.polygon([(cx-70*S, chao-120*S), (cx, chao-168*S), (cx+70*S, chao-120*S)], fill=hexc(amarelo))  # frontão
d.line([(cx-70*S, chao-120*S), (cx, chao-168*S), (cx+70*S, chao-120*S)], fill=hexc(branco), width=5*S)
R(-62, 116, 62, 124, branco)                    # friso
R(-66, 0, -56, 124, branco); R(56, 0, 66, 124, branco)  # cantos
R(-24, 150, 24, 250, amarelo)                   # torre
R(-28, 150, 28, 158, branco); R(-28, 246, 28, 252, branco)
d.polygon([(cx-30*S, chao-252*S), (cx, chao-292*S), (cx+30*S, chao-252*S)], fill=hexc(telha))  # cúpula
R(-2, 290, 2, 322, branco); R(-12, 306, 12, 310, branco)  # cruz
# sino e janelas em arco
def arco(x, y, lw, lh, c):
    d.rectangle([cx+(x-lw/2)*S, chao-(y+lh-lw/2)*S, cx+(x+lw/2)*S, chao-y*S], fill=hexc(c))
    d.ellipse([cx+(x-lw/2)*S, chao-(y+lh)*S, cx+(x+lw/2)*S, chao-(y+lh-lw)*S], fill=hexc(c))
arco(0, 186, 22, 44, '#5A3A1E')
arco(0, 0, 30, 60, '#6B4423')        # porta
arco(-38, 52, 16, 36, verde); arco(38, 52, 16, 36, verde)
d.ellipse([cx-11*S, chao-142*S, cx+11*S, chao-120*S], fill=hexc(branco))
# alas laterais
R(-110, 0, -66, 80, amarelo); R(66, 0, 110, 80, amarelo)
d.polygon([(cx-116*S, chao-80*S), (cx-66*S, chao-104*S), (cx-66*S, chao-80*S)], fill=hexc(telha))
d.polygon([(cx+116*S, chao-80*S), (cx+66*S, chao-104*S), (cx+66*S, chao-80*S)], fill=hexc(telha))
arco(-88, 26, 14, 32, verde); arco(88, 26, 14, 32, verde)

perto = morro(0.74, [0.035, 0.02], [0.8, 2.2], hexc('#145A35'), 4.0)
arvores(perto, 240, [hexc('#1B6B3E'), hexc('#155C36'), hexc('#227A47'), hexc('#2B8A51')], 14, 30, desce=0.08)
arvores(perto, 40, [hexc('#33995A'), hexc('#3BA363')], 10, 20, faixa=(0.5, 1), desce=0.05)
# coqueiros/palmeiras discretas à direita
for px in (0.70, 0.96):
    x, y = px*w, 0.66*h
    d.line([(x, y), (x+6*S, y-90*S)], fill=hexc('#4A3420'), width=5*S)
    for ang in range(0, 360, 45):
        a = math.radians(ang)
        d.line([(x+6*S, y-90*S), (x+6*S+math.cos(a)*38*S, y-90*S+math.sin(a)*16*S+10*S)], fill=hexc('#2E8B4E'), width=7*S)

# escurece a esquerda e o topo para o texto branco ficar legível
sombra = Image.new('L', (w, h), 0); sd = ImageDraw.Draw(sombra)
for x in range(w):
    t = max(0.0, 1 - x/(w*0.72)); sd.line([(x, 0), (x, h)], fill=int(205*t**1.2))
topo_s = Image.new('L', (w, h), 0); td = ImageDraw.Draw(topo_s)
for y in range(h):
    t = max(0.0, 1 - y/(h*0.45)); td.line([(0, y), (w, y)], fill=int(120*t))
from PIL import ImageChops
mascara = ImageChops.lighter(sombra, topo_s)
img.paste(Image.new('RGB', (w, h), hexc('#083A22')), (0, 0), mascara)
img = img.resize((W, H), Image.LANCZOS)
img.save(sys.argv[1], optimize=True)

# traço amarelo (pincelada fina, mais grossa no meio)
TW, TH = 480, 48
t2 = Image.new('RGBA', (TW*4, TH*4), (0, 0, 0, 0)); tdd = ImageDraw.Draw(t2)
for i in range(600):
    t = i/599
    x = (0.03 + 0.94*t)*TW*4
    y = (0.70 - 0.42*math.sin(math.pi*t*0.85) + 0.05*t)*TH*4
    r = (2 + 14*math.sin(math.pi*t)**0.7)*4*0.5
    tdd.ellipse([x-r, y-r, x+r, y+r], fill=(247, 198, 0, 255))
t2.resize((TW, TH), Image.LANCZOS).save(sys.argv[2], optimize=True)
