<?php

/**
 * Measure digit ink cap-height (px) in 144 DPI Dompdf PNG renders.
 * Usage: php scripts/measure-pdf-digit-cap-heights.php [artifact-dir]
 */

$artifactDir = $argv[1] ?? (is_dir('/opt/cursor/artifacts') ? '/opt/cursor/artifacts' : storage_path('framework/testing/pdf-artifacts'));

$slugs = [
    'dompdf-render-simplified',
    'dompdf-render-standard',
    'dompdf-render-credit-note',
    'dompdf-render-quotation',
];

/**
 * @return array{cap: int, rows: int}
 */
function measureRegionCapHeight(GdImage $img, int $w, int $h, float $x1Pct, float $y1Pct, float $x2Pct, float $y2Pct): array
{
    $x1 = (int) floor($w * $x1Pct);
    $x2 = (int) ceil($w * $x2Pct);
    $y1 = (int) floor($h * $y1Pct);
    $y2 = (int) ceil($h * $y2Pct);

    $minY = $y2 + 1;
    $maxY = $y1 - 1;
    $cols = 0;
    for ($y = $y1; $y <= $y2; $y++) {
        for ($x = $x1; $x <= $x2; $x++) {
            $rgb = imagecolorat($img, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $lum = (int) (0.299 * $r + 0.587 * $g + 0.114 * $b);
            if ($lum < 175) {
                $minY = min($minY, $y);
                $maxY = max($maxY, $y);
                $cols++;
            }
        }
    }

    if ($cols === 0) {
        return ['cap' => 0, 'rows' => 0];
    }

  return ['cap' => $maxY - $minY + 1, 'rows' => $cols];
}

/** @var list<array{slug: string, region: string, tier: string, cap: int}> */
$rows = [];

foreach ($slugs as $slug) {
    $png = $artifactDir.'/'.$slug.'.png';
    if (! is_file($png)) {
        fwrite(STDERR, "Missing {$png}\n");
        continue;
    }
    $img = imagecreatefrompng($png);
    if ($img === false) {
        fwrite(STDERR, "Cannot load {$png}\n");
        continue;
    }
    $w = imagesx($img);
    $h = imagesy($img);

    $regions = [
        ['region' => 'header_vat_en', 'tier' => 'body', 'box' => [0.16, 0.168, 0.30, 0.182]],
        ['region' => 'header_vat_ar', 'tier' => 'body', 'box' => [0.70, 0.168, 0.84, 0.182]],
        ['region' => 'meta_invoice_no', 'tier' => 'body', 'box' => [0.58, 0.200, 0.78, 0.208]],
        ['region' => 'buyer_phone', 'tier' => 'body', 'box' => [0.58, 0.248, 0.78, 0.256]],
        ['region' => 'table_qty', 'tier' => 'body', 'box' => [0.36, 0.276, 0.44, 0.284]],
        ['region' => 'table_line_amount', 'tier' => 'body', 'box' => [0.86, 0.276, 0.96, 0.284]],
        ['region' => 'total_subtotal', 'tier' => 'body', 'box' => [0.86, 0.708, 0.96, 0.716]],
        ['region' => 'total_grand', 'tier' => 'grand', 'box' => [0.86, 0.722, 0.96, 0.732]],
    ];

    foreach ($regions as $def) {
        [$x1, $y1, $x2, $y2] = $def['box'];
        $m = measureRegionCapHeight($img, $w, $h, $x1, $y1, $x2, $y2);
        $rows[] = [
            'slug' => $slug,
            'region' => $def['region'],
            'tier' => $def['tier'],
            'cap' => $m['cap'],
        ];
    }

    imagedestroy($img);
}

$byTier = ['body' => [], 'grand' => []];
foreach ($rows as $row) {
    if ($row['cap'] > 0) {
        $byTier[$row['tier']][] = $row['cap'];
    }
}

$summary = [];
foreach (['body', 'grand'] as $tier) {
    if ($byTier[$tier] === []) {
        continue;
    }
    $summary[$tier] = [
        'min' => min($byTier[$tier]),
        'max' => max($byTier[$tier]),
        'spread' => max($byTier[$tier]) - min($byTier[$tier]),
    ];
}

echo json_encode(['rows' => $rows, 'summary' => $summary], JSON_PRETTY_PRINT)."\n";