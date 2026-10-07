# GeoIP

GeoIP adds privacy-conscious visitor location detection to ProcessWire with local MaxMind GeoLite2 databases, an optional IPGeolocation.io fallback, visitor corrections, lookup history and template helpers.

![GeoIP](assets/GeoIP.png)

It is made for sites that need country-, region- or city-aware content, shipping choices, store finders, regional notices or a useful location default without coupling templates to one lookup provider.

**Author:** Maxim Semenov  
**Website:** [smnv.org](https://smnv.org)  
**Email:** [maxim@smnv.org](mailto:maxim@smnv.org)

If this project helps your work, consider supporting future development: [GitHub Sponsors](https://github.com/sponsors/mxmsmnv) or [smnv.org/sponsor](https://smnv.org/sponsor/).

## What GeoIP Does

- Detects country, region, city, postal code, coordinates, continent and timezone.
- Uses a local MaxMind GeoLite2 City or Country database first.
- Can fall back to IPGeolocation.io over HTTPS when local lookup is unavailable.
- Exposes `$geoip` as a ProcessWire API variable in templates.
- Provides `detect()`, `getField()`, `inCountry()`, `inRegion()`, `inCity()` and `showIf()` helpers.
- Stores one optional visitor correction per IP address.
- Provides automatic floating and template-integrated correction widgets.
- Supports deferred widgets that keep shared full-page HTML free of visitor-specific location data and CSRF tokens.
- Can cache the current visitor result in the ProcessWire session and log one lookup per IP per session.
- Adds a Setup → GeoIP admin area for lookup history, corrections and manual IP lookup.

## Performance Model

GeoIP is a ProcessWire autoload module so `$geoip` and its request endpoints are always available. Autoloading does **not** open a MaxMind database or perform a location lookup.

Detection is lazy. The provider chain runs only when a template calls a location helper, the admin performs a manual lookup, or a widget fragment requests visitor data. The result is cached in memory for the request and can also be cached in the session.

The MaxMind Composer autoloader is loaded only when a local lookup is requested. The optional HTTP provider is called only after local lookup fails and only when it is explicitly enabled.

## Installation

1. Copy the `GeoIP` folder into `/site/modules/`.
2. In ProcessWire Admin, refresh modules.
3. Install **GeoIP**, then install **ProcessGeoIP**.
4. For local lookup, run `composer require geoip2/geoip2` inside `site/assets/GeoIP/`.
5. Add `GeoLite2-City.mmdb` or `GeoLite2-Country.mmdb` to `site/assets/GeoIP/`.
6. Optionally enable IPGeolocation.io fallback and enter an API key in the module settings.

Composer is not required on the production server at runtime. You can build `site/assets/GeoIP/vendor/` locally and upload it together with the GeoLite2 database.

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

$location = $geoip->detect();
echo $location['city'];
```

## Location Widget

Enable the template-integrated widget in the module settings, then render it where it belongs in the site shell:

```php
echo $geoip->renderLocationWidget([
    'id' => 'site-location',
    'defer' => true,
]);
```

Use `defer => true` on pages served through CloudCache or another shared full-page cache. The cached page contains only a stable placeholder; visitor-specific markup is loaded from a private, no-store endpoint.

## Privacy And Operations

Local MaxMind lookup keeps the visitor IP on your server. Enabling IPGeolocation.io sends the IP address to that provider over HTTPS and should be reflected in the site's privacy policy.

Lookup logging and visitor corrections store IP addresses in the database. Configure retention for the site's legal and operational requirements. Module uninstall preserves both tables intentionally.

## Documentation

See [DOCUMENTATION.md](DOCUMENTATION.md) for complete setup, configuration and integration examples.

See [API.md](API.md) for the public template API, result schema, widget options and endpoints.

See [AGENTS.md](AGENTS.md) for Olivia and AI-agent integration guidance.

See [CHANGELOG.md](CHANGELOG.md) for release notes.

## License

[MIT](LICENSE)
