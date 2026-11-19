#!/usr/bin/env python3
"""Gera servidor/dados/mapas/<nome>.json (desenho dos municípios de um ou mais estados, na mesma projeção).
Uso: python3 scripts/gerar-mapas.py <saida.json> <UF>:<malha.json>:<nomes.json>:<contorno.json> [<UF>:... mais estados]
Malha:    https://servicodados.ibge.gov.br/api/v3/malhas/estados/31?formato=application/vnd.geo+json&intrarregiao=municipio&qualidade=intermediaria
Contorno: https://servicodados.ibge.gov.br/api/v3/malhas/estados/31?formato=application/vnd.geo+json&qualidade=intermediaria
Nomes:    https://servicodados.ibge.gov.br/api/v1/localidades/estados/31/municipios   (31 = MG, 35 = SP)"""
import json, math, sys
saida, estados = sys.argv[1], [a.split(':') for a in sys.argv[2:]]
def poligonos(geo): return geo['coordinates'] if geo['type'] == 'MultiPolygon' else [geo['coordinates']]
dados = [(uf, json.load(open(m)), {str(x['id']): x['nome'] for x in json.load(open(n))}, json.load(open(c))) for uf, m, n, c in estados]
pts = [p for _, g, _, _ in dados for f in g['features'] for poly in poligonos(f['geometry']) for anel in poly for p in anel]
x0, x1 = min(p[0] for p in pts), max(p[0] for p in pts); y0, y1 = min(p[1] for p in pts), max(p[1] for p in pts)
k = math.cos(math.radians((y0 + y1) / 2)); L = 1000 / ((x1 - x0) * k)   # 1000 de largura
def xy(p): return round((p[0] - x0) * k * L, 1), round((y1 - p[1]) * L, 1)
def desenho(geo):
    d = []; sx = sy = n = 0
    for poly in poligonos(geo):
        for anel in poly:
            q = []
            for p in anel:
                c = xy(p)
                if not q or c != q[-1]: q.append(c)
            if len(q) < 3: continue
            d.append('M' + 'L'.join(f'{a:g},{b:g}' for a, b in q) + 'Z')
            for a, b in q: sx += a; sy += b; n += 1
    return ''.join(d), round(sx / max(n, 1)), round(sy / max(n, 1))
out, contornos = [], []
for uf, g, nm, cont in dados:
    for f in g['features']:
        cod = str(f['properties']['codarea']); d, cx, cy = desenho(f['geometry'])
        out.append({'c': cod, 'u': uf, 'n': nm.get(cod, cod), 'd': d, 'x': cx, 'y': cy})
    contornos.append({'u': uf, 'd': ''.join(desenho(f['geometry'])[0] for f in cont['features'])})
json.dump({'ufs': [e[0] for e in estados], 'w': 1000, 'h': round((y1 - y0) * L), 'lon0': x0, 'lat1': y1, 'k': k, 'L': L,
           'e': contornos, 'm': sorted(out, key=lambda m: (m['n'], m['u']))}, open(saida, 'w'), ensure_ascii=False, separators=(',', ':'))
print(saida, len(out), 'municípios')
