<?php

final class GeoIPResult
{
    public static function empty(string $ip): array
    {
        return [
            'ip'          => $ip,
            'country'     => '',
            'countryCode' => '',
            'continent'   => '',
            'region'      => '',
            'regionCode'  => '',
            'city'        => '',
            'zip'         => '',
            'lat'         => null,
            'lon'         => null,
            'timezone'    => '',
            'corrected'   => false,
            'status'      => 'fail',
            'source'      => '',
        ];
    }

    public static function withConfiguredFallbacks(array $result, array $fallbacks): array
    {
        if (($result['status'] ?? 'fail') === 'success') {
            return $result;
        }

        if (!empty($fallbacks['countryCode'])) {
            $result['countryCode'] = $fallbacks['countryCode'];
        }
        if (!empty($fallbacks['regionCode'])) {
            $result['regionCode'] = $fallbacks['regionCode'];
        }
        if (!empty($fallbacks['city'])) {
            $result['city'] = $fallbacks['city'];
        }

        if ($result['countryCode'] || $result['regionCode'] || $result['city']) {
            $result['source'] = 'configured';
        }

        return $result;
    }
}
