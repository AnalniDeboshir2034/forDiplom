<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/water_treatment.php';

header('Content-Type: application/xml; charset=UTF-8');

$baseUrl = 'https://medikator.ru';
$now = gmdate('Y-m-d');

$urls = [
    [
        'loc' => $baseUrl . '/',
        'changefreq' => 'weekly',
        'priority' => '1.0',
        'lastmod' => $now,
    ],
    [
        'loc' => $baseUrl . '/catalog',
        'changefreq' => 'daily',
        'priority' => '0.9',
        'lastmod' => $now,
    ],
    [
        'loc' => $baseUrl . '/contacts',
        'changefreq' => 'monthly',
        'priority' => '0.8',
        'lastmod' => $now,
    ],
    [
        'loc' => $baseUrl . '/privacy',
        'changefreq' => 'yearly',
        'priority' => '0.3',
        'lastmod' => $now,
    ],
];

if ($mysqli && !$mysqli->connect_error) {
    $sql = "
        SELECT m.slug, MAX(mv.view_data) AS last_view
        FROM medicator m
        LEFT JOIN medicator_view mv ON mv.medicator_id = m.id
        WHERE m.slug IS NOT NULL AND m.slug != ''
        GROUP BY m.id, m.slug
        ORDER BY m.id ASC
    ";

    $result = $mysqli->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $slug = trim((string)($row['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $lastmod = trim((string)($row['last_view'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $lastmod)) {
                $lastmod = $now;
            }

            $urls[] = [
                'loc' => $baseUrl . '/product/' . rawurlencode($slug),
                'changefreq' => 'weekly',
                'priority' => '0.8',
                'lastmod' => $lastmod,
            ];
        }
        $result->free();
    }
}

$waterTreatmentProduct = load_water_treatment_product();
if (is_array($waterTreatmentProduct) && !empty($waterTreatmentProduct['slug'])) {
    $urls[] = [
        'loc' => $baseUrl . '/product/' . rawurlencode((string)$waterTreatmentProduct['slug']),
        'changefreq' => 'weekly',
        'priority' => '0.8',
        'lastmod' => $now,
    ];
}

if ($mysqli && !$mysqli->connect_error) {
    $mysqli->close();
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach ($urls as $url) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
    echo "    <lastmod>" . htmlspecialchars($url['lastmod'], ENT_XML1, 'UTF-8') . "</lastmod>\n";
    echo "    <changefreq>" . htmlspecialchars($url['changefreq'], ENT_XML1, 'UTF-8') . "</changefreq>\n";
    echo "    <priority>" . htmlspecialchars($url['priority'], ENT_XML1, 'UTF-8') . "</priority>\n";
    echo "  </url>\n";
}
echo "</urlset>\n";

