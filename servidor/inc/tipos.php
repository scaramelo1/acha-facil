<?php
// Tipo do negócio escolhido numa caixa de seleção que se monta pela área (17/11, passos 94–96, pedido do usuário: evitar
// tipos repetidos). Usado em /incluir.php e em /admin/comercio.php. Fora da lista só marcando "Não encontrei o tipo".
declare(strict_types=1);

/** Tipos de cada área já usados por 3+ comércios ativos: [categoria_id => [tipo, ...]] em ordem alfabética. */
function tipos_por_area(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    foreach (linhas("SELECT categoria_id, tipo FROM profissionais WHERE status = 'ativo' AND tipo IS NOT NULL AND tipo <> ''
        GROUP BY categoria_id, tipo HAVING COUNT(*) >= 3 ORDER BY tipo") as $t) $cache[(int)$t['categoria_id']][] = $t['tipo'];
    return $cache;
}

/** Tipo da lista da área igual ao digitado (sem ligar para acento, maiúscula e espaços), ou null. */
function tipo_da_lista(array $tipos_area, int $area, string $tipo): ?string {
    $k = sem_acento(trim(preg_replace('/\s+/u', ' ', $tipo)));
    foreach ($tipos_area[$area] ?? [] as $t) if (sem_acento($t) === $k) return $t;
    return null;
}

/**
 * Lê o tipo do formulário (campos tipo, tipo_novo, tipo_outro). $aceitar = tipo que vale mesmo fora da lista (o atual
 * do comércio, no admin). Devolve [tipo, é_novo, erro].
 */
function tipo_do_post(array $tipos_area, int $area, ?string $aceitar = null): array {
    if (!empty($_POST['tipo_novo'])) {
        $t = trim(preg_replace('/\s+/u', ' ', (string)($_POST['tipo_outro'] ?? '')));
        $t = mb_substr($t, 0, 80);
        if ($t === '') return [null, false, 'Escreva qual é o tipo do negócio no campo que apareceu.'];
        if ($area && ($l = tipo_da_lista($tipos_area, $area, $t))) return [$l, false, null];   // já existia
        return [mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1), true, null];
    }
    $t = trim((string)($_POST['tipo'] ?? ''));
    if ($t === '') return [null, false, 'Escolha o tipo do negócio.'];
    if ($aceitar !== null && $t === $aceitar) return [$t, false, null];
    if ($area && ($l = tipo_da_lista($tipos_area, $area, $t))) return [$l, false, null];
    return [null, false, 'Escolha o tipo na caixa. Se não estiver lá, marque "Não encontrei o tipo na lista".'];
}

/**
 * Caixa de tipos + "Não encontrei o tipo na lista" + campo que aparece. $id_area = id do <select> da área na página.
 * $atual = tipo marcado; $extra = [area => tipo] incluído na lista (o tipo atual do comércio, no admin).
 */
function tipo_campos_html(array $tipos_area, string $id_area, string $atual, bool $novo, string $outro, array $extra = [],
                          string $rotulo_outro = 'Qual é o tipo do seu negócio? (a equipe confere)'): string {
    foreach ($extra as $a => $t) {
        if ($t === '' || in_array($t, $tipos_area[(int)$a] ?? [], true)) continue;
        $tipos_area[(int)$a][] = $t;
        sort($tipos_area[(int)$a], SORT_LOCALE_STRING);
    }
    ob_start(); ?>
      <label for="tipo">Tipo do negócio</label>
      <select id="tipo" name="tipo" data-valor="<?= h($novo ? '' : $atual) ?>">
        <option value="">Escolha a área primeiro</option>
      </select>
      <label class="marcar"><input type="checkbox" id="tipo_novo" name="tipo_novo" value="1"<?= $novo ? ' checked' : '' ?>> Não encontrei o tipo na lista</label>
      <div id="tipo-outro-caixa"<?= $novo ? '' : ' hidden' ?>>
        <label for="tipo_outro"><?= h($rotulo_outro) ?></label>
        <input id="tipo_outro" name="tipo_outro" maxlength="80" value="<?= h($outro) ?>">
      </div>
      <script>
      (function () {   // a caixa de tipos se monta sozinha com os tipos da área escolhida
        var tipos = <?= json_encode($tipos_area, JSON_UNESCAPED_UNICODE) ?>, area = document.getElementById(<?= json_encode($id_area) ?>),
            sel = document.getElementById('tipo'), novo = document.getElementById('tipo_novo'),
            caixa = document.getElementById('tipo-outro-caixa'), outro = document.getElementById('tipo_outro');
        function montar(manter) {
          var t = tipos[area.value] || [], antes = manter ? sel.dataset.valor : '';
          sel.innerHTML = '';
          var o = document.createElement('option'); o.value = '';
          o.textContent = area.value ? (t.length ? 'Escolha o tipo' : 'Nenhum tipo nesta área: marque abaixo') : 'Escolha a área primeiro';
          sel.appendChild(o);
          t.forEach(function (n) { var op = document.createElement('option'); op.value = n; op.textContent = n; if (n === antes) op.selected = true; sel.appendChild(op); });
          estado();
        }
        function estado() {
          var n = novo.checked;
          caixa.hidden = !n; outro.required = n;
          sel.disabled = n || !area.value; sel.required = !n;
          if (n) sel.value = '';
        }
        area.addEventListener('change', function () { montar(false); });
        novo.addEventListener('change', function () { estado(); if (novo.checked) outro.focus(); });
        montar(true);
      })();
      </script>
<?php return (string)ob_get_clean();
}
