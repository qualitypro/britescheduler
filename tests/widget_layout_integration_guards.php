<?php
declare(strict_types=1);
// Source-level guards for recovered dashboard widget integration.
// These are not substitutes for authenticated tenant and browser tests.
$root = dirname(__DIR__);
$failures = 0;
$check = static function (bool $ok, string $label) use (&$failures): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) { ++$failures; }
};
$read = static function (string $path) use ($root): string {
    $content = @file_get_contents($root . '/' . $path);
    if ($content === false) {
        throw new RuntimeException('Missing source: ' . $path);
    }
    return $content;
};
try {
    $api = $read('api/dashboard_widget_layout.php');
    $js = $read('assets/js/brite-widget-layout.js');
    $css = $read('assets/css/brite-widget-layout.css');
    $migration = $read('migrations/030_dashboard_widget_layouts.sql');
    $check(str_contains($api, 'Auth::requireUser()') && str_contains($api, 'Auth::tenant()'), 'Authenticated tenant context');
    $check(str_contains($api, 'tenant_id=? AND user_id=? AND dashboard_key=?'), 'Scoped layout lookup and deletion');
    $check(str_contains($api, 'hash_equals(csrf_token(),$csrf)'), 'CSRF validation on mutations');
    $check(str_contains($api, 'ModuleRegistry::activeFor($tenant)'), 'Intelligence module activation guard');
    foreach (['operations','contractor','client','intelligence_performance','intelligence_financial'] as $key) {
        $check(str_contains($api, "'".$key."'"), 'Dashboard allowlist: '.$key);
    }
    $check(str_contains($migration, 'UNIQUE KEY uq_dashboard_widget_scope'), 'Unique tenant/user/dashboard layout scope');
    $check(str_contains($js, 'fetch(endpoint') && str_contains($js, "method:'PUT'"), 'Widget persistence request');
    $check(strlen($css) > 0, 'Widget layout stylesheet exists');
    foreach (['dashboard.v2.php','client-dashboard.php','contractor-dashboard.php','modules/intelligence/index.php'] as $page) {
        $source = $read($page);
        $check(str_contains($source, 'brite-widget-layout.js'), 'Widget JavaScript wired: '.$page);
        $check(str_contains($source, 'brite-widget-layout.css'), 'Widget CSS wired: '.$page);
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL '.$e->getMessage().PHP_EOL);
    exit(1);
}
exit($failures === 0 ? 0 : 1);
