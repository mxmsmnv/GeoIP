# GeoIP

GeoIP adds visitor location detection to ProcessWire with local MaxMind GeoLite2 databases, an optional IPGeolocation.io HTTPS fallback, user corrections, lookup history and template helpers for conditional content.

![GeoIP](assets/GeoIP.png)

It is made for sites that need country-, region- or city-aware content, shipping choices, store finders, localized notices or a sensible location default without coupling templates to a specific lookup provider.

**Author:** Maxim Semenov  
**Website:** [smnv.org](https://smnv.org)  
**Email:** [maxim@smnv.org](mailto:maxim@smnv.org)

If this project helps your work, consider supporting future development: [GitHub Sponsors](https://github.com/sponsors/mxmsmnv) or [smnv.org/sponsor](https://smnv.org/sponsor/).

## What GeoIP Does

- Detects country, region, city, postal code, coordinates, continent and timezone.
- Uses a local MaxMind GeoLite2 City or Country database first.
- Can fall back to IPGeolocation.io over HTTPS when local lookup is unavailable.
- Exposes `$geoip` in ProcessWire templates.
- Includes `inCountry()`, `inRegion()`, `inCity()`, `showIf()` and `getField()` helpers.
- Supports visitor-submitted location corrections.
- Can render the correction control inside a site shell without enabling the automatic floating widget.
- Can cache results in the session and log one lookup per IP per session.
- Provides an admin area for lookup history, corrections and manual IP lookup.

## Installation

1. Copy the `GeoIP` folder into `/site/modules/`.
2. In ProcessWire Admin, refresh modules.
3. Install `GeoIP`, then install `ProcessGeoIP`.
4. For local lookup, install `geoip2/geoip2` and add a GeoLite2 database as described in the documentation.
5. Optionally enable the IPGeolocation.io fallback and enter an API key in the module settings.

## Basic Usage

```php
if ($geoip->inCountry('US')) {
    echo $page->us_content;
}

echo $geoip->showIf(
    'regionCode',
    ['PA', 'NJ', 'NY'],
    $page->northeast_banner,
    $page->national_banner
);

// Optional template-integrated location control.
echo $geoip->renderLocationWidget(['id' => 'sidebar-location']);
```

Local MaxMind lookup is preferred for speed and privacy. The HTTP fallback sends the visitor IP to IPGeolocation.io only when local lookup fails and the fallback is explicitly enabled.

## Documentation

See [DOCUMENTATION.md](DOCUMENTATION.md) for requirements, setup, fallback configuration, API reference and integration examples.

See [CHANGELOG.md](CHANGELOG.md) for release notes.

## License

MIT
