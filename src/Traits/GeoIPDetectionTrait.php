<?php

trait GeoIPDetectionTrait
{
    protected ?array $geoData = null;
    protected ?GeoIPLookupService $lookupService = null;
    protected ?GeoIPStore $store = null;

    /**
     * Return location data for the current visitor or a supplied IP address.
     *
     * Current-visitor results are cached in memory and, when enabled, in the
     * ProcessWire session. An explicit IP bypasses session data, corrections
     * and logging so it is safe for the admin lookup tool.
     */
    public function detect(?string $ip = null): array
    {
        if ($ip !== null) {
            return $this->lookup($ip);
        }

        if ($this->geoData !== null) {
            return $this->geoData;
        }

        $resolvedIp = $this->getClientIP();

        if ($this->get('session_cache')) {
            $cached = $this->wire('session')->get(self::SESSION_KEY);
            if (is_array($cached)) {
                return $this->geoData = $this->applyCorrection($cached, $resolvedIp);
            }
        }

        $data = $this->applyCorrection($this->lookup($resolvedIp), $resolvedIp);

        if ($this->get('session_cache')) {
            $this->wire('session')->set(self::SESSION_KEY, $data);
        }

        $this->geoData = $data;

        if ($this->get('enable_logging')) {
            $this->getStore()->logLookup($data);
        }

        return $data;
    }

    public function getField(string $field): mixed
    {
        return $this->detect()[$field] ?? null;
    }

    public function inCountry(string|array $codes): bool
    {
        $current = strtoupper((string)($this->detect()['countryCode'] ?? ''));
        return in_array($current, array_map('strtoupper', (array)$codes), true);
    }

    public function inRegion(string|array $codes): bool
    {
        $current = strtoupper((string)($this->detect()['regionCode'] ?? ''));
        return in_array($current, array_map('strtoupper', (array)$codes), true);
    }

    public function inCity(string|array $cities): bool
    {
        $current = strtolower((string)($this->detect()['city'] ?? ''));
        return in_array($current, array_map('strtolower', (array)$cities), true);
    }

    public function showIf(
        string $field,
        string|array $values,
        string $content,
        string $else = ''
    ): string {
        $current = strtolower((string)($this->detect()[$field] ?? ''));
        $values = array_map('strtolower', (array)$values);
        return in_array($current, $values, true) ? $content : $else;
    }

    public function getClientIP(): string
    {
        $keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($keys as $key) {
            if (empty($_SERVER[$key])) {
                continue;
            }

            $ip = trim(explode(',', (string)$_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }

        return (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    }

    public function saveCorrection(array $data): bool
    {
        $saved = $this->getStore()->saveCorrection($this->getClientIP(), $data);

        if ($saved) {
            $this->wire('session')->remove(self::SESSION_KEY);
            $this->geoData = null;
        }

        return $saved;
    }

    protected function lookup(string $ip): array
    {
        return $this->getLookupService()->lookup($ip);
    }

    protected function getLookupService(): GeoIPLookupService
    {
        if ($this->lookupService !== null) {
            return $this->lookupService;
        }

        $httpFallback = null;
        if ($this->get('http_fallback_enabled') && $this->get('ipgeolocation_api_key')) {
            $http = new WireHttp();
            $http->setTimeout(max(1, min(10, (int)$this->get('http_timeout'))));
            $httpFallback = new GeoIPGeolocationProvider(
                static fn(string $url): string => (string)$http->get($url),
                (string)$this->get('ipgeolocation_api_key')
            );
        }

        return $this->lookupService = new GeoIPLookupService(
            new GeoIPMaxMindProvider($this->getGeoIPPath(), $this->getAutoloadPath()),
            $httpFallback,
            [
                'countryCode' => (string)$this->get('fallback_country_code'),
                'regionCode' => (string)$this->get('fallback_region_code'),
                'city' => (string)$this->get('fallback_city'),
            ]
        );
    }

    protected function applyCorrection(array $data, string $ip): array
    {
        $correction = $this->getStore()->getCorrection($ip);
        if (!$correction) {
            $data['corrected'] = false;
            return $data;
        }

        $map = [
            'country' => 'country',
            'country_code' => 'countryCode',
            'region' => 'region',
            'region_code' => 'regionCode',
            'city' => 'city',
        ];

        foreach ($map as $storedKey => $resultKey) {
            if (!empty($correction[$storedKey])) {
                $data[$resultKey] = $correction[$storedKey];
            }
        }

        $data['corrected'] = true;
        return $data;
    }

    protected function getStore(): GeoIPStore
    {
        if ($this->store === null) {
            $this->store = new GeoIPStore(
                $this->wire('database'),
                $this->wire('session'),
                $this->wire('sanitizer'),
                $this->wire('log')
            );
        }

        return $this->store;
    }
}
