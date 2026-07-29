<?php

final class GeoIPCorrectionWidget
{
    public function render(
        array $geo,
        string $endpoint = './?geoip_action=correct',
        array $options = []
    ): string
    {
        $country = htmlspecialchars($geo['country'] ?? '', ENT_QUOTES);
        $countryCode = htmlspecialchars($geo['countryCode'] ?? '', ENT_QUOTES);
        $region = htmlspecialchars($geo['region'] ?? '', ENT_QUOTES);
        $regionCode = htmlspecialchars($geo['regionCode'] ?? '', ENT_QUOTES);
        $city = htmlspecialchars($geo['city'] ?? '', ENT_QUOTES);
        $endpoint = htmlspecialchars($endpoint, ENT_QUOTES);
        $variant = ($options['variant'] ?? '') === 'embedded' ? 'embedded' : 'floating';
        $id = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string)($options['id'] ?? 'geoip-widget'));
        $id = htmlspecialchars(trim((string)$id, '-') ?: 'geoip-widget', ENT_QUOTES);
        $locationParts = array_filter([
            trim((string)($geo['city'] ?? '')),
            trim((string)($geo['region'] ?? '')),
            trim((string)($geo['country'] ?? '')),
        ]);
        if (!$locationParts && trim((string)($geo['countryCode'] ?? '')) !== '') {
            $locationParts[] = strtoupper(trim((string)$geo['countryCode']));
        }
        $location = htmlspecialchars(
            $locationParts ? implode(', ', $locationParts) : 'Location unavailable',
            ENT_QUOTES
        );

        return <<<HTML
<details id="{$id}" class="geoip-widget geoip-widget--{$variant}" data-geoip-widget>
  <summary class="geoip-widget__summary">
    <svg class="geoip-widget__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"></path>
      <circle cx="12" cy="10" r="2.5"></circle>
    </svg>
    <span class="geoip-widget__copy">
      <strong>Your location</strong>
      <span data-geoip-location>{$location}</span>
    </span>
    <span class="geoip-widget__change">Change</span>
  </summary>
  <form class="geoip-widget__form" action="{$endpoint}" method="post" data-geoip-form>
    <label>
      <span>Country</span>
      <input type="text" name="country" value="{$country}" autocomplete="country-name">
    </label>
    <label>
      <span>Country code</span>
      <input type="text" name="country_code" value="{$countryCode}" maxlength="2" autocomplete="country">
    </label>
    <label>
      <span>Region or state</span>
      <input type="text" name="region" value="{$region}" autocomplete="address-level1">
    </label>
    <label>
      <span>Region code</span>
      <input type="text" name="region_code" value="{$regionCode}" maxlength="12">
    </label>
    <label class="geoip-widget__field--wide">
      <span>City</span>
      <input type="text" name="city" value="{$city}" autocomplete="address-level2">
    </label>
    <div class="geoip-widget__actions">
      <button type="submit">Save location</button>
      <button type="button" data-geoip-cancel>Cancel</button>
    </div>
    <p class="geoip-widget__status" role="status" aria-live="polite" data-geoip-status></p>
  </form>
</details>
HTML;
    }
}
