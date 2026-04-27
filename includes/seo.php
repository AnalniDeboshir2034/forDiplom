<?php

if (!function_exists('seo_site_base_url')) {
    function seo_site_base_url(): string
    {
        return 'https://medikator.ru';
    }
}

if (!function_exists('seo_current_path')) {
    function seo_current_path(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '/';
        }

        return $path;
    }
}

if (!function_exists('seo_canonical_url')) {
    function seo_canonical_url(?string $path = null): string
    {
        $normalizedPath = $path ?? seo_current_path();
        if ($normalizedPath === '') {
            $normalizedPath = '/';
        }

        return rtrim(seo_site_base_url(), '/') . $normalizedPath;
    }
}

if (!function_exists('seo_slug_to_text')) {
    function seo_slug_to_text(string $slug): string
    {
        $value = trim($slug);
        if ($value === '') {
            return 'medikator';
        }

        $value = str_replace(['_', '-'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        $value = trim((string)$value);

        if ($value === '') {
            return 'medikator';
        }

        return function_exists('mb_convert_case')
            ? mb_convert_case($value, MB_CASE_TITLE, 'UTF-8')
            : ucwords($value);
    }
}

if (!function_exists('seo_product_image_alt')) {
    function seo_product_image_alt(array $product, string $fallback = 'Медикатор-дозатор', string $suffix = ''): string
    {
        $slug = trim((string)($product['slug'] ?? ''));
        $name = trim((string)($product['name'] ?? ''));

        if ($slug !== '') {
            $base = seo_slug_to_text($slug);
        } elseif ($name !== '') {
            $base = $name;
        } else {
            $base = $fallback;
        }

        if ($suffix !== '') {
            $base .= ' ' . $suffix;
        }

        return $base;
    }
}

if (!function_exists('seo_render_meta')) {
    function seo_render_meta(array $meta): void
    {
        $title = trim((string)($meta['title'] ?? 'Medikator.ru'));
        $description = trim((string)($meta['description'] ?? ''));
        $canonical = trim((string)($meta['canonical'] ?? seo_canonical_url()));
        $robots = trim((string)($meta['robots'] ?? 'index,follow,max-image-preview:large'));
        $image = trim((string)($meta['image'] ?? '/products/favicon.svg'));
        $type = trim((string)($meta['type'] ?? 'website'));

        if (!preg_match('#^https?://#i', $image)) {
            $image = rtrim(seo_site_base_url(), '/') . '/' . ltrim($image, '/');
        }

        echo '<title>' . htmlspecialchars($title) . "</title>\n";
        if ($description !== '') {
            echo '<meta name="description" content="' . htmlspecialchars($description) . "\">\n";
        }
        echo '<meta name="robots" content="' . htmlspecialchars($robots) . "\">\n";
        echo '<link rel="canonical" href="' . htmlspecialchars($canonical) . "\">\n";
        echo '<meta property="og:locale" content="ru_RU">' . "\n";
        echo '<meta property="og:type" content="' . htmlspecialchars($type) . "\">\n";
        echo '<meta property="og:site_name" content="Medikator.ru">' . "\n";
        echo '<meta property="og:title" content="' . htmlspecialchars($title) . "\">\n";
        if ($description !== '') {
            echo '<meta property="og:description" content="' . htmlspecialchars($description) . "\">\n";
        }
        echo '<meta property="og:url" content="' . htmlspecialchars($canonical) . "\">\n";
        echo '<meta property="og:image" content="' . htmlspecialchars($image) . "\">\n";
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . htmlspecialchars($title) . "\">\n";
        if ($description !== '') {
            echo '<meta name="twitter:description" content="' . htmlspecialchars($description) . "\">\n";
        }
        echo '<meta name="twitter:image" content="' . htmlspecialchars($image) . "\">\n";
    }
}

if (!function_exists('seo_render_organization_jsonld')) {
    function seo_render_organization_jsonld(array $siteSettings = []): void
    {
        $phone = (string)($siteSettings['contacts']['phone'] ?? '');
        $email = (string)($siteSettings['contacts']['email'] ?? '');
        $address = (string)($siteSettings['contacts']['address'] ?? '');

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'Medikator.ru',
            'url' => seo_site_base_url(),
            'logo' => seo_site_base_url() . '/products/icon.png',
            'telephone' => $phone,
            'email' => $email,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $address,
                'addressCountry' => 'RU',
            ],
        ];

        echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }
}

