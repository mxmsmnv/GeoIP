# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [1.3.0] - 2026-07-30

### Added

- Added deferred embedded widgets through
  `renderLocationWidget(['defer' => true])`. Cacheable HTML contains only a
  stable placeholder; visitor-specific location and a current CSRF token load
  from a private/no-store fragment endpoint.

### Fixed

- Location correction now validates ProcessWire CSRF tokens.
- Fragment and correction responses explicitly disable shared caching.
- Aligned the frontend and Process module release versions.

## [1.2.0] - 2026-07-29

### Changed

- The embedded location widget is now a compact city row that opens a Designsystemet-compatible native dialog.
- Visitors can confirm the detected location or reveal the correction form to choose another one.
- The dialog uses native command controls, accessible labeling, focused editing and responsive form layout.

## [1.1.0] - 2026-07-29

### Added

- Independent `enable_embedded_widget` setting for site-shell integrations.
- Public `$geoip->renderLocationWidget()` API with accessible embedded markup.
- Editable country and region codes in addition to country, region and city.
- Shared frontend CSS and JavaScript assets without inline event handlers.

### Changed

- The automatic floating widget and manually embedded widget now use the same correction form and endpoint.
- Location summaries use the natural `City, Region, Country` order and omit empty parts.

## [1.0.2] - 2026-07-23

### Added

- Optional IPGeolocation.io HTTPS fallback with API key and configurable timeout.
- Lookup `source` metadata for MaxMind, IPGeolocation.io and static fallback results.
- `DOCUMENTATION.md` for complete setup, API reference and integration examples.

### Changed

- Split lookup providers, result normalization, persistence and correction widget rendering into focused classes under `src/`.
- Reduced `README.md` to a concise project overview matching the Vox module style.
- Local MaxMind lookup remains the first choice; static fallback values are applied only after local and HTTP providers fail.

## [1.0.1] - 2026-03-15

### Added

- Initial public release
- MaxMind GeoLite2-City and GeoLite2-Country database support
- Country, region, city, ZIP, lat/lon, timezone, continent detection
- Cloudflare (`HTTP_CF_CONNECTING_IP`) and standard proxy header support
- `inCountry()`, `inRegion()`, `inCity()`, `showIf()`, `getField()` template helpers
- `$geoip` wire variable available in all templates
- In-memory and session-based result caching (configurable)
- Frontend correction widget — fixed position, dismissible, saves per-IP override
- User correction POST endpoint (`/?geoip_action=correct`)
- Corrections stored in `geoip_corrections` table (UNIQUE per IP, upserted)
- Session cleared on correction save — geo re-detects with correction applied immediately
- Lookup logging to `geoip_log` table — one entry per unique IP per session
- Configurable log retention in days; manual prune button in admin
- Admin panel at Setup → GeoIP with three tabs: Log, Corrections, IP Lookup
- Log tab: paginated table, stat cards (total, today, corrections, top countries)
- Corrections tab: inline edit and delete per correction
- IP Lookup tab: grouped result card (Location / Coordinates / Meta)
- DB status notice in admin and module config — shows missing Composer package or database with exact commands
- Local Composer setup: `geoip2/geoip2` installed into `site/assets/GeoIP/vendor/`
- `composer.json` auto-created in `site/assets/GeoIP/` on module install
- Path helpers: `getDataPath()`, `getGeoIPPath()`, `getVendorPath()`, `getAutoloadPath()`
- Local autoload loaded automatically from `site/assets/GeoIP/vendor/autoload.php`
- Fallback values for country/region/city when detection fails
- `geoip-admin` permission for admin panel access
- Tables preserved on module uninstall
- `ProcessGeoIP::___install()` creates Setup → GeoIP page
- `ConfigurableModule` with static `getModuleConfigInputfields()` — setup status + all settings in one screen
- Module config shows green ready / red composer missing / yellow DB missing status

### Database tables

- `geoip_log` — ip, country_code, region_code, city, status, created
- `geoip_corrections` — ip, country, country_code, region, region_code, city, created
