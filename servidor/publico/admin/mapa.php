<?php
// Mapa das cidades (19/11, pedido do usuário): MG e SP divididos por município, as cidades do projeto pintadas,
// para ver onde há espaço e onde as cidades se amontoam. Só administradores (exigir_admin).
// Desenho dos municípios: dados/mapas/<UF>.json (e SP-MG.json, os dois juntos), gerado da malha do IBGE por scripts/gerar-mapas.py.
require __DIR__ . '/../../inc/admin.php';
$admin = exigir_admin();
const MAPA_UFS = ['SP-MG' => 'SP e MG juntos', 'SP' => 'São Paulo', 'MG' => 'Minas Gerais'];   // 19/11: juntos é o padrão (pedido do usuário)
$uf = strtoupper((string)($_GET['uf'] ?? 'SP-MG'));
if (!isset(MAPA_UFS[$uf])) $uf = 'SP-MG';
$ufs = explode('-', $uf);
$juntos = count($ufs) > 1;
$mapa = json_decode((string)file_get_contents(__DIR__ . "/../../dados/mapas/$uf.json"), true);

$tem_tabela = (bool)valor("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'cidades'");
$cidades = $tem_tabela ? linhas("SELECT c.slug, c.nome, c.uf, c.ativa, c.lat, c.lng,
    (SELECT COUNT(*) FROM profissionais p WHERE p.cidade = c.slug AND p.status = 'ativo') AS comercios
    FROM cidades c ORDER BY c.nome") : [];
$contagem = array_fill_keys(array_keys(MAPA_UFS), [0, 0]);
foreach ($cidades as $c) foreach (array_keys(MAPA_UFS) as $v) if (in_array($c['uf'], explode('-', $v), true)) $contagem[$v][$c['ativa'] ? 0 : 1]++;

/** Sem acento e minúsculo, para casar o nome do banco com o do IBGE. */
function mapa_chave(string $s): string {
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ü'=>'u','ç'=>'c']);
    return trim(preg_replace('/[^a-z0-9]+/', '-', $s), '-');
}
// Cidades do projeto (dos dois estados: a mais perto pode estar do outro lado da divisa), para o JavaScript. Nome igual ao do IBGE casa direto; senão (distrito como Monte Verde, que
// fica em Camanducaia) o navegador acha o município pelo centro (lat/lng) da cidade.
$doEstado = [];
foreach ($cidades as $c) {
    $doEstado[] = ['slug' => $c['slug'], 'nome' => $c['nome'], 'uf' => $c['uf'], 'aqui' => in_array($c['uf'], $ufs, true), 'chave' => mapa_chave($c['nome']), 'ativa' => (int)$c['ativa'],
        'comercios' => (int)$c['comercios'], 'lat' => $c['lat'] !== null ? (float)$c['lat'] : null, 'lng' => $c['lng'] !== null ? (float)$c['lng'] : null,
        'link' => $c['ativa'] && isset(CIDADES[$c['slug']]) ? url_publica(cidade_web($c['slug']) . '/') : null];
}
$proj = ['lon0' => $mapa['lon0'], 'lat1' => $mapa['lat1'], 'k' => $mapa['k'], 'L' => $mapa['L']];

admin_inicio('Mapa das cidades', $admin);
?>
<style>
.mapa-abas{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0 12px}
.mapa-caixa{position:relative;background:#fff;border:1px solid #dfe7e2;border-radius:14px;overflow:hidden;touch-action:none}
.mapa-caixa svg{display:block;width:100%;height:min(78vh,820px);cursor:grab;user-select:none}
.mapa-caixa svg.arrastando{cursor:grabbing}
.mapa-caixa path{fill:#eef1ef;stroke:#b9c4be;stroke-width:.35;vector-effect:non-scaling-stroke}
.mapa-caixa path:hover{fill:#d9e2dd}
.mapa-caixa path.ligada{fill:#0a8f55}.mapa-caixa path.ligada:hover{fill:#005C38}
.mapa-caixa path.desligada{fill:#F7C600}.mapa-caixa path.desligada:hover{fill:#d9ad00}
.mapa-caixa path.estado{fill:none;stroke:#173b2b;stroke-width:1.6;pointer-events:none}
.mapa-caixa path.escolhida{stroke:#c0392b;stroke-width:2.5}
.mapa-caixa text{font:700 9px Nunito,sans-serif;fill:#173b2b;paint-order:stroke;stroke:#fff;stroke-width:2.5px;pointer-events:none;text-anchor:middle}
.mapa-zoom{position:absolute;right:10px;top:10px;display:flex;flex-direction:column;gap:6px}
.mapa-zoom button{width:38px;height:38px;border-radius:10px;border:1px solid #cfd9d3;background:#fff;font:800 20px Nunito,sans-serif;cursor:pointer}
.mapa-dica{position:absolute;pointer-events:none;background:#173b2b;color:#fff;padding:6px 10px;border-radius:8px;font-size:.85rem;white-space:nowrap;display:none;z-index:2}
.mapa-legenda{display:flex;gap:16px;flex-wrap:wrap;align-items:center;margin:10px 0}
.mapa-legenda i{display:inline-block;width:14px;height:14px;border-radius:4px;vertical-align:-2px;margin-right:5px;border:1px solid #b9c4be}
.mapa-ferramentas{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin:10px 0}
.mapa-ferramentas input[type=search]{min-width:16em}
#mapa-info{min-height:3em}
</style>

<h1>Mapa das cidades</h1>
<p class="discreto">Cada pedaço é um município. Verde = cidade ligada no app, amarelo = cadastrada mas desligada. Toque num
  município para ver a distância até a cidade do projeto mais perto.</p>

<div class="mapa-abas">
  <?php foreach (MAPA_UFS as $sigla => $nomeUf): ?>
  <a class="botao<?= $sigla === $uf ? '' : ' contorno' ?>" href="?uf=<?= $sigla ?>"><?= h($nomeUf) ?>
    <small>(<?= $contagem[$sigla][0] ?> ligada<?= $contagem[$sigla][0] === 1 ? '' : 's' ?><?= $contagem[$sigla][1] ? ', ' . $contagem[$sigla][1] . ' desligada' . ($contagem[$sigla][1] === 1 ? '' : 's') : '' ?>)</small></a>
  <?php endforeach ?>
</div>

<div class="mapa-legenda">
  <span><i style="background:#0a8f55"></i>Ligada no app</span>
  <span><i style="background:#F7C600"></i>Cadastrada, desligada</span>
  <span><i style="background:#eef1ef"></i>Fora do projeto</span>
  <span><?php if ($juntos): ?><span><i style="background:#fff;border:2px solid #173b2b"></i>Divisa dos estados</span><?php endif ?>
  <span><?= number_format(count($mapa['m']), 0, ',', '.') ?> municípios<?= $juntos ? '' : ' em ' . h(MAPA_UFS[$uf]) ?></span>
</div>

<div class="mapa-ferramentas">
  <input type="search" id="mapa-busca" list="mapa-nomes" placeholder="Procurar município..." aria-label="Procurar município">
  <datalist id="mapa-nomes"><?php foreach ($mapa['m'] as $m): ?><option value="<?= h($m['n'] . ($juntos ? ' - ' . $m['u'] : '')) ?>"><?php endforeach ?></datalist>
  <label class="marcar"><input type="checkbox" id="mapa-nomes-ver"> Mostrar os nomes das cidades do projeto</label>
</div>

<div class="quadro" id="mapa-info">Toque num município.</div>

<div class="mapa-caixa">
  <svg id="mapa" viewBox="0 0 <?= (int)$mapa['w'] ?> <?= (int)$mapa['h'] ?>" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Mapa de <?= h($juntos ? 'São Paulo e Minas Gerais' : MAPA_UFS[$uf]) ?> por município">
    <g id="mapa-municipios"><?php foreach ($mapa['m'] as $i => $m): ?><path data-i="<?= $i ?>" d="<?= h($m['d']) ?>"/><?php endforeach ?></g>
    <g id="mapa-estados"><?php foreach ($mapa['e'] as $e): ?><path class="estado" d="<?= h($e['d']) ?>"/><?php endforeach ?></g>
    <g id="mapa-rotulos"></g>
  </svg>
  <div class="mapa-zoom"><button type="button" data-z="1.5" title="Aproximar">+</button><button type="button" data-z="0.667" title="Afastar">−</button><button type="button" data-z="0" title="Ver tudo">⤢</button></div>
  <div class="mapa-dica" id="mapa-dica"></div>
</div>

<h2>Cidades do projeto<?= $juntos ? '' : ' em ' . h(MAPA_UFS[$uf]) ?></h2>
<div class="tabela"><table id="mapa-tabela">
  <tr><th>Cidade</th><th>No app</th><th>Comércios</th><th>Cidade do projeto mais perto</th></tr>
</table></div>

<script>
(function () {
  const MUN = <?= json_encode(array_map(fn($m) => ['n' => $m['n'], 'u' => $m['u'], 'x' => $m['x'], 'y' => $m['y']], $mapa['m']), JSON_UNESCAPED_UNICODE) ?>;
  const CID = <?= json_encode($doEstado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const P = <?= json_encode($proj) ?>;
  const JUNTOS = <?= $juntos ? 'true' : 'false' ?>;
  const W = <?= (int)$mapa['w'] ?>, H = <?= (int)$mapa['h'] ?>;
  const svg = document.getElementById('mapa'), dica = document.getElementById('mapa-dica'), info = document.getElementById('mapa-info');
  const paths = [...document.querySelectorAll('#mapa-municipios path')];
  const KM = 111.32;                                   // km por grau de latitude
  const px = (lat, lng) => [(lng - P.lon0) * P.k * P.L, (P.lat1 - lat) * P.L];
  const chave = s => s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  const esc = s => String(s).replace(/[&<>"]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
  const num = n => n.toLocaleString('pt-BR');

  // cidade do projeto -> município (pelo nome; senão pelo centro dentro do desenho)
  const nomeMun = m => JUNTOS ? m.n + ' - ' + m.u : m.n;
  const porChave = {}, porBusca = {}; MUN.forEach((m, i) => { porChave[m.u + '|' + chave(m.n)] = i; porBusca[chave(nomeMun(m))] = i; });
  const doMun = {};   // índice do município -> cidades do projeto nele
  CID.forEach(c => {
    if (!c.aqui) { if (c.lat !== null) [c.x, c.y] = px(c.lat, c.lng); c.nome += ' - ' + c.uf; return; }
    if (JUNTOS) c.nome += ' - ' + c.uf;
    let i = porChave[c.uf + '|' + c.chave];
    if (i === undefined && c.lat !== null) {
      const [x, y] = px(c.lat, c.lng), pt = svg.createSVGPoint(); pt.x = x; pt.y = y;
      i = paths.findIndex(p => p.isPointInFill(pt));
      if (i < 0) i = undefined;
    }
    c.i = i;
    if (i === undefined) return;
    (doMun[i] ||= []).push(c);
    // posição: o centro da cidade, ou o meio do município
    [c.x, c.y] = c.lat !== null ? px(c.lat, c.lng) : [MUN[i].x, MUN[i].y];
  });
  Object.entries(doMun).forEach(([i, cs]) => paths[i].classList.add(cs.some(c => c.ativa) ? 'ligada' : 'desligada'));

  const distKm = (ax, ay, bx, by) => Math.hypot(ax - bx, ay - by) / P.L * KM;
  function maisPerto(x, y, fora) {
    let melhor = null;
    CID.forEach(c => { if (c.x === undefined || c === fora || (c.aqui && c.i === fora?.i)) return;
      const d = distKm(x, y, c.x, c.y); if (!melhor || d < melhor.d) melhor = {c, d}; });
    return melhor;
  }
  const situacao = c => c.ativa ? 'ligada' : 'desligada';

  // rótulos
  const NS = 'http://www.w3.org/2000/svg', gRot = document.getElementById('mapa-rotulos');
  function desenharRotulos() {
    gRot.innerHTML = '';
    if (!document.getElementById('mapa-nomes-ver').checked) return;
    const z = escala();
    CID.forEach(c => { if (c.x === undefined) return;
      const t = document.createElementNS(NS, 'text'); t.setAttribute('x', c.x); t.setAttribute('y', c.y - 4 / z);
      t.style.fontSize = (9 / z) + 'px'; t.style.strokeWidth = (2.5 / z) + 'px'; t.textContent = c.nome; gRot.appendChild(t); });
  }

  // tabela
  const tab = document.getElementById('mapa-tabela');
  CID.filter(c => c.aqui).sort((a, b) => b.ativa - a.ativa || a.nome.localeCompare(b.nome)).forEach(c => {
    const tr = tab.insertRow(), perto = c.i !== undefined ? maisPerto(c.x, c.y, c) : null;
    tr.innerHTML = `<td><a href="#" data-i="${c.i ?? ''}"><strong>${esc(c.nome)}</strong></a>${c.i === undefined ? ' <small>(não achei no mapa)</small>' : ''}</td>`
      + `<td>${c.ativa ? '✅ Ligada' : '⏸️ Desligada'}</td><td>${num(c.comercios)}</td>`
      + `<td>${perto ? esc(perto.c.nome) + ' - ' + Math.round(perto.d) + ' km' : '-'}</td>`;
  });
  tab.addEventListener('click', e => { const a = e.target.closest('a[data-i]'); if (!a || a.dataset.i === '') return; e.preventDefault(); escolher(+a.dataset.i, true); svg.scrollIntoView({behavior: 'smooth', block: 'center'}); });

  // escolher um município
  let escolhido = null;
  function escolher(i, centralizar) {
    if (escolhido !== null) paths[escolhido].classList.remove('escolhida');
    escolhido = i; paths[i].classList.add('escolhida');
    const m = MUN[i], cs = doMun[i] || [];
    let html = `<strong>${esc(nomeMun(m))}</strong>: `;
    if (cs.length) html += cs.map(c => `${c.nome !== nomeMun(m) ? esc(c.nome) + ' (fica aqui): ' : ''}${c.ativa ? '✅ ligada no app' : '⏸️ cadastrada, desligada'}, ${num(c.comercios)} comércios`
      + (c.link ? ` · <a href="${esc(c.link)}" target="_blank" rel="noopener">abrir no site</a>` : '')).join('; ');
    else html += 'fora do projeto';
    const perto = maisPerto(m.x, m.y, cs[0]);
    if (perto) html += `<br>Cidade do projeto mais perto: <strong>${esc(perto.c.nome)}</strong> (${situacao(perto.c)}), a ${Math.round(perto.d)} km em linha reta.`;
    info.innerHTML = html;
    if (centralizar) centrar(m.x, m.y, Math.max(escala(), 4));
  }

  // zoom e arrasto (viewBox)
  let vb = {x: 0, y: 0, w: W, h: H};
  const aplicar = () => { svg.setAttribute('viewBox', `${vb.x} ${vb.y} ${vb.w} ${vb.h}`); desenharRotulos(); };
  const escala = () => W / vb.w;
  function zoom(f, cx, cy) {
    const nw = Math.min(W * 1.2, Math.max(W / 40, vb.w / f)), r = nw / vb.w;
    cx ??= vb.x + vb.w / 2; cy ??= vb.y + vb.h / 2;
    vb = {x: cx - (cx - vb.x) * r, y: cy - (cy - vb.y) * r, w: nw, h: vb.h * r}; aplicar();
  }
  function centrar(x, y, z) { const w = W / z, h = H / z; vb = {x: x - w / 2, y: y - h / 2, w, h}; aplicar(); }
  const ponto = e => { const r = svg.getBoundingClientRect(), s = Math.max(vb.w / r.width, vb.h / r.height);
    const ox = (r.width * s - vb.w) / 2, oy = (r.height * s - vb.h) / 2;   // preserveAspectRatio: meio
    return [vb.x - ox + (e.clientX - r.left) * s, vb.y - oy + (e.clientY - r.top) * s, s]; };
  svg.addEventListener('wheel', e => { e.preventDefault(); const [x, y] = ponto(e); zoom(e.deltaY < 0 ? 1.25 : 0.8, x, y); }, {passive: false});
  document.querySelectorAll('.mapa-zoom button').forEach(b => b.addEventListener('click', () => +b.dataset.z ? zoom(+b.dataset.z) : (vb = {x: 0, y: 0, w: W, h: H}, aplicar())));

  const toques = new Map(); let arrasto = null, moveu = false, pinca = null;
  svg.addEventListener('pointerdown', e => { toques.set(e.pointerId, e); svg.setPointerCapture(e.pointerId); moveu = false;
    if (toques.size === 1) arrasto = {cx: e.clientX, cy: e.clientY, vb: {...vb}};
    else if (toques.size === 2) { const [a, b] = [...toques.values()]; pinca = {d: Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY)}; arrasto = null; } });
  svg.addEventListener('pointermove', e => {
    if (toques.has(e.pointerId)) toques.set(e.pointerId, e);
    if (pinca && toques.size === 2) { const [a, b] = [...toques.values()], d = Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
      const [x, y] = ponto({clientX: (a.clientX + b.clientX) / 2, clientY: (a.clientY + b.clientY) / 2}); zoom(d / pinca.d, x, y); pinca.d = d; moveu = true; return; }
    if (arrasto) { const dx = e.clientX - arrasto.cx, dy = e.clientY - arrasto.cy;
      if (Math.abs(dx) + Math.abs(dy) > 4) { moveu = true; svg.classList.add('arrastando'); }
      if (moveu) { const s = ponto(e)[2]; vb = {...arrasto.vb, x: arrasto.vb.x - dx * s, y: arrasto.vb.y - dy * s}; aplicar(); } }
    const p = e.target.closest?.('path[data-i]');
    if (p && e.pointerType === 'mouse' && !arrasto) { const i = +p.dataset.i, cs = doMun[i] || [], r = svg.parentNode.getBoundingClientRect();
      dica.textContent = nomeMun(MUN[i]) + (cs.length ? ': ' + cs.map(c => (c.nome !== nomeMun(MUN[i]) ? c.nome + ', ' : '') + (c.ativa ? 'ligada' : 'desligada') + ', ' + num(c.comercios) + ' comércios').join('; ') : '');
      dica.style.display = 'block'; dica.style.left = (e.clientX - r.left + 14) + 'px'; dica.style.top = (e.clientY - r.top + 14) + 'px'; }
    else if (!p) dica.style.display = 'none';
  });
  const soltar = e => { toques.delete(e.pointerId); if (toques.size < 2) pinca = null;
    if (toques.size === 0) { svg.classList.remove('arrastando');
      if (!moveu && e.type === 'pointerup') { const p = document.elementFromPoint(e.clientX, e.clientY)?.closest('path[data-i]'); if (p) escolher(+p.dataset.i, false); }
      arrasto = null; } };
  svg.addEventListener('pointerup', soltar); svg.addEventListener('pointercancel', soltar);
  svg.addEventListener('pointerleave', () => dica.style.display = 'none');

  document.getElementById('mapa-nomes-ver').addEventListener('change', desenharRotulos);
  document.getElementById('mapa-busca').addEventListener('change', e => { const i = porBusca[chave(e.target.value)]; if (i !== undefined) escolher(i, true); });

  desenharRotulos();
  // começa perto das cidades do projeto
  const pos = CID.filter(c => c.i !== undefined && c.aqui);
  if (pos.length) { const xs = pos.map(c => c.x), ys = pos.map(c => c.y), m = 60;
    const x0 = Math.min(...xs) - m, x1 = Math.max(...xs) + m, y0 = Math.min(...ys) - m, y1 = Math.max(...ys) + m;
    centrar((x0 + x1) / 2, (y0 + y1) / 2, Math.min(W / (x1 - x0), H / (y1 - y0), 6)); }
})();
</script>
<?php admin_fim();
