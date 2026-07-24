<?php

final class GeoIPGeolocationProvider
{
    /** @var callable */
    private $httpGet;

    public function __construct(callable $httpGet, private readonly string $apiKey)
    {
        $this->httpGet = $httpGet;
    }

    public function lookup(string $ip): array
    {
        $result = GeoIPResult::empty($ip);

        if ($this->apiKey === '') {
            $result['message'] = 'IPGeolocation.io API key is not configured.';
            return $result;
        }

        $url = 'https://api.ipgeolocation.io/v3/ipgeo?apiKey='
            . rawurlencode($this->apiKey)
            . '&ip=' . rawurlencode($ip);

        try {
            $response = ($this->httpGet)($url);
            $payload = json_decode((string) $response, true);

            if (!is_array($payload)) {
                $result['message'] = 'IPGeolocation.io returned an invalid response.';
                return $result;
            }

            if (!is_array($payload['location'] ?? null) || $payload['location'] === []) {
                $message = $payload['message'] ?? $payload['error'] ?? 'lookup failed.';
                $result['message'] = is_scalar($message)
                    ? 'IPGeolocation.io: ' . (string) $message
                    : 'IPGeolocation.io lookup failed.';
                return $result;
            }

            $location = $payload['location'];
            $timezone = is_array($payload['time_zone'] ?? null) ? $payload['time_zone'] : [];

            $result['ip'] = (string) ($payload['ip'] ?? $ip);
            $result['country'] = (string) ($location['country_name'] ?? '');
            $result['countryCode'] = (string) ($location['country_code2'] ?? '');
            $result['continent'] = (string) ($location['continent_name'] ?? '');
            $result['region'] = (string) ($location['state_prov'] ?? '');
            $result['regionCode'] = (string) ($location['state_code'] ?? '');
            $countryPrefix = strtoupper($result['countryCode']) . '-';
            if ($countryPrefix !== '-' && str_starts_with(strtoupper($result['regionCode']), $countryPrefix)) {
                $result['regionCode'] = substr($result['regionCode'], strlen($countryPrefix));
            }
            $result['city'] = (string) ($location['city'] ?? '');
            $result['zip'] = (string) ($location['zipcode'] ?? '');
            $result['lat'] = isset($location['latitude']) ? (float) $location['latitude'] : null;
            $result['lon'] = isset($location['longitude']) ? (float) $location['longitude'] : null;
            $result['timezone'] = (string) ($timezone['name'] ?? '');
            $result['status'] = 'success';
            $result['source'] = 'ipgeolocation';
        } catch (\Throwable $exception) {
            $result['message'] = $exception->getMessage();
        }

        return $result;
    }
}
