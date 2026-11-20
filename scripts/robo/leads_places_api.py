#!/usr/bin/env python3
"""
Gera leads de uma cidade via Google Places API (oficial) e monta o HTML no formato do "projeto leeds".
Uso:
  pip install requests
  export GOOGLE_MAPS_API_KEY=suachave   (Places API (New) ativada no Google Cloud)
  python3 leads_places_api.py "Poços de Caldas" MG
Saída: leads_<cidade>.html  + totais por status no terminal.

16/11/2026 (Acha Fácil): cada linha leva o ponto exato do Google (data-lat/data-lng, campo places.location, que já vem
no mesmo pedido, sem custo extra) e se o comércio está aberto (data-ativo: ativo / confirmar = fechado temporariamente /
inativo = fechado de vez; o importador tira do app os que fecharam de vez).
20/11/2026: também procura igrejas, cemitérios, velórios e órgãos públicos (pedido do usuário; +25 termos, ~12% a mais de pesquisas).
"""
import os, sys, re, json, html, time, datetime, unicodedata, concurrent.futures as cf
from urllib.parse import urlparse, quote_plus
import requests, urllib3
urllib3.disable_warnings()

KEY = os.environ.get("GOOGLE_MAPS_API_KEY")
ARGS = [a for a in sys.argv[1:] if not a.startswith("--")]
CIDADE = ARGS[0] if len(ARGS) > 0 else "Poços de Caldas"
UF = ARGS[1] if len(ARGS) > 1 else "MG"

TERMOS = """restaurante|lanchonete|pizzaria|hamburgueria|padaria|confeitaria|doceria|sorveteria|açaí|cafeteria|bar|choperia|churrascaria|marmitaria|comida japonesa|pastelaria|distribuidora de bebidas|adega|supermercado|mercearia|hortifruti|açougue|empório|salão de beleza|barbearia|manicure|clínica de estética|depilação|sobrancelha|tatuagem|academia|pilates|estúdio de dança|artes marciais|pet shop|clínica veterinária|banho e tosa|agropecuária|loja de roupas|moda feminina|moda masculina|moda infantil|loja de calçados|ótica|joalheria|bijuteria|loja de presentes|papelaria|livraria|loja de brinquedos|loja de cosméticos|perfumaria|farmácia|farmácia de manipulação|produtos naturais|suplementos|floricultura|loja de móveis|colchões|decoração|loja de tecidos|armarinho|materiais de construção|vidraçaria|marmoraria|serralheria|marcenaria|loja de tintas|ferragens|material elétrico|iluminação|pisos e revestimentos|oficina mecânica|auto elétrica|funilaria|borracharia|auto peças|lava rápido|estética automotiva|loja de veículos|motopeças|oficina de motos|bicicletaria|autoescola|loja de celulares|assistência técnica celular|informática|eletrodomésticos|gráfica|comunicação visual|fotografia|agência de marketing|contabilidade|advocacia|imobiliária|construtora|arquitetura|despachante|seguros|agência de viagens|hotel|pousada|clínica médica|dentista|psicólogo|fisioterapia|nutricionista|laboratório|fonoaudiologia|escola de idiomas|curso preparatório|escola infantil|escola de música|curso profissionalizante|lavanderia|costureira|sapataria|chaveiro|dedetizadora|piscinas|energia solar|ar condicionado|segurança eletrônica|provedor de internet|buffet|salão de festas|loja de festas|bolos|cervejaria|artigos esportivos|loja de pesca|tabacaria|loja de vinhos|queijaria|chocolates|doces artesanais|artesanato|loja de malhas|cama mesa e banho|loja de utilidades|brechó|loja de noivas|instrumentos musicais|funerária|transportadora|mudanças|locadora de veículos|estacionamento|gás|água mineral|ferro velho|casa de repouso|loja de embalagens|atacadista|confecção|uniformes|bordado|camisetas personalizadas|lingerie|moda fitness|massagem|spa|petiscaria|pub|casa de shows|turismo|artigos religiosos|cortinas|gesso|eletricista|encanador|portões automáticos|calhas|paisagismo|viveiro de plantas|peixaria|rotisseria|comida árabe|comida chinesa|espetinho|salgados|podologia|micropigmentação|madeireira|pneus|som automotivo|insulfilm|retífica|alinhamento e balanceamento|locação de equipamentos|aluguel de brinquedos|lotérica|assistência técnica|reformas|clube|pesqueiro|laticínio|café|cachaçaria""".split("|")
# 20/11/2026 (pedido do usuário): igrejas, cemitérios, velórios e órgãos públicos
TERMOS_NOVOS = """igreja|igreja evangélica|paróquia|centro espírita|templo|cemitério|velório|prefeitura|câmara municipal|cartório|correios|delegacia de polícia|polícia militar|corpo de bombeiros|fórum|posto de saúde|UPA pronto atendimento|CRAS assistência social|INSS|Poupatempo|conselho tutelar|secretaria municipal|biblioteca pública|escola estadual|escola municipal""".split("|")
TERMOS += TERMOS_NOVOS
# --so-novos: pesquisa SÓ os termos novos (para cidade já rodada antes de 20/11) e grava leads_<cidade>_novos.html
SO_NOVOS = "--so-novos" in sys.argv
if SO_NOVOS: TERMOS = TERMOS_NOVOS


FIELDS = ("places.id,places.displayName,places.primaryTypeDisplayName,places.formattedAddress,places.addressComponents,"
          "places.nationalPhoneNumber,places.websiteUri,places.rating,places.userRatingCount,places.googleMapsUri,"
          "places.businessStatus,places.location,places.regularOpeningHours,nextPageToken")

class Parar(Exception):
    """Erro que não adianta repetir (chave inválida, API desligada, crédito ou limite do dia acabou)."""

PESQUISAS = [0]   # pesquisas feitas (cada uma é cobrada pelo Google)

def busca(termo):
    out, token = [], None
    for _ in range(3):  # a API devolve até 60 por consulta
        body = {"textQuery": f"{termo} em {CIDADE} - {UF}", "languageCode": "pt-BR", "regionCode": "BR", "pageSize": 20}
        if token: body["pageToken"] = token
        for tentativa in range(4):
            try:
                r = requests.post("https://places.googleapis.com/v1/places:searchText", json=body, timeout=30,
                                  headers={"X-Goog-Api-Key": KEY, "X-Goog-FieldMask": FIELDS})
            except requests.RequestException as erro:
                print("rede", termo, erro); time.sleep(10); continue
            PESQUISAS[0] += 1
            if r.status_code == 429 and tentativa < 3: time.sleep(30); continue   # muitas por minuto: espera e tenta de novo
            if r.status_code >= 500 and tentativa < 3: time.sleep(10); continue
            break
        else:
            print("erro", termo, "sem resposta"); return out
        # 20/11: chave inválida, API desligada, sem faturamento ou limite do dia = para tudo (antes salvava a lista pela metade)
        if r.status_code in (400, 401, 403, 429):
            raise Parar(f"{r.status_code} em '{termo}': {r.text[:300]}")
        if r.status_code != 200:
            print("erro", termo, r.status_code, r.text[:200]); break
        j = r.json(); out += j.get("places", [])
        token = j.get("nextPageToken")
        if not token: break
        time.sleep(2)
    return out

def comp(p, tipo, short=False):
    for c in p.get("addressComponents", []):
        if tipo in c.get("types", []):
            return c.get("shortText" if short else "longText", "")
    return ""

def endereco(p):
    rua, num, bairro = comp(p, "route", True), comp(p, "street_number"), comp(p, "sublocality") or comp(p, "sublocality_level_1")
    cidade, uf, cep = comp(p, "administrative_area_level_2") or CIDADE, comp(p, "administrative_area_level_1", True) or UF, comp(p, "postal_code")
    if mesma_cidade(cidade, CIDADE): cidade = CIDADE   # 20/11: grava o nome do jeito cadastrado ("del-Rei" -> "del Rei")
    s = rua + (f", {num}" if num else "") + (f" - {bairro}" if bairro else "")
    s = (s + ", " if s else "") + f"{cidade} - {uf}" + (f", {cep}" if cep else "")
    return s, cidade

# ---------- checagem de site ----------
PLATAFORMAS = ['instagram.com','facebook.com','fb.com','wa.me','whatsapp.com','linktr.ee','ifood.com.br','business.site',
 'goo.gl','g.page','bit.ly','youtube.com','tiktok.com','linkedin.com','mercadolivre','shopee','booking.com','airbnb',
 'tripadvisor','anota.ai','goomer','menudino','aiqfome','beacons.ai','bio.link','taplink','wixsite.com','ola.click',
 'instadelivery','booksy','trinks.com','doctoralia','sites.google.com','m.me','cardapioweb','saipos','neemo','blogspot','wordpress.com','canva.site','webnode']

def dominio(u):
    return urlparse(u if "://" in u else "http://" + u).netloc.lower().replace("www.", "")

def plataforma(u):
    d = dominio(u)
    return d if any(p in d or p in u.lower() for p in PLATAFORMAS) else None

def checa_site(url):
    if not url: return "sem_site", "Sem site no Google"
    p = plataforma(url)
    if p: return "sem_site", f"Sem site próprio — só rede social/plataforma ({p})"
    try:
        r = requests.get(url, timeout=15, verify=False, allow_redirects=True,
                         headers={"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124 Safari/537.36"})
    except requests.exceptions.ConnectionError as e:
        return "site_fora", "Domínio não resolve (DNS)" if "resolve" in str(e).lower() or "getaddrinfo" in str(e) else "Servidor não responde"
    except requests.exceptions.Timeout:
        return "site_fora", "Não responde (timeout)"
    except Exception:
        return "site_fora", "Erro ao abrir o site"
    p = plataforma(r.url)
    if p: return "sem_site", f"Sem site próprio — só rede social/plataforma ({p})"
    if r.status_code >= 400: return "site_fora", f"Erro HTTP {r.status_code}"
    low = r.text[:400000].lower()
    if len(low.strip()) < 300: return "site_fora", "Página em branco"
    if len(low) < 60000:
        for k, m in [("domain is for sale", "Domínio à venda"), ("está à venda", "Domínio à venda"), ("parked", "Domínio estacionado"),
                     ("em construção", "Site em construção"), ("coming soon", "Site em construção"), ("suspended", "Hospedagem suspensa"),
                     ("index of /", "Página padrão do servidor"), ("welcome to nginx", "Página padrão do servidor")]:
            if k in low: return "site_fora", m
    probs = []
    if not r.url.startswith("https"): probs.append("sem HTTPS")
    if 'name="viewport"' not in low and "name='viewport'" not in low: probs.append("não é responsivo")
    if ".swf" in low: probs.append("usa Flash")
    anos = [int(a) for a in re.findall(r"(?:©|&copy;|copyright)[^<]{0,30}?((?:19|20)\d\d)(?!\d)", low)]
    if anos and max(anos) <= datetime.date.today().year - 5: probs.append(f"copyright de {max(anos)}")
    if probs: return "site_desatualizado", "; ".join(probs).capitalize()
    return "tem_site", "Site funcionando"

INICIO = [time.time()]

def barra(etapa, feito, total, extra=""):
    """Barra de progresso numa linha só (20/11): [######----] 60% 120/199 · 2:30 passados · faltam ~1:40."""
    pct = feito / total if total else 1
    gasto = time.time() - INICIO[0]
    falta = gasto / pct - gasto if pct > 0 else 0
    mmss = lambda x: f"{int(x // 60)}:{int(x % 60):02d}"
    cheio = int(pct * 30)
    linha = (f"\r{etapa} [{'#' * cheio}{'-' * (30 - cheio)}] {pct * 100:3.0f}% {feito}/{total}"
             f" · {mmss(gasto)} passados" + (f" · faltam ~{mmss(falta)}" if 0 < pct < 1 else "") + (f" · {extra}" if extra else ""))
    sys.stdout.write(linha.ljust(130)[:130]); sys.stdout.flush()
    if feito >= total: sys.stdout.write("\n")

def mesma_cidade(a, b):
    """Compara sem acento, hífen e maiúsculas ("São João del-Rei" = "São João del Rei")."""
    n = lambda x: re.sub(r"[^a-z0-9]", "", unicodedata.normalize("NFD", (x or "").lower()).encode("ascii", "ignore").decode())
    return n(a) == n(b)

ROTULO = {"sem_site": "Sem site", "site_fora": "Site fora do ar", "site_desatualizado": "Site desatualizado", "tem_site": "Tem site"}
e = lambda s: html.escape(str(s or ""), quote=True)

def main():
    if not KEY: sys.exit("Defina GOOGLE_MAPS_API_KEY")
    vistos, leads = set(), []
    resultados = []
    ex = cf.ThreadPoolExecutor(4)   # sem "with": o Ctrl+C não fica esperando as pesquisas em andamento
    try:
        futuros = [ex.submit(busca, t) for t in TERMOS]
        INICIO[0] = time.time()
        for i, f in enumerate(cf.as_completed(futuros), 1):
            resultados.append(f.result())
            barra("Pesquisando no Google", i, len(TERMOS), f"{PESQUISAS[0]} pesquisas")
        ex.shutdown()
    except KeyboardInterrupt:
        print("\nParado com Ctrl+C. Nada foi salvo. Pesquisas feitas:", PESQUISAS[0])
        os._exit(130)
    except Parar as erro:
        print("\n\nPAREI SEM SALVAR NADA:", erro)
        print("Pesquisas feitas antes de parar:", PESQUISAS[0])
        os._exit(2)
    for res in resultados:
        for p in res:
            end, cidade = endereco(p)
            if not mesma_cidade(cidade, CIDADE): continue
            nome = p.get("displayName", {}).get("text", "")
            chave = (nome.strip().lower(), end.strip().lower())
            if chave in vistos: continue
            vistos.add(chave)
            leads.append(dict(nome=nome, cat=p.get("primaryTypeDisplayName", {}).get("text", ""), end=end,
                              tel=p.get("nationalPhoneNumber", ""), site=p.get("websiteUri", ""),
                              nota=p.get("rating"), n=p.get("userRatingCount"), link=p.get("googleMapsUri", ""),
                              lat=(p.get("location") or {}).get("latitude"), lng=(p.get("location") or {}).get("longitude"),
                              status_google=p.get("businessStatus"), tem_horario=bool(p.get("regularOpeningHours"))))
    # etiqueta Ativo / Confirmar / Inativo (mesmas regras e textos das listas que já estão no Acha Fácil)
    for l in leads:
        if l["status_google"] == "CLOSED_PERMANENTLY": l["ativo"], l["ativo_mot"] = "inativo", "Fechado permanentemente no Google"
        elif l["status_google"] == "CLOSED_TEMPORARILY": l["ativo"], l["ativo_mot"] = "confirmar", "Fechado temporariamente no Google"
        else:
            falta = [x for x, ok in (("horário", l["tem_horario"]), ("telefone", bool(l["tel"])), ("avaliações", bool(l["n"]))) if not ok]
            if len(falta) >= 2 or (not l["tem_horario"] and not l["n"]):
                l["ativo"], l["ativo_mot"] = "confirmar", "Sem " + ", sem ".join(falta[:-1]) + (" e sem " if len(falta) > 1 else "") + falta[-1] + " no Google"
            else:
                l["ativo"], l["ativo_mot"] = "ativo", "Perfil ativo no Google (horário/avaliações)"
    print(len(leads), "comércios únicos; checando sites...")
    ex = cf.ThreadPoolExecutor(24)
    try:
        futuros = {ex.submit(checa_site, l["site"]): l for l in leads}
        INICIO[0] = time.time()
        for i, f in enumerate(cf.as_completed(futuros), 1):
            futuros[f]["st"], futuros[f]["mot"] = f.result()
            barra("Conferindo os sites  ", i, len(leads))
        ex.shutdown()
    except KeyboardInterrupt:
        print("\nParado com Ctrl+C. Nada foi salvo. Pesquisas feitas:", PESQUISAS[0])
        os._exit(130)
    linhas = []
    for l in sorted(leads, key=lambda l: (l["cat"], l["nome"])):
        dig = re.sub(r"\D", "", l["tel"])
        wa = f'<a class="wa" href="https://wa.me/55{dig}" target="_blank">WhatsApp</a>' if len(dig) == 11 and dig[2] == "9" else '<span class="muted">—</span>'
        site = f'<a href="{e(l["site"])}" target="_blank">{e(dominio(l["site"]))}</a>' if l["site"] else '<span class="muted">—</span>'
        nota = f'{l["nota"]:.1f}'.replace(".", ",") + f' ({l["n"] or 0})' if l["nota"] else ""
        busca_ig = "https://www.google.com/search?q=" + quote_plus(f'{l["nome"]} {CIDADE} - {UF} instagram')
        linhas.append(
            f'<tr data-status="{l["st"]}" data-cat="{e(l["cat"])}" data-ativo="{l["ativo"]}"'
            + (f' data-lat="{l["lat"]:.7f}" data-lng="{l["lng"]:.7f}"' if l["lat"] is not None and l["lng"] is not None else '') + '> '
            f'<td><a class="nm" href="{e(l["link"])}" target="_blank">{e(l["nome"])}</a><div class="sub">{e(l["end"])}</div>'
            f'<span class="tag a-{l["ativo"]}" title="{e(l["ativo_mot"])}">{l["ativo"].capitalize()}</span></td> '
            f'<td>{e(l["cat"])}</td> '
            f'<td><span class="tag t-{l["st"]}">{ROTULO[l["st"]]}</span><div class="sub">{e(l["mot"])}</div></td> '
            f'<td class="nw">{e(l["tel"])}</td> <td>{wa}</td>'
            f'<td><a class="muted" target="_blank" href="{e(busca_ig)}">buscar</a></td><td>{site}</td> '
            f'<td class="nw">{nota}</td></tr>')
    hoje = datetime.date.today().strftime("%d/%m/%Y")
    doc = f"""<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Leads {e(CIDADE)}</title><style>body{{font-family:system-ui,sans-serif;margin:16px;color:#222}}table{{border-collapse:collapse;width:100%;font-size:14px}}
td,th{{border-bottom:1px solid #eee;padding:6px;text-align:left;vertical-align:top}}.muted{{color:#888}}.sub{{color:#777;font-size:12px}}.nw{{white-space:nowrap}}
.tag{{padding:2px 6px;border-radius:4px;font-size:12px}}.t-sem_site{{background:#fde2e2}}.t-site_fora{{background:#ffe8cc}}.t-site_desatualizado{{background:#fff5bf}}.t-tem_site{{background:#dff5e1}}.a-ativo{{background:#dff5e1}}.a-confirmar{{background:#fff5bf}}.a-inativo{{background:#eee;color:#666}}
a.nm{{font-weight:600;color:#1a56db;text-decoration:none}}a.wa{{color:#128c4b}}</style></head><body>
<h1>Leads — {e(CIDADE)}</h1>
<div class="muted">{e(CIDADE)} - {UF} · atualizado em {hoje} · fonte: Google Maps</div>
<table><thead><tr><th>Comércio</th><th>Categoria</th><th>Status</th><th>Telefone</th><th>WhatsApp</th><th>Instagram</th><th>Site</th><th>Nota</th></tr></thead>
<tbody>
{chr(10).join(linhas)}
</tbody></table></body></html>"""
    nome_arq = "leads_" + re.sub(r"\W+", "_", CIDADE.lower()) + ("_novos" if SO_NOVOS else "") + ".html"
    open(nome_arq, "w", encoding="utf-8").write(doc)
    from collections import Counter
    c = Counter(l["st"] for l in leads)
    a = Counter(l["ativo"] for l in leads)
    print("Pesquisas feitas no Google:", PESQUISAS[0])
    print("Ativo:", a.get("ativo", 0), "· Confirmar:", a.get("confirmar", 0), "· Inativo:", a.get("inativo", 0))
    print(nome_arq, "| total", len(leads), "|", " · ".join(f"{ROTULO[k]}: {c.get(k, 0)}" for k in ROTULO),
          "| com ponto no mapa:", sum(1 for l in leads if l["lat"] is not None), "| fechados de vez:", sum(1 for l in leads if l["ativo"] == "inativo"))

if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:   # Ctrl+C: sai na hora, sem salvar e sem esperar as pesquisas em andamento
        print("\nParado com Ctrl+C. Nada foi salvo. Pesquisas feitas:", PESQUISAS[0])
        os._exit(130)
