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
        $dialogId = $id . '-dialog';
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
        $compactLocation = htmlspecialchars(
            trim((string)($geo['city'] ?? ''))
                ?: trim((string)($geo['country'] ?? ''))
                ?: strtoupper(trim((string)($geo['countryCode'] ?? '')))
                ?: 'Choose location',
            ENT_QUOTES
        );
        $csrfInput = (string)($options['csrf_input'] ?? '');
        $csrfUrl = htmlspecialchars(
            (string)($options['csrf_url'] ?? '/?geoip_action=csrf'),
            ENT_QUOTES
        );

        return <<<HTML
<div id="{$id}" class="geoip-widget geoip-widget--{$variant}" data-geoip-widget>
  <button class="geoip-widget__trigger" type="button" command="show-modal" commandfor="{$dialogId}" aria-label="Current location: {$location}. Change location">
    <svg class="geoip-widget__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"></path>
      <circle cx="12" cy="10" r="2.5"></circle>
    </svg>
    <span class="geoip-widget__location" data-geoip-location>{$compactLocation}</span>
    <svg class="geoip-widget__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="m9 18 6-6-6-6"></path>
    </svg>
  </button>

  <dialog class="ds-dialog geoip-widget__dialog" id="{$dialogId}" closedby="any" aria-labelledby="{$dialogId}-title">
    <div class="ds-dialog__block geoip-widget__dialog-header">
      <div>
        <h2 class="ds-heading" data-size="sm" id="{$dialogId}-title">Confirm your location</h2>
        <p class="ds-paragraph" data-size="sm">We use your location to make nearby places and local information more relevant.</p>
      </div>
      <button class="ds-button geoip-widget__close" data-variant="tertiary" data-icon="true" type="button" command="close" commandfor="{$dialogId}" aria-label="Close location dialog">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"></path></svg>
      </button>
    </div>
    <div class="ds-dialog__block">
      <div class="geoip-widget__confirmation" data-geoip-confirmation>
        <div class="geoip-widget__detected">
          <svg class="geoip-widget__detected-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"></path>
            <circle cx="12" cy="10" r="2.5"></circle>
          </svg>
          <div>
            <span class="ds-label">Detected location</span>
            <strong>{$location}</strong>
          </div>
        </div>
        <div class="geoip-widget__actions">
          <button class="ds-button" data-variant="primary" type="button" command="close" commandfor="{$dialogId}">Yes, that’s right</button>
          <button class="ds-button" data-variant="secondary" type="button" data-geoip-edit>Choose another location</button>
        </div>
      </div>

      <form class="geoip-widget__form" action="{$endpoint}" method="post" data-geoip-form data-geoip-csrf-url="{$csrfUrl}" hidden>
        {$csrfInput}
        <p class="ds-paragraph geoip-widget__form-intro" data-size="sm">Enter the most useful location for your LQRS experience.</p>
        <label class="ds-field">
          <span class="ds-label">Country</span>
          <input class="ds-input" type="text" name="country" value="{$country}" autocomplete="country-name">
        </label>
        <label class="ds-field">
          <span class="ds-label">Country code</span>
          <input class="ds-input" type="text" name="country_code" value="{$countryCode}" maxlength="2" autocomplete="country">
        </label>
        <label class="ds-field">
          <span class="ds-label">Region or state</span>
          <input class="ds-input" type="text" name="region" value="{$region}" autocomplete="address-level1">
        </label>
        <label class="ds-field">
          <span class="ds-label">Region code</span>
          <input class="ds-input" type="text" name="region_code" value="{$regionCode}" maxlength="12">
        </label>
        <label class="ds-field geoip-widget__field--wide">
          <span class="ds-label">City</span>
          <input class="ds-input" type="text" name="city" value="{$city}" autocomplete="address-level2">
        </label>
        <div class="geoip-widget__actions">
          <button class="ds-button" data-variant="primary" type="submit">Save location</button>
          <button class="ds-button" data-variant="tertiary" type="button" data-geoip-cancel>Back</button>
        </div>
        <p class="geoip-widget__status" role="status" aria-live="polite" data-geoip-status></p>
      </form>
    </div>
  </dialog>
</div>
HTML;
    }
}
