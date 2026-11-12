<?php
// Agenda de eventos para o app (passo 33; mesma tabela do site, passo 32).
//   GET eventos.php?cidade=<slug>          próximos eventos da cidade, em ordem de data
//   GET eventos.php?cidade=<slug>&id=<n>   um evento (com descrição, link oficial, Google Agenda e mapa)
// Datas já vão escritas em português (o app só mostra). Sem a mudança 020: lista vazia.
require __DIR__ . '/../../inc/api.php';
require_once __DIR__ . '/../../inc/eventos.php';
metodo('GET');

function evento_app(array $e, bool $completo): array {
    $i = strtotime($e['data_inicio']);
    $f = $e['data_fim'] && $e['data_fim'] !== $e['data_inicio'] ? strtotime($e['data_fim']) : null;
    $acontecendo = $e['data_inicio'] < hoje();
    $r = [
        'id' => (int)$e['id'],
        'titulo' => $e['titulo'],
        'data' => $e['data_inicio'],
        // grupo da lista: "Acontecendo agora", "Novembro", "Fevereiro de 2027"
        'grupo' => $acontecendo ? 'Acontecendo agora' : ucfirst(MESES[(int)date('n', $i)]) . (date('Y', $i) !== date('Y') ? ' de ' . date('Y', $i) : ''),
        // folhinha do calendário
        'semana' => mb_strtoupper(mb_substr(DIAS_SEMANA[(int)date('w', $i)], 0, 3)),
        'dia' => date('j', $i) . ($f ? ' a ' . date('j', $f) : ''),
        'mes' => mb_strtoupper(mb_substr(MESES[(int)date('n', $f ?: $i)], 0, 3)),
        'quando' => ucfirst(evento_quando($e)),
        'horario' => evento_horario($e),
        'local' => $e['local_nome'],
        'endereco' => $e['endereco'],
        'preco' => $e['preco'],
        'imagem' => $e['imagem'] ? foto_url($e['imagem']) : null,
        'terminou' => evento_terminou($e),
    ];
    if ($completo) {
        $nome_cidade = nome_cidade($e['cidade']); $uf = uf_cidade($e['cidade']);
        $onde = implode(', ', array_filter([$e['local_nome'], $e['endereco']]));
        $r += [
            'cidade' => "$nome_cidade - $uf",
            'descricao' => $e['descricao'],
            'linkOficial' => evento_link_oficial($e),
            'agenda' => evento_google_agenda($e),
            'mapa' => $onde !== '' ? 'https://www.google.com/maps/search/?' . http_build_query(['api' => 1, 'query' => "$onde, $nome_cidade - $uf"]) : null,
            'site' => url_publica(ltrim(url_evento($e), '/')),
        ];
    }
    return $r;
}

$cidade = (string)($_GET['cidade'] ?? CIDADE_PADRAO);
if (isset($_GET['id'])) {
    $e = evento((int)$_GET['id']);
    if (!$e) erro('Evento não encontrado. Pode ter sido tirado da agenda.', 404);
    responder(['evento' => evento_app($e, true)]);
}
if (!isset(CIDADES[$cidade])) erro('Cidade desconhecida.', 404);
responder(['eventos' => array_map(fn($e) => evento_app($e, false), eventos_proximos($cidade))]);
