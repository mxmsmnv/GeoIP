<?php

final class GeoIPLookupService
{
    public function __construct(
        private readonly GeoIPMaxMindProvider $maxMind,
        private readonly ?GeoIPGeolocationProvider $httpFallback,
        private readonly array $configuredFallbacks
    ) {
    }

    public function lookup(string $ip): array
    {
        $result = $this->maxMind->lookup($ip);

        if (($result['status'] ?? 'fail') !== 'success' && $this->httpFallback !== null) {
            $localMessage = $result['message'] ?? '';
            $result = $this->httpFallback->lookup($ip);

            if (($result['status'] ?? 'fail') !== 'success' && $localMessage !== '') {
                $result['message'] = $localMessage . ' HTTP fallback: '
                    . ($result['message'] ?? 'lookup failed.');
            }
        }

        return GeoIPResult::withConfiguredFallbacks($result, $this->configuredFallbacks);
    }
}
