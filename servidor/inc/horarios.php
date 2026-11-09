<?php
// Horário de funcionamento por campo (mudança 015): {"semana": {...}, "sab": {...}, "dom": {...}, "fer": {...}}
// Cada campo: {"sit": "aberto", "abre": "08:00", "fecha": "18:00"} | {"sit": "fechado"} | {"sit": "24h"}.
// Campo ausente = não informado. "fecha" menor que "abre" = fecha depois da meia-noite (ex.: 11:00 às 03:00).
declare(strict_types=1);

const HORARIO_CAMPOS = ['semana' => 'Segunda a sexta', 'sab' => 'Sábado', 'dom' => 'Domingo', 'fer' => 'Feriados'];

/** Lê a coluna horarios (JSON) já conferida. */
function horarios_ler(?string $json): array {
    $h = $json ? json_decode($json, true) : null;
    if (!is_array($h)) return [];
    $ok = [];
    foreach (HORARIO_CAMPOS as $c => $_) {
        $v = $h[$c] ?? null;
        if (!is_array($v)) continue;
        if (in_array($v['sit'] ?? '', ['fechado', '24h'], true)) $ok[$c] = ['sit' => $v['sit']];
        elseif (($v['sit'] ?? '') === 'aberto' && hora_valida($v['abre'] ?? '') && hora_valida($v['fecha'] ?? '')) $ok[$c] = ['sit' => 'aberto', 'abre' => $v['abre'], 'fecha' => $v['fecha']];
    }
    return $ok;
}
function hora_valida($h): bool { return is_string($h) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $h) === 1; }

/** Horários vindos do formulário (hor[campo][sit|abre|fecha]). Devolve [JSON ou null se nada informado, erro]. */
function horarios_do_post(array $post): array {
    $h = [];
    foreach (HORARIO_CAMPOS as $c => $nome) {
        $v = (array)($post[$c] ?? []);
        $sit = (string)($v['sit'] ?? '');
        if ($sit === 'fechado' || $sit === '24h') $h[$c] = ['sit' => $sit];
        elseif ($sit === 'aberto') {
            $abre = (string)($v['abre'] ?? ''); $fecha = (string)($v['fecha'] ?? '');
            if (!hora_valida($abre) || !hora_valida($fecha)) return [null, "$nome: preencha a hora que abre e a que fecha."];
            if ($abre === $fecha) return [null, "$nome: a hora que abre e a que fecha estão iguais (se não fecha, escolha 24 horas)."];
            $h[$c] = ['sit' => 'aberto', 'abre' => $abre, 'fecha' => $fecha];
        }
    }
    return [$h ? json_encode($h) : null, null];
}

/** "08:00 às 18:00", "Fechado" ou "Aberto 24 horas". */
function horario_campo_texto(array $v): string {
    return match ($v['sit']) { 'fechado' => 'Fechado', '24h' => 'Aberto 24 horas', default => $v['abre'] . ' às ' . $v['fecha'] };
}

/** Uma linha por campo: "Segunda a sexta: 08:00 às 18:00". */
function horarios_linhas(array $h): array {
    $l = [];
    foreach (HORARIO_CAMPOS as $c => $nome) if (isset($h[$c])) $l[] = $nome . ': ' . horario_campo_texto($h[$c]);
    return $l;
}
function horarios_texto(array $h): ?string { return $h ? implode(' · ', horarios_linhas($h)) : null; }

/** Para o app: lista na ordem dos campos (o app mostra cada um numa linha e calcula se está aberto agora). */
function horarios_api(array $h): ?array {
    if (!$h) return null;
    $l = [];
    foreach (HORARIO_CAMPOS as $c => $nome) {
        if (!isset($h[$c])) continue;
        $l[] = ['campo' => $c, 'nome' => $nome, 'texto' => horario_campo_texto($h[$c]), 'situacao' => $h[$c]['sit'],
            'abre' => $h[$c]['abre'] ?? null, 'fecha' => $h[$c]['fecha'] ?? null];
    }
    return $l;
}

/** Campos do formulário (painel do dono e incluir negócio), preenchidos com $h. */
function horarios_form(array $h): string {
    ob_start(); ?>
<fieldset class="horarios">
  <legend>Horário de funcionamento</legend>
  <p class="dica">Escolha em cada linha. Se fecha depois da meia-noite, é só pôr a hora (ex.: abre 11:00, fecha 03:00).</p>
  <?php foreach (HORARIO_CAMPOS as $c => $nome): $v = $h[$c] ?? null; $sit = $v['sit'] ?? ''; ?>
  <div class="horario-linha">
    <label for="hor-<?= $c ?>"><?= h($nome) ?></label>
    <select id="hor-<?= $c ?>" name="hor[<?= $c ?>][sit]" data-horario>
      <option value=""<?= $sit === '' ? ' selected' : '' ?>>Não informar</option>
      <option value="aberto"<?= $sit === 'aberto' ? ' selected' : '' ?>>Aberto</option>
      <option value="fechado"<?= $sit === 'fechado' ? ' selected' : '' ?>>Fechado</option>
      <option value="24h"<?= $sit === '24h' ? ' selected' : '' ?>>24 horas</option>
    </select>
    <span class="horas"<?= $sit === 'aberto' ? '' : ' hidden' ?>>
      <input type="time" name="hor[<?= $c ?>][abre]" value="<?= h($v['abre'] ?? '') ?>" aria-label="<?= h($nome) ?>: abre às">
      <span>às</span>
      <input type="time" name="hor[<?= $c ?>][fecha]" value="<?= h($v['fecha'] ?? '') ?>" aria-label="<?= h($nome) ?>: fecha às">
    </span>
  </div>
  <?php endforeach ?>
</fieldset>
<script>
document.querySelectorAll('select[data-horario]').forEach(function (s) {
  s.addEventListener('change', function () { s.parentNode.querySelector('.horas').hidden = s.value !== 'aberto'; });
});
</script>
<?php return (string)ob_get_clean();
}
