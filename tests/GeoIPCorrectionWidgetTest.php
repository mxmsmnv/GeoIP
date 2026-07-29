<?php

require_once __DIR__ . '/../src/Frontend/GeoIPCorrectionWidget.php';

function assertWidget(bool $condition, string $message): void
{
    if ($condition) return;
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$renderer = new GeoIPCorrectionWidget();
$markup = $renderer->render([
    'country' => 'United States',
    'countryCode' => 'US',
    'region' => 'New York',
    'regionCode' => 'NY',
    'city' => 'Brooklyn',
], '/location/?geoip_action=correct', [
    'variant' => 'embedded',
    'id' => 'sidebar location',
]);

assertWidget(str_contains($markup, 'id="sidebar-location"'), 'Widget ID should be sanitized.');
assertWidget(str_contains($markup, 'geoip-widget--embedded'), 'Embedded variant should be rendered.');
assertWidget(str_contains($markup, 'Brooklyn, New York, United States'), 'Location summary should be natural.');
assertWidget(str_contains($markup, 'name="country_code" value="US"'), 'Country code should be editable.');
assertWidget(str_contains($markup, 'data-geoip-form'), 'Correction form hook should be present.');
assertWidget(!str_contains($markup, 'style='), 'Widget markup should not contain inline presentation styles.');
assertWidget(!str_contains($markup, '<script>'), 'Widget markup should not contain inline scripts.');

echo "GeoIP correction widget tests passed.\n";
