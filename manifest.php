<?php
/**
 * Manifesto PWA Dinâmico (White-Label)
 * Retorna o JSON do manifest.json personalizado de acordo com o tenant informado.
 */

header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/db.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$tenant = null;

if ($slug) {
    try {
        $db = obterConexao();
        $stmt = $db->prepare("SELECT nome, slug, logo_path, modo_visualizacao FROM tenants WHERE slug = :slug");
        $stmt->execute([':slug' => $slug]);
        $tenant = $stmt->fetch();
    } catch (Exception $e) {
        // Ignora erro e usa fallback
    }
}

$nome = $tenant ? $tenant['nome'] : 'Central de Alertas';
$shortName = $tenant ? $tenant['nome'] : 'Alertas';
$startUrl = $tenant ? "t/{$tenant['slug']}/dashboard" : "./";
$modoVisualizacao = $tenant ? $tenant['modo_visualizacao'] : 'dark';

$themeColor = ($modoVisualizacao === 'light') ? '#f5f6fa' : '#0c0a1f';
$backgroundColor = ($modoVisualizacao === 'light') ? '#f5f6fa' : '#0c0a1f';

// Fallback para ícones
$icon192 = 'assets/img/icon_192.png';
$icon512 = 'assets/img/icon_512.png';

if ($tenant && $tenant['logo_path'] && file_exists(__DIR__ . '/' . $tenant['logo_path'])) {
    $icon192 = $tenant['logo_path'];
    $icon512 = $tenant['logo_path'];
}

$manifest = [
    "name" => $nome . " - Central de Alertas",
    "short_name" => $shortName,
    "description" => "Central de Monitoramento e Alertas em Tempo Real.",
    "start_url" => $startUrl,
    "display" => "standalone",
    "background_color" => $backgroundColor,
    "theme_color" => $themeColor,
    "orientation" => "portrait",
    "icons" => [
        [
            "src" => $icon192,
            "sizes" => "192x192",
            "type" => "image/png"
        ],
        [
            "src" => $icon512,
            "sizes" => "512x512",
            "type" => "image/png"
        ]
    ]
];

echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
