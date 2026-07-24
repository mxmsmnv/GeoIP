<?php

require_once __DIR__ . '/../src/Lookup/GeoIPResult.php';
require_once __DIR__ . '/../src/Lookup/GeoIPMaxMindProvider.php';
require_once __DIR__ . '/../src/Lookup/GeoIPGeolocationProvider.php';
require_once __DIR__ . '/../src/Lookup/GeoIPLookupService.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$apiResponse = json_encode([
    'ip' => '8.8.8.8',
    'location' => [
        'continent_name' => 'North America',
        'country_code2' => 'US',
        'country_name' => 'United States',
        'state_prov' => 'Virginia',
        'state_code' => 'US-VA',
        'city' => 'Ashburn',
        'zipcode' => '20149',
        'latitude' => '39.04372',
        'longitude' => '-77.48749',
    ],
    'time_zone' => [
        'name' => 'America/New_York',
    ],
], JSON_THROW_ON_ERROR);

$requestedUrl = '';
$httpProvider = new GeoIPGeolocationProvider(
    static function (string $url) use (&$requestedUrl, $apiResponse): string {
        $requestedUrl = $url;
        return $apiResponse;
    },
    'test key'
);

$service = new GeoIPLookupService(
    new GeoIPMaxMindProvider('/nonexistent/', '/nonexistent/autoload.php'),
    $httpProvider,
    ['countryCode' => 'GB', 'regionCode' => '', 'city' => 'London']
);

$result = $service->lookup('8.8.8.8');
assertSameValue('success', $result['status'], 'HTTP fallback should return success.');
assertSameValue('ipgeolocation', $result['source'], 'HTTP fallback source should be exposed.');
assertSameValue('US', $result['countryCode'], 'Country code should be mapped.');
assertSameValue('VA', $result['regionCode'], 'Country prefix should be removed from subdivision code.');
assertSameValue('America/New_York', $result['timezone'], 'Timezone should be mapped.');
assertSameValue(
    'https://api.ipgeolocation.io/v3/ipgeo?apiKey=test%20key&ip=8.8.8.8',
    $requestedUrl,
    'API key and IP should be encoded into the HTTPS request.'
);

$failedService = new GeoIPLookupService(
    new GeoIPMaxMindProvider('/nonexistent/', '/nonexistent/autoload.php'),
    new GeoIPGeolocationProvider(static fn(string $url): string => '{}', 'test'),
    ['countryCode' => 'GB', 'regionCode' => 'ENG', 'city' => 'London']
);

$failed = $failedService->lookup('192.0.2.1');
assertSameValue('fail', $failed['status'], 'Static defaults must not disguise a failed lookup.');
assertSameValue('configured', $failed['source'], 'Static fallback source should be exposed.');
assertSameValue('GB', $failed['countryCode'], 'Static country fallback should be applied.');
assertSameValue('ENG', $failed['regionCode'], 'Static region fallback should be applied.');
assertSameValue('London', $failed['city'], 'Static city fallback should be applied.');

echo "GeoIP lookup tests passed.\n";
