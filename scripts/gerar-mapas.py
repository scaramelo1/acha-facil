#!/usr/bin/env python3
"""Gera servidor/dados/mapas/<UF>.json (desenho dos municípios) a partir da malha do IBGE.
Uso: python3 scripts/gerar-mapas.py <malha.geojson> <nomes.json> <UF> <saida.json>
Malha:  https://servicodados.ibge.gov.br/api/v3/malhas/estados/31?formato=application/vnd.geo+json&intrarregiao=municipio&qualidade=intermediaria
Nomes:  https://servicodados.ibge.gov.br/api/v1/localidades/estados/31/municipios   (31 = MG, 35 = SP)"""
import json, math, sys
malha, nomes, uf, saida = sys.argv[1:5]
g = json.load(open(malha)); nm = {str(m['id']): m['nome'] for m in json.load(open(nomes))}
pts = [p for f in g['features'] for poly in (f['geometry']['coordinates'] if f['geometry']['type'] == 'MultiPolygon' else [f['geometry']['coordinates']]) for anel in poly for p in anel]
x0, x1 = min(p[0] for p in pts), max(p[0] for p in pts); y0, y1 = min(p[1] for p in pts), max(p[1] for p in pts)
k = math.cos(math.radians((y0 + y1) / 2)); L = 1000 / ((x1 - x0) * k)   # 1000 de largura
def xy(p): return round((p[0] - x0) * k * L, 1), round((y1 - p[1]) * L, 1)
out = []
for f in g['features']:
    cod = str(f['properties']['codarea']); geo = f['geometry']
    polys = geo['coordinates'] if geo['type'] == 'MultiPolygon' else [geo['coordinates']]
    d = []; sx = sy = n = 0
    for poly in polys:
        for anel in poly:
            q = []
            for p in anel:
                c = xy(p)
                if not q or c != q[-1]: q.append(c)
            if len(q) < 3: continue
            d.append('M' + 'L'.join(f'{a:g},{b:g}' for a, b in q) + 'Z')
            for a, b in q: sx += a; sy += b; n += 1
    out.append({'c': cod, 'n': nm.get(cod, cod), 'd': ''.join(d), 'x': round(sx / n), 'y': round(sy / n)})
json.dump({'uf': uf, 'w': 1000, 'h': round((y1 - y0) * L), 'lon0': x0, 'lat1': y1, 'k': k, 'L': L, 'm': sorted(out, key=lambda m: m['n'])},
          open(saida, 'w'), ensure_ascii=False, separators=(',', ':'))
print(uf, len(out), 'municípios')
