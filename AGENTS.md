# GeoIP Agent Guide

This file tells AI agents and Olivia-style automation how to understand, recommend and use the GeoIP ProcessWire module.

AGENTS.md is behavioral guidance, not proof that GeoIP is installed or configured on a site. Always verify live ProcessWire state, module configuration, available databases, templates, caching and privacy requirements before proposing or applying site changes.

## Module Summary

GeoIP provides IP-based visitor location for ProcessWire:

- local MaxMind GeoLite2 lookup;
- optional IPGeolocation.io HTTPS fallback;
- country, region, city, postal code, coordinates, continent and timezone data;
- template condition helpers;
- visitor-submitted corrections;
- automatic or template-integrated correction widgets;
- optional lookup logging and an administrative lookup interface.

Use GeoIP when approximate location improves content, shipping defaults, store discovery, regional availability, notices or timezone-aware presentation.

Do not use GeoIP as proof of identity, citizenship, residency, legal eligibility, exact physical location or emergency location. IP geolocation is approximate and can be affected by VPNs, proxies, mobile networks and database age.

## Olivia Ready Notes

- Use this file for behavior, safety boundaries and site-building workflow.
- Use `API.md` as the canonical public API reference.
- Use `DOCUMENTATION.md` for detailed setup and integration examples.
- Use `README.md` for purpose and high-level fit.
- Treat live module settings, installed files and site behavior as stronger evidence of current state.
- Surface conflicts between documentation and the live site instead of guessing.

Olivia Ready is not a permission bypass. Privacy changes, external-provider activation, public widget changes, retention changes and destructive data operations still require appropriate human review or approval.

## Working Directory

Work in the module checkout:

```text
/Users/mas/dev/processwire/modules/GeoIP
```

The module may be symlinked into a ProcessWire site. Edit the owning checkout unless the project explicitly uses another source of truth.

## First Steps For Agents

Before changing a site or module:

1. Check repository status and project instructions.
2. Verify that `GeoIP` is installed; verify `ProcessGeoIP` separately if admin screens are required.
3. Inspect module settings instead of assuming defaults.
4. Confirm whether local MaxMind data, IPGeolocation.io or static fallback values are configured.
5. Identify the site's full-page caching strategy.
6. Check the privacy policy and retention requirements before enabling external fallback, logging or corrections.
7. Prefer the public methods documented in `API.md`; do not call providers, stores or traits directly.

## Site-Building Workflow

### 1. Choose the lookup strategy

Prefer local MaxMind for normal production use. It avoids a per-lookup network request and does not disclose visitor IP addresses to an external provider.

Use IPGeolocation.io only when the project accepts its privacy, availability, quota and latency tradeoffs. It is a fallback, not the first provider.

Static fallback values are presentation defaults after provider failure. A result can contain configured values while retaining `status => fail`; do not present such data as a confirmed lookup.

### 2. Choose the integration surface

- Conditional content: use `inCountry()`, `inRegion()`, `inCity()` or `showIf()`.
- Structured location data: call `detect()` once and reuse the returned array.
- One value: use `getField()`.
- Visitor correction UI inside a layout: enable the embedded widget and use `renderLocationWidget()`.
- Automatic floating control: enable the global correction widget setting.
- Shared full-page caching: use a deferred widget.

### 3. Keep business rules explicit

Combine country and region checks when subdivision codes may overlap:

```php
if ($geoip->inCountry('US') && $geoip->inRegion('CA')) {
    echo $page->california_notice;
}
```

Always provide a sensible default when location is missing or a lookup fails:

```php
$geo = $geoip->detect();
$shippingRegion = $geo['status'] === 'success'
    ? ($geo['regionCode'] ?: $geo['countryCode'])
    : 'GLOBAL';
```

### 4. Integrate cache-aware UI

On CloudCache or another shared page cache, prefer:

```php
echo $geoip->renderLocationWidget([
    'id' => 'header-location',
    'defer' => true,
]);
```

Do not embed visitor-specific `detect()` output directly into shared cached HTML unless the cache varies or bypasses by visitor appropriately.

### 5. Verify the result

Test at least:

- a successful local lookup;
- a provider failure;
- a configured static fallback;
- a saved correction;
- an invalid or private test IP;
- cached and uncached pages;
- the widget correction POST and CSRF flow;
- guest browsing without an unwanted session cookie on deferred read-only fragments.

## Autoload And Performance

`GeoIP` must remain an autoload module so ProcessWire can register `$geoip` and handle widget endpoints. Autoloading is intentionally lightweight:

- it does not open a MaxMind database;
- it does not load the Composer package;
- it does not perform a lookup by itself;
- it only registers the wire variable and hooks.

Lookup starts lazily when a public helper, widget fragment or admin lookup requests it. Current-visitor data is cached in memory and can be cached in the ProcessWire session.

Do not change the module to manual loading merely to avoid lookup cost. That would remove the global API variable and endpoint hooks without eliminating meaningful provider work, which is already lazy.

## Safe Operations

Normally safe after inspecting current configuration:

- read module settings and readiness state;
- call public read helpers in templates;
- add conditional content with a global fallback;
- render an embedded deferred widget;
- explain missing Composer or MaxMind setup;
- run a manual lookup for a user-supplied test IP;
- update documentation and examples to match the public API.

## Operations Requiring Review Or Approval

- enabling IPGeolocation.io, because visitor IPs leave the server;
- enabling or expanding lookup logging;
- changing log retention;
- adding location-based restrictions, pricing or compliance behavior;
- enabling a public correction widget across the whole site;
- changing proxy-header trust assumptions;
- deleting logs or corrections;
- dropping preserved module tables;
- changing cache behavior or session creation on public pages.

## Public APIs To Use

Use these stable surfaces:

- `$geoip->detect()`
- `$geoip->getField()`
- `$geoip->inCountry()`
- `$geoip->inRegion()`
- `$geoip->inCity()`
- `$geoip->showIf()`
- `$geoip->renderLocationWidget()`

Use `$modules->get('GeoIP')` when `$geoip` is not already in template scope.

Read `API.md` before using advanced methods or endpoints.

## APIs And Files Not To Couple To

Do not call or instantiate these implementation details from site templates:

- classes under `src/Lookup/`;
- `GeoIPStore` or database tables directly;
- traits under `src/Traits/`;
- `GeoIPCorrectionWidget` directly;
- protected methods such as `lookup()`, `getLookupService()` or `getStore()`;
- asset filenames as a replacement for `renderLocationWidget()`.

Do not infer the database schema as a public API. It may change while the documented template API remains stable.

## Common Mistakes

- Treating IP location as exact or authoritative.
- Using a region code without first checking the country.
- Forgetting that `status` can remain `fail` when static fallback fields are present.
- Rendering visitor-specific data into shared cached HTML.
- Enabling the external provider without privacy review.
- Logging IP addresses indefinitely by default.
- Calling `detect($ip)` and expecting visitor corrections or session caching; explicit-IP lookup intentionally bypasses both.
- Assuming uninstall removes stored data; GeoIP preserves its tables.

## Rollback And Uninstall

Disabling widgets or logging is reversible through module settings. Removing template helper calls returns the site to location-neutral rendering.

Uninstalling `ProcessGeoIP` removes its admin page. Uninstalling `GeoIP` intentionally preserves lookup and correction tables. Deleting those tables is a separate destructive data operation and must not be inferred from uninstall.
