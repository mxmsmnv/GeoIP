<?php

final class GeoIPMaxMindProvider
{
    public function __construct(
        private readonly string $databasePath,
        private readonly string $autoloadPath
    ) {
    }

    public function lookup(string $ip): array
    {
        $result = GeoIPResult::empty($ip);

        if (is_file($this->autoloadPath) && !class_exists('\GeoIp2\Database\Reader')) {
            require_once $this->autoloadPath;
        }

        $cityDatabase = $this->databasePath . 'GeoLite2-City.mmdb';
        $countryDatabase = $this->databasePath . 'GeoLite2-Country.mmdb';
        $database = is_file($cityDatabase)
            ? $cityDatabase
            : (is_file($countryDatabase) ? $countryDatabase : null);

        if ($database === null) {
            $result['message'] = 'No GeoLite2 database found in ' . $this->databasePath;
            return $result;
        }

        if (!class_exists('\GeoIp2\Database\Reader')) {
            $result['message'] = 'geoip2/geoip2 Composer package not found.';
            return $result;
        }

        try {
            $reader = new \GeoIp2\Database\Reader($database);
            $record = str_contains($database, 'City')
                ? $reader->city($ip)
                : $reader->country($ip);

            $result['country'] = $record->country->name ?? '';
            $result['countryCode'] = $record->country->isoCode ?? '';
            $result['continent'] = $record->continent->name ?? '';

            if (isset($record->mostSpecificSubdivision)) {
                $result['region'] = $record->mostSpecificSubdivision->name ?? '';
                $result['regionCode'] = $record->mostSpecificSubdivision->isoCode ?? '';
            }

            if (isset($record->city)) {
                $result['city'] = $record->city->name ?? '';
            }
            if (isset($record->postal)) {
                $result['zip'] = $record->postal->code ?? '';
            }
            if (isset($record->location)) {
                $result['lat'] = $record->location->latitude;
                $result['lon'] = $record->location->longitude;
                $result['timezone'] = $record->location->timeZone ?? '';
            }

            $result['status'] = 'success';
            $result['source'] = 'maxmind';
        } catch (\Throwable $exception) {
            $result['message'] = $exception->getMessage();
        }

        return $result;
    }
}
