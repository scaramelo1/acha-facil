# Imagem de compartilhar (og:image, 1200x630) do Acha Fácil: a paisagem do cabeçalho do app, o símbolo e o texto.
# 19/11 (avaliação do site): cada cidade com o seu número ("4.464 comércios em São João da Boa Vista - SP") e a inicial
# com o total. Chamado por publico/og.php (gera na 1ª vez e de novo a cada 7 dias); também dá para rodar à mão:
#   python3 servidor/og-imagem.py saida.png "4.464" "comércios e serviços" "São João da Boa Vista - SP"
# Precisa do Pillow. Fonte Nunito (OFL, Google Fonts) em servidor/fontes/.
import os, sys
from PIL import Image, ImageDraw, ImageFont

AQUI = os.path.dirname(os.path.abspath(__file__))
FUNDO = os.path.join(AQUI, '..', 'assets', 'ilustracoes', 'cabecalho.png')
SIMBOLO = os.path.join(AQUI, 'publico', 'assets', 'logo.png')
FONTE = os.path.join(AQUI, 'fontes', 'Nunito.ttf')
W, H = 1200, 630
BRANCO, AMARELO, VERDE = (255, 255, 255), (247, 198, 0), (0, 92, 56)


def fonte(tam, peso=900):
    f = ImageFont.truetype(FONTE, tam)
    try: f.set_variation_by_axes([peso])
    except Exception: pass
    return f


def cabe(d, texto, tam, peso, largura):
    """Maior fonte (até tam) em que o texto cabe na largura."""
    while tam > 20:
        f = fonte(tam, peso)
        if d.textlength(texto, font=f) <= largura: return f
        tam -= 2
    return fonte(tam, peso)


def gerar(saida, numero, linha2, linha3):
    img = Image.open(FUNDO).convert('RGB').crop((0, 0, W, H))
    # escurece a esquerda para o texto ler bem (a igreja fica à direita)
    sombra = Image.new('L', (W, H), 0)
    ds = ImageDraw.Draw(sombra)
    for x in range(W):
        ds.line([(x, 0), (x, H)], fill=max(0, int(170 * (1 - x / (W * 0.75)))))
    img.paste(Image.new('RGB', (W, H), (4, 40, 24)), (0, 0), sombra)
    d = ImageDraw.Draw(img)
    m = 64
    # símbolo + "Acha Fácil!"
    s = Image.open(SIMBOLO).convert('RGBA').resize((96, 96), Image.LANCZOS)
    img.paste(s, (m - 10, 44), s)
    f = fonte(56, 900)
    d.text((m + 92, 62), 'Acha ', font=f, fill=BRANCO)
    d.text((m + 92 + d.textlength('Acha ', font=f), 62), 'Fácil!', font=f, fill=AMARELO)
    # número grande, o que é e onde
    larg = 700
    fn = cabe(d, numero, 150, 1000, larg)
    d.text((m, 175), numero, font=fn, fill=AMARELO)
    y = 175 + fn.getbbox(numero)[3] + 14
    f2 = cabe(d, linha2, 50, 800, larg)
    d.text((m, y), linha2, font=f2, fill=BRANCO)
    y += f2.getbbox(linha2)[3] + 12
    f3 = cabe(d, linha3, 50, 900, larg)
    d.text((m, y), linha3, font=f3, fill=BRANCO)
    # faixa de baixo
    d.rectangle([0, H - 78, W, H], fill=VERDE)
    d.rectangle([0, H - 82, W, H - 78], fill=AMARELO)
    fb = fonte(30, 800)
    d.text((m, H - 60), 'Endereço, horário e WhatsApp, grátis', font=fb, fill=BRANCO)
    site = 'achafacilapp.com.br'
    fs = fonte(30, 900)
    d.text((W - m - d.textlength(site, font=fs), H - 60), site, font=fs, fill=AMARELO)
    tmp = saida + '.tmp.png'
    img.save(tmp, optimize=True)
    os.replace(tmp, saida)


if __name__ == '__main__':
    if len(sys.argv) != 5:
        sys.exit('uso: og-imagem.py saida.png NUMERO "linha 2" "linha 3"')
    gerar(*sys.argv[1:])
