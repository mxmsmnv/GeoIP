# GeoIP Public API

This document describes the supported public interface of GeoIP 1.3.3 for ProcessWire 3.0.200+ and PHP 8.2+.

Use live site state to determine whether the module is installed and configured. This file defines how the API should be called; it does not prove provider readiness on a particular site.

## Accessing The Module

GeoIP registers itself as the `$geoip` ProcessWire API variable:

```php
$geo = $geoip->detect();
```

Outside normal template scope:

```php
/** @var GeoIP $geoip */
$geoip = $modules->get('GeoIP');
```

The module is autoloaded, but detection is lazy. Accessing `$geoip` does not perform a lookup until a location method is called.

## Result Schema

`detect()` returns an array with these keys:

| Key | Type | Meaning |
| --- | --- | --- |
| `ip` | `string` | Resolved or explicitly supplied IP address. |
| `country` | `string` | Country name, when available. |
| `countryCode` | `string` | ISO 3166-1 alpha-2 country code. |
| `continent` | `string` | Continent name. |
| `region` | `string` | Subdivision name. |
| `regionCode` | `string` | Provider subdivision code, normalized without the country prefix for IPGeolocation.io. |
| `city` | `string` | City name. |
| `zip` | `string` | Postal code. |
| `lat` | `float|null` | Approximate latitude. |
| `lon` | `float|null` | Approximate longitude. |
| `timezone` | `string` | IANA timezone identifier when available. |
| `corrected` | `bool` | Whether a saved visitor correction was applied. |
| `status` | `string` | `success` or `fail`. |
| `source` | `string` | `maxmind`, `ipgeolocation`, `configured` or an empty string. |
| `message` | `string` | Failure detail when a provider cannot resolve the IP; not guaranteed on success. |

Static fallback values do not change `status` to `success`. Check `status` when the difference between detected and configured data matters.

## `detect(?string $ip = null): array`

Detect the current visitor or look up an explicit IP.

```php
$geo = $geoip->detect();
```

Current-visitor behavior:

- resolves the client IP;
- reuses request-memory cache;
- reuses the optional ProcessWire session cache;
- runs local MaxMind, then optional HTTP fallback;
- applies configured fallback fields after provider failure;
- applies a saved visitor correction;
- optionally logs one lookup per IP per session.

Explicit-IP behavior:

```php
$geo = $geoip->detect('8.8.8.8');
```

An explicit IP is intended for administrative or diagnostic lookup. It does not use the visitor session cache, apply visitor corrections or write a lookup log entry.

## `getField(string $field): mixed`

Return one key from the current visitor result, or `null` when the key does not exist.

```php
$countryCode = $geoip->getField('countryCode');
$timezone = $geoip->getField('timezone');
```

Prefer `detect()` once when a template needs several fields.

## `inCountry(string|array $codes): bool`

Case-insensitive match against `countryCode`.

```php
if ($geoip->inCountry('US')) {
    echo $page->us_content;
}

if ($geoip->inCountry(['US', 'CA', 'MX'])) {
    echo $page->north_america_content;
}
```

## `inRegion(string|array $codes): bool`

Case-insensitive match against `regionCode`.

```php
if ($geoip->inCountry('US') && $geoip->inRegion(['PA', 'NJ', 'NY'])) {
    echo $page->northeast_content;
}
```

Subdivision codes can overlap between countries. Combine region checks with `inCountry()` whenever the rule is country-specific.

## `inCity(string|array $cities): bool`

Case-insensitive exact match against the provider's city name.

```php
if ($geoip->inCity(['Philadelphia', 'New York'])) {
    echo $page->local_delivery_content;
}
```

City spelling and availability depend on provider data. Do not use this helper for security or legal eligibility.

## `showIf(string $field, string|array $values, string $content, string $else = ''): string`

Return one of two strings after a case-insensitive exact field match.

```php
echo $geoip->showIf(
    'countryCode',
    'US',
    $page->us_banner,
    $page->global_banner
);
```

The method performs no escaping. Escape or sanitize content according to its source and output context.

## `renderLocationWidget(array $options = []): string`

Render the template-integrated location correction control. The `enable_embedded_widget` module setting must be enabled; otherwise the method returns an empty string.

```php
echo $geoip->renderLocationWidget([
    'id' => 'header-location',
    'defer' => true,
]);
```

Supported options:

| Option | Type | Default | Meaning |
| --- | --- | --- | --- |
| `id` | `string` | `geoip-widget` | HTML identifier; sanitized before output. |
| `defer` | `bool` | `false` | Render a stable placeholder and load visitor markup from a private endpoint. |
| `endpoint` | `string` | `./?geoip_action=correct` | Correction POST target for a non-deferred widget. |

The method owns CSS/JavaScript asset output and emits those tags at most once per request.

Use `defer => true` with shared full-page caches. A deferred fragment does not include a CSRF token; the browser requests one only when the visitor submits a correction.

## `getClientIP(): string`

Return the module's resolved client IP.

Header precedence:

1. `HTTP_CF_CONNECTING_IP`
2. `HTTP_X_REAL_IP`
3. first value in `HTTP_X_FORWARDED_FOR`
4. `HTTP_CLIENT_IP`
5. `REMOTE_ADDR`

Forwarded headers should only be trusted when the deployment ensures they are written or sanitized by a trusted proxy. This method is public for diagnostics and integration, but it is not an authentication primitive.

## Runtime Path Helpers

These public helpers report the module's persistent runtime paths:

```php
$geoip->getDataPath();      // /path/to/site/assets/GeoIP/
$geoip->getDataUrl();       // /site/assets/GeoIP/
$geoip->getGeoIPPath();     // same as getDataPath()
$geoip->getGeoIPUrl();      // same as getDataUrl()
$geoip->getVendorPath();    // /path/to/site/assets/GeoIP/vendor/
$geoip->getAutoloadPath();  // .../vendor/autoload.php
```

Use these for setup tooling and diagnostics. Do not assume a hard-coded server path.

## Advanced Correction Method

### `saveCorrection(array $data): bool`

Save a correction for the current resolved IP and clear the current visitor cache.

Accepted input keys:

- `country`
- `country_code`
- `region`
- `region_code`
- `city`

```php
$saved = $geoip->saveCorrection([
    'country' => 'United States',
    'country_code' => 'US',
    'region' => 'Pennsylvania',
    'region_code' => 'PA',
    'city' => 'Philadelphia',
]);
```

Site code should normally use the provided widget and protected correction endpoint. If calling this method directly, the caller owns authorization, CSRF protection and user intent.

## Public Widget Endpoints

GeoIP handles these query actions before page rendering:

| Method | Query | Purpose |
| --- | --- | --- |
| `GET` | `?geoip_action=fragment` | Return private/no-store widget markup as JSON. |
| `GET` | `?geoip_action=csrf` | Return a current ProcessWire CSRF name and token as private/no-store JSON. |
| `POST` | `?geoip_action=correct` | Validate CSRF and save the visitor correction. |

These endpoints are implementation surfaces for the bundled widget. Prefer `renderLocationWidget()` over constructing their URLs manually.

## Configuration Keys

The module currently exposes these settings:

| Key | Type | Default |
| --- | --- | --- |
| `enable_logging` | `bool` | `true` |
| `log_retention_days` | `int` | `90` |
| `show_correction_widget` | `bool` | `true` |
| `enable_embedded_widget` | `bool` | `false` |
| `session_cache` | `bool` | `true` |
| `http_fallback_enabled` | `bool` | `false` |
| `ipgeolocation_api_key` | `string` | empty |
| `http_timeout` | `int` | `2`, clamped to 1–10 seconds |
| `fallback_country_code` | `string` | `US` |
| `fallback_region_code` | `string` | empty |
| `fallback_city` | `string` | empty |

Use ProcessWire module configuration APIs or the admin configuration screen. Do not edit implementation defaults to configure one site.

## Internal APIs

The following are implementation details and are not stable site-template APIs:

- classes in `src/Lookup/`, `src/Storage/`, `src/Frontend/` and `src/Traits/`;
- protected module methods;
- the `geoip_log` and `geoip_corrections` schemas;
- widget DOM hooks and asset filenames.

If internal code and this document disagree about a public signature, treat that as a documentation defect and verify the installed module version before use.
