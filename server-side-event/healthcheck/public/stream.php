<?php
/**
 * Endpoint de Server-Sent Events — MOCK de healthcheck.
 *
 * Mantem um estado em memoria de alguns servicos e, a cada 5-10s, muda
 * aleatoriamente o status de um deles, emitindo eventos para o cliente.
 */

// ---- Headers obrigatorios do protocolo SSE ----
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // par do fastcgi_buffering off no nginx

// Nada pode ficar preso em buffer antes de sair.
while (ob_get_level() > 0) {
    ob_end_flush();
}

/**
 * Envia uma mensagem SSE.
 * Formato: "event: <nome>\nid: <n>\ndata: <json>\n\n"
 */
function send(string $event, array $data, int $id): void
{
    echo "event: {$event}\n";
    echo "id: {$id}\n";
    echo 'data: ' . json_encode($data) . "\n\n";
    flush();
}

// ---- Severidade: numero maior = pior. Usado para calcular o status geral. ----
const SEVERITY = [
    'operational'  => 0,
    'maintenance'  => 1,
    'degraded'     => 2,
    'partial'      => 3,
    'major'        => 4,
];

const LABELS = [
    'operational'  => 'Operational',
    'maintenance'  => 'Under Maintenance',
    'degraded'     => 'Degraded Performance',
    'partial'      => 'Partial Outage',
    'major'        => 'Major Outage',
];

const OVERALL_TITLES = [
    'operational'  => 'All Systems Operational',
    'maintenance'  => 'Scheduled Maintenance',
    'degraded'     => 'Degraded Performance',
    'partial'      => 'Partial System Outage',
    'major'        => 'Major System Outage',
];

// ---- Estado inicial dos servicos mockados ----
$services = [
    'api'     => ['name' => 'API Gateway',      'status' => 'operational', 'uptime' => 99.98],
    'db'      => ['name' => 'Database Cluster', 'status' => 'operational', 'uptime' => 99.94],
    'storage' => ['name' => 'Object Storage',   'status' => 'operational', 'uptime' => 99.87],
    'email'   => ['name' => 'Email Delivery',   'status' => 'operational', 'uptime' => 99.70],
    'cdn'     => ['name' => 'CDN Edge Nodes',   'status' => 'operational', 'uptime' => 99.99],
];

$allStatuses = array_keys(SEVERITY);
$eventId = 0;

/** Calcula o status geral a partir do pior componente. */
function overallStatus(array $services): string
{
    $worst = 'operational';
    foreach ($services as $s) {
        if (SEVERITY[$s['status']] > SEVERITY[$worst]) {
            $worst = $s['status'];
        }
    }
    return $worst;
}

/** Monta o payload do banner geral. */
function overallPayload(array $services): array
{
    $status = overallStatus($services);
    return [
        'status' => $status,
        'title'  => OVERALL_TITLES[$status],
        'time'   => date('H:i:s'),
    ];
}

// ---- Snapshot inicial: manda todos os componentes de uma vez ----
send('snapshot', [
    'components' => array_map(
        fn ($id, $s) => [
            'id'     => $id,
            'name'   => $s['name'],
            'status' => $s['status'],
            'uptime' => number_format($s['uptime'], 2),
            'label'  => LABELS[$s['status']],
        ],
        array_keys($services),
        array_values($services)
    ),
], ++$eventId);

send('overall', overallPayload($services), ++$eventId);

// ============================================================
//  Loop principal do mock
// ============================================================
while (true) {
    // Se o cliente fechou a aba, libera o worker do php-fpm.
    if (connection_aborted()) {
        break;
    }

    // Espera aleatoria entre 5 e 10 segundos.
    sleep(random_int(5, 10));

    // Escolhe um servico e sorteia um novo status (diferente do atual).
    $id  = array_rand($services);
    $old = $services[$id]['status'];
    do {
        $new = $allStatuses[array_rand($allStatuses)];
    } while ($new === $old);

    $services[$id]['status'] = $new;

    // Uptime cai um tiquinho quando degrada, sobe devagar quando recupera.
    $delta = $new === 'operational' ? random_int(1, 4) / 100 : -random_int(1, 8) / 100;
    $services[$id]['uptime'] = max(90, min(100, $services[$id]['uptime'] + $delta));

    // 1) Atualiza o componente que mudou.
    send('component', [
        'id'     => $id,
        'name'   => $services[$id]['name'],
        'status' => $new,
        'uptime' => number_format($services[$id]['uptime'], 2),
        'label'  => LABELS[$new],
    ], ++$eventId);

    // 2) Recalcula e atualiza o banner geral.
    send('overall', overallPayload($services), ++$eventId);

    // 3) Registra no feed de eventos ao vivo.
    $worsened = SEVERITY[$new] > SEVERITY[$old];
    send('incident', [
        'service' => $services[$id]['name'],
        'status'  => $new,
        'message' => sprintf(
            '%s %s: %s',
            $services[$id]['name'],
            $worsened ? 'degradou para' : 'mudou para',
            LABELS[$new]
        ),
        'time'    => date('H:i:s'),
    ], ++$eventId);
}
