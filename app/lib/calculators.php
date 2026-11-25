<?php
/**
 * Engineering calculators (hydraulics). The same formulas run in the browser (main.js, live) and
 * here (when JavaScript is off, or for links to a calculated result).
 */
declare(strict_types=1);

const CALC_STD_BORES = [25, 32, 40, 50, 63, 80, 100, 125, 140, 160, 180, 200, 220, 250, 280, 320];

/** @return array<string, array{title: string, intro: string, product: string, inputs: array, icon: string}> */
function calculators(): array
{
    return [
        'force' => ['title' => 'Cylinder force', 'icon' => 'arrow-up-from-line', 'product' => 'hydraulic-cylinders',
            'intro' => 'Push and pull force of a double-acting hydraulic cylinder.',
            'inputs' => ['bore' => ['Bore (piston) diameter', 'mm', 80], 'rod' => ['Rod diameter', 'mm', 45], 'pressure' => ['Working pressure', 'bar', 160]]],
        'speed' => ['title' => 'Cylinder speed', 'icon' => 'gauge', 'product' => 'hydraulic-power-packs',
            'intro' => 'Extend and retract speed and stroke time from the pump flow.',
            'inputs' => ['bore' => ['Bore diameter', 'mm', 80], 'rod' => ['Rod diameter', 'mm', 45], 'flow' => ['Pump flow', 'L/min', 20], 'stroke' => ['Stroke', 'mm', 500]]],
        'power' => ['title' => 'Pump motor power', 'icon' => 'zap', 'product' => 'hydraulic-pumps-motors',
            'intro' => 'Electric motor power needed to drive a hydraulic pump.',
            'inputs' => ['flow' => ['Flow', 'L/min', 40], 'pressure' => ['Pressure', 'bar', 200], 'efficiency' => ['Overall efficiency', '%', 85]]],
        'sizing' => ['title' => 'Cylinder sizing', 'icon' => 'ruler', 'product' => 'hydraulic-cylinders',
            'intro' => 'Smallest standard bore that lifts a load, with a safety factor.',
            'inputs' => ['load' => ['Load to lift', 'tonnes', 10], 'pressure' => ['Available pressure', 'bar', 160], 'factor' => ['Safety factor', '×', 1.25]]],
    ];
}

/** @return array<int, array{0: string, 1: string}> label => formatted value, or [] when the inputs are invalid */
function calc_run(string $type, array $in): array
{
    $n = fn($k) => max(0, (float) ($in[$k] ?? 0));
    $f = fn(float $v, int $dec = 1) => number_format($v, $dec);
    $area = fn(float $d) => M_PI / 4 * $d * $d; // mm²
    switch ($type) {
        case 'force':
            [$D, $d, $p] = [$n('bore'), $n('rod'), $n('pressure')];
            if ($D <= 0 || $p <= 0 || $d >= $D) return [];
            $push = $p / 10 * $area($D); $pull = $p / 10 * ($area($D) - $area($d)); // N (bar/10 = MPa = N/mm²)
            return [['Push force', $f($push / 1000) . ' kN (' . $f($push / 9806.65, 2) . ' t)'], ['Pull force', $f($pull / 1000) . ' kN (' . $f($pull / 9806.65, 2) . ' t)'],
                ['Piston area', $f($area($D) / 100, 2) . ' cm²']];
        case 'speed':
            [$D, $d, $q, $s] = [$n('bore'), $n('rod'), $n('flow'), $n('stroke')];
            if ($D <= 0 || $q <= 0 || $d >= $D) return [];
            $qmm = $q * 1e6 / 60; // mm³/s
            $ve = $qmm / $area($D); $vr = $qmm / ($area($D) - $area($d));
            return [['Extend speed', $f($ve) . ' mm/s'], ['Retract speed', $f($vr) . ' mm/s'],
                ['Stroke time (extend / retract)', $s > 0 ? $f($s / $ve, 2) . ' s / ' . $f($s / $vr, 2) . ' s' : '—']];
        case 'power':
            [$q, $p, $eta] = [$n('flow'), $n('pressure'), $n('efficiency')];
            if ($q <= 0 || $p <= 0 || $eta <= 0 || $eta > 100) return [];
            $kw = $q * $p / 600 / ($eta / 100);
            return [['Motor power', $f($kw, 2) . ' kW (' . $f($kw * 1.341, 1) . ' hp)'], ['Hydraulic power', $f($q * $p / 600, 2) . ' kW'],
                ['Suggested motor', 'next standard size ≥ ' . $f($kw * 1.1, 1) . ' kW']];
        case 'sizing':
            [$t, $p, $sf] = [$n('load'), $n('pressure'), $n('factor') ?: 1.25];
            if ($t <= 0 || $p <= 0) return [];
            $areaNeeded = $t * 9806.65 * $sf / ($p / 10);
            $dmin = sqrt(4 * $areaNeeded / M_PI);
            $std = null;
            foreach (CALC_STD_BORES as $b) if ($b >= $dmin) { $std = $b; break; }
            return [['Minimum bore', $f($dmin) . ' mm'], ['Recommended standard bore', $std ? $std . ' mm' : 'over 320 mm — ask us about a custom design'],
                ['Force at that bore', $std ? $f($p / 10 * $area($std) / 9806.65, 2) . ' t at ' . $f($p, 0) . ' bar' : '—']];
    }
    return [];
}

/** One line for the quote form: "Cylinder force: bore 80 mm, rod 45 mm, 160 bar → push 80.4 kN …" */
function calc_summary(string $type, array $in, array $results): string
{
    $defs = calculators()[$type] ?? null;
    if (!$defs || !$results) return '';
    $parts = [];
    foreach ($defs['inputs'] as $k => [$l, $unit]) $parts[] = strtolower($l) . ' ' . ($in[$k] ?? '') . ' ' . $unit;
    return $defs['title'] . ' calculator — ' . implode(', ', $parts) . ' → ' . implode('; ', array_map(fn($r) => $r[0] . ': ' . $r[1], $results)) . '.';
}
