# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

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