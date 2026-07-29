<?php
if (!defined("PROCESSWIRE")) die();

require_once __DIR__ . '/src/Lookup/GeoIPResult.php';
require_once __DIR__ . '/src/Lookup/GeoIPMaxMindProvider.php';
require_once __DIR__ . '/src/Lookup/GeoIPGeolocationProvider.php';
require_once __DIR__ . '/src/Lookup/GeoIPLookupService.php';
require_once __DIR__ . '/src/Storage/GeoIPStore.php';
require_once __DIR__ . '/src/Frontend/GeoIPCorrectionWidget.php';
require_once __DIR__ . '/src/Config/GeoIPConfig.php';

/**
 * GeoIP — MaxMind GeoLite2 geolocation module for ProcessWire
 *
 * Detects country/region/city from visitor IP, allows user corrections,
 * logs lookups, and exposes geo data to templates for conditional content.
 *
 * @author Maxim Semenov <maxim@smnv.org> (smnv.org)
 * @license MIT
 */
class GeoIP extends WireData implements Module, ConfigurableModule
{
    // ── Module info ──────────────────────────────────────────────────────────

    public static function getModuleInfo(): array
    {
        return [
            'title'    => 'GeoIP',
            'version'  => 110,
            'summary'  => 'IP geolocation with local MaxMind lookup, optional IPGeolocation.io fallback, user corrections, and conditional content helpers.',
            'author'   => 'Maxim Semenov',
            'href'     => 'https://smnv.org',
            'singular' => true,
            'autoload' => true,
            'icon'     => 'globe',
            'requires' => ['ProcessWire>=3.0.200', 'PHP>=8.2'],
        ];
    }

    // ── Constants ────────────────────────────────────────────────────────────

    const TABLE_LOG         = 'geoip_log';
    const TABLE_CORRECTIONS = 'geoip_corrections';
    const SESSION_KEY       = 'geoip_data';

    // ── Internal cache ───────────────────────────────────────────────────────

    protected ?array $geoData = null;
    protected ?GeoIPLookupService $lookupService = null;
    protected ?GeoIPStore $store = null;
    protected ?GeoIPCorrectionWidget $correctionWidget = null;
    protected bool $correctionWidgetAssetsRendered = false;

    // ── Install / uninstall ──────────────────────────────────────────────────

    public function ___install(): void
    {
        $this->getStore()->createTables();
        $this->createAssetsDir();
        // Admin page is created by ProcessGeoIP::___install() once that module is installed.
    }

    public function ___uninstall(): void
    {
        // Tables are intentionally preserved on uninstall to keep historical data.
    }

    // ── Init ─────────────────────────────────────────────────────────────────

    public function init(): void
    {
        // Make $geoip available in all templates
        $this->wire->set('geoip', $this);

        // Handle correction POST endpoint
        $this->addHookBefore('ProcessPageView::execute', $this, 'handleCorrectionRequest');

        // Inject frontend correction widget if enabled
        if ($this->get('show_correction_widget')) {
            $this->addHookAfter('Page::render', $this, 'injectCorrectionWidget');
        }
    }

    // ── Core: detect geo ─────────────────────────────────────────────────────

    /**
     * Return geo data array for current visitor (or given IP).
     * Result is cached in memory and session.
     *
     * @param string|null $ip  Override IP (admin/debug). Session is NOT written when set.
     */
    public function detect(?string $ip = null): array
    {
        if ($ip === null) {
            if ($this->geoData !== null) return $this->geoData;

            $resolvedIp = $this->getClientIP();

            // Load from session cache (raw geo data without correction)
            if ($this->get('session_cache')) {
                $cached = $this->wire('session')->get(self::SESSION_KEY);
                if (is_array($cached)) {
                    // Always re-apply correction on top of cache —
                    // correction may have been saved after this session entry was written
                    $correction = $this->getUserCorrection($resolvedIp);
                    if ($correction) {
                        $cached['country']     = $correction['country']      ?: $cached['country'];
                        $cached['countryCode'] = $correction['country_code'] ?: $cached['countryCode'];
                        $cached['region']      = $correction['region']       ?: $cached['region'];
                        $cached['regionCode']  = $correction['region_code']  ?: $cached['regionCode'];
                        $cached['city']        = $correction['city']         ?: $cached['city'];
                        $cached['corrected']   = true;
                    } else {
                        $cached['corrected'] = false;
                    }
                    $this->geoData = $cached;
                    return $this->geoData;
                }
            }

            $data = $this->lookup($resolvedIp);

            // Apply correction
            $correction = $this->getUserCorrection($resolvedIp);
            if ($correction) {
                $data['country']     = $correction['country']      ?: $data['country'];
                $data['countryCode'] = $correction['country_code'] ?: $data['countryCode'];
                $data['region']      = $correction['region']       ?: $data['region'];
                $data['regionCode']  = $correction['region_code']  ?: $data['regionCode'];
                $data['city']        = $correction['city']         ?: $data['city'];
                $data['corrected']   = true;
            }

            if ($this->get('session_cache')) {
                $this->wire('session')->set(self::SESSION_KEY, $data);
            }
            $this->geoData = $data;

            if ($this->get('enable_logging')) {
                $this->logLookup($data);
            }

            return $data;
        }

        // Manual IP override (admin lookup) — no session, no correction
        return $this->lookup($ip);
    }

    /** Get a single geo field. e.g. $geoip->getField('countryCode') */
    public function getField(string $field): mixed
    {
        return $this->detect()[$field] ?? null;
    }

    // ── Conditional helpers ───────────────────────────────────────────────────

    public function inCountry(string|array $codes): bool
    {
        $current = strtoupper($this->detect()['countryCode'] ?? '');
        return in_array($current, array_map('strtoupper', (array) $codes), true);
    }

    public function inRegion(string|array $codes): bool
    {
        $current = strtoupper($this->detect()['regionCode'] ?? '');
        return in_array($current, array_map('strtoupper', (array) $codes), true);
    }

    public function inCity(string|array $cities): bool
    {
        $current = strtolower($this->detect()['city'] ?? '');
        return in_array($current, array_map('strtolower', (array) $cities), true);
    }

    /**
     * Render $content only if geo field matches given value(s).
     *
     * echo $geoip->showIf('countryCode', 'US', $page->us_block, $page->global_block);
     * echo $geoip->showIf('regionCode', ['PA','NJ'], '<p>Tristate promo</p>');
     */
    public function showIf(string $field, string|array $values, string $content, string $else = ''): string
    {
        $current = strtolower($this->detect()[$field] ?? '');
        $values  = array_map('strtolower', (array) $values);
        return in_array($current, $values, true) ? $content : $else;
    }

    // ── Lookup providers ─────────────────────────────────────────────────────

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
            $http->setTimeout(max(1, min(10, (int) $this->get('http_timeout'))));
            $httpFallback = new GeoIPGeolocationProvider(
                static fn(string $url): string => (string) $http->get($url),
                (string) $this->get('ipgeolocation_api_key')
            );
        }

        $this->lookupService = new GeoIPLookupService(
            new GeoIPMaxMindProvider($this->getGeoIPPath(), $this->getAutoloadPath()),
            $httpFallback,
            [
                'countryCode' => (string) $this->get('fallback_country_code'),
                'regionCode' => (string) $this->get('fallback_region_code'),
                'city' => (string) $this->get('fallback_city'),
            ]
        );

        return $this->lookupService;
    }

    // ── IP detection ─────────────────────────────────────────────────────────

    public function getClientIP(): string
    {
        $keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];
        foreach ($keys as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = trim(explode(',', $_SERVER[$k])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    // ── Path helpers ─────────────────────────────────────────────────────────

    /** Base directory: site/assets/GeoIP/ */
    public function getDataPath(): string
    {
        return $this->wire('config')->paths->assets . 'GeoIP/';
    }

    public function getDataUrl(): string
    {
        return $this->wire('config')->urls->assets . 'GeoIP/';
    }

    /** Database directory: site/assets/GeoIP/ (same as data root) */
    public function getGeoIPPath(): string
    {
        return $this->getDataPath();
    }

    public function getGeoIPUrl(): string
    {
        return $this->getDataUrl();
    }

    /** Composer vendor: site/assets/GeoIP/vendor/ */
    public function getVendorPath(): string
    {
        return $this->getDataPath() . 'vendor/';
    }

    public function getAutoloadPath(): string
    {
        return $this->getVendorPath() . 'autoload.php';
    }

    // ── User correction ───────────────────────────────────────────────────────

    protected function getUserCorrection(string $ip): ?array
    {
        return $this->getStore()->getCorrection($ip);
    }

    public function saveCorrection(array $data): bool
    {
        $ok = $this->getStore()->saveCorrection($this->getClientIP(), $data);

        if ($ok) {
            $this->wire('session')->remove(self::SESSION_KEY);
            $this->geoData = null;
        }

        return $ok;
    }

    // ── Logging ───────────────────────────────────────────────────────────────

    protected function logLookup(array $data): void
    {
        $this->getStore()->logLookup($data);
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

    // ── AJAX correction handler ───────────────────────────────────────────────

    public function handleCorrectionRequest(HookEvent $event): void
    {
        $input = $this->wire('input');
        if ($input->get('geoip_action') !== 'correct') return;
        if (!$input->requestMethod('POST')) return;

        header('Content-Type: application/json; charset=utf-8');

        $post = $input->post;
        $ok   = $this->saveCorrection([
            'country'      => $post->text('country'),
            'country_code' => $post->text('country_code'),
            'region'       => $post->text('region'),
            'region_code'  => $post->text('region_code'),
            'city'         => $post->text('city'),
        ]);

        echo json_encode(['success' => $ok]);
        exit;
    }

    // ── Correction widget ─────────────────────────────────────────────────────

    public function injectCorrectionWidget(HookEvent $event): void
    {
        if ($this->wire('page')->template == 'admin') return;
        $geo           = $this->detect();
        $event->return = str_replace('</body>', $this->renderCorrectionWidget($geo) . '</body>', $event->return);
    }

    /**
     * Render a location correction control inside a site template.
     *
     * Templates retain control over placement while GeoIP owns detection,
     * correction persistence, endpoint handling and accessible form markup.
     */
    public function renderLocationWidget(array $options = []): string
    {
        if (!$this->get('enable_embedded_widget')) return '';

        $options['variant'] = 'embedded';
        $endpoint = (string)($options['endpoint'] ?? './?geoip_action=correct');
        unset($options['endpoint']);

        return $this->renderCorrectionWidget($this->detect(), $endpoint, $options);
    }

    protected function renderCorrectionWidget(
        array $geo,
        string $endpoint = './?geoip_action=correct',
        array $options = []
    ): string
    {
        $this->correctionWidget ??= new GeoIPCorrectionWidget();
        $markup = $this->correctionWidget->render($geo, $endpoint, $options);
        if ($this->correctionWidgetAssetsRendered) return $markup;

        $this->correctionWidgetAssetsRendered = true;
        $baseUrl = rtrim((string)$this->wire('config')->urls->siteModules, '/')
            . '/GeoIP/assets/';
        $version = self::getModuleInfo()['version'];

        return '<link rel="stylesheet" href="' . $baseUrl . 'geoip-widget.css?v=' . $version . '">'
            . '<script src="' . $baseUrl . 'geoip-widget.js?v=' . $version . '" defer></script>'
            . $markup;
    }

    protected function createAssetsDir(): void
    {
        $dataPath  = $this->getDataPath();
        $geoipPath = $this->getGeoIPPath();

        // GeoIP/ for vendor, geoip/ for databases (shared, may already exist)
        if (!is_dir($dataPath))  wireMkdir($dataPath,  true);
        if (!is_dir($geoipPath)) wireMkdir($geoipPath, true);

        // composer.json so `composer require geoip2/geoip2` installs vendor/ here
        $composerJson = $dataPath . 'composer.json';
        if (!file_exists($composerJson)) {
            file_put_contents($composerJson, json_encode([
                'require' => ['geoip2/geoip2' => '^3.0'],
                'config'  => ['vendor-dir' => 'vendor'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }


    // ── ConfigurableModule ────────────────────────────────────────────────────

    public static function getDefaultConfig(): array
    {
        return GeoIPConfig::defaults();
    }

    public static function getModuleConfigInputfields(array $data)
    {
        return GeoIPConfig::buildInputfields($data);
    }

}
