<?php
if (!defined("PROCESSWIRE")) die();

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
            'version'  => 101,
            'summary'  => 'MaxMind GeoLite2-based geolocation. Country/region/city detection with user correction support and conditional content blocks.',
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

    // ── Install / uninstall ──────────────────────────────────────────────────

    public function ___install(): void
    {
        $this->createTables();
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

    // ── MaxMind lookup ───────────────────────────────────────────────────────

    protected function lookup(string $ip): array
    {
        // Load local vendor autoload if not yet loaded
        $autoload = $this->getAutoloadPath();
        if (file_exists($autoload) && !class_exists('\GeoIp2\Database\Reader')) {
            require_once $autoload;
        }

        $dbDir     = $this->getGeoIPPath();
        $cityDB    = $dbDir . 'GeoLite2-City.mmdb';
        $countryDB = $dbDir . 'GeoLite2-Country.mmdb';

        $base = [
            'ip'          => $ip,
            'country'     => '',
            'countryCode' => '',
            'continent'   => '',
            'region'      => '',
            'regionCode'  => '',
            'city'        => '',
            'zip'         => '',
            'lat'         => null,
            'lon'         => null,
            'timezone'    => '',
            'corrected'   => false,
            'status'      => 'fail',
        ];

        $applyFallbacks = function (array $d): array {
            if ($d['status'] !== 'success') {
                if ($this->get('fallback_country_code')) $d['countryCode'] = $this->get('fallback_country_code');
                if ($this->get('fallback_region_code'))  $d['regionCode']  = $this->get('fallback_region_code');
                if ($this->get('fallback_city'))         $d['city']        = $this->get('fallback_city');
            }
            return $d;
        };

        $dbFile = file_exists($cityDB) ? $cityDB : (file_exists($countryDB) ? $countryDB : null);

        if ($dbFile === null) {
            $base['message'] = 'No GeoLite2 database found in ' . $dbDir;
            return $applyFallbacks($base);
        }

        if (!class_exists('\GeoIp2\Database\Reader')) {
            $base['message'] = 'geoip2/geoip2 Composer package not found.';
            return $applyFallbacks($base);
        }

        try {
            $reader = new \GeoIp2\Database\Reader($dbFile);
            $record = str_contains($dbFile, 'City') ? $reader->city($ip) : $reader->country($ip);

            $base['country']     = $record->country->name    ?? '';
            $base['countryCode'] = $record->country->isoCode ?? '';
            $base['continent']   = $record->continent->name  ?? '';

            if (isset($record->mostSpecificSubdivision)) {
                $sub = $record->mostSpecificSubdivision;
                $base['region']     = $sub->name    ?? '';
                $base['regionCode'] = $sub->isoCode ?? '';
            }

            if (isset($record->city))     $base['city'] = $record->city->name    ?? '';
            if (isset($record->postal))   $base['zip']  = $record->postal->code  ?? '';
            if (isset($record->location)) {
                $base['lat']      = $record->location->latitude;
                $base['lon']      = $record->location->longitude;
                $base['timezone'] = $record->location->timeZone ?? '';
            }

            $base['status'] = 'success';

        } catch (\Throwable $e) {
            $base['message'] = $e->getMessage();
        }

        return $applyFallbacks($base);
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
        $stmt = $this->wire('database')->prepare(
            "SELECT * FROM `" . self::TABLE_CORRECTIONS . "` WHERE ip = :ip ORDER BY created DESC LIMIT 1"
        );
        $stmt->execute([':ip' => $ip]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function saveCorrection(array $data): bool
    {
        $san    = $this->wire('sanitizer');
        $ip     = $this->getClientIP();
        $stmt   = $this->wire('database')->prepare("
            INSERT INTO `" . self::TABLE_CORRECTIONS . "`
                (ip, country, country_code, region, region_code, city, created)
            VALUES
                (:ip, :country, :cc, :region, :rc, :city, NOW())
            ON DUPLICATE KEY UPDATE
                country=VALUES(country), country_code=VALUES(country_code),
                region=VALUES(region), region_code=VALUES(region_code),
                city=VALUES(city), created=NOW()
        ");

        $ok = $stmt->execute([
            ':ip'      => $ip,
            ':country' => $san->text($data['country']      ?? ''),
            ':cc'      => strtoupper($san->text($data['country_code'] ?? '')),
            ':region'  => $san->text($data['region']       ?? ''),
            ':rc'      => strtoupper($san->text($data['region_code']  ?? '')),
            ':city'    => $san->text($data['city']         ?? ''),
        ]);

        if ($ok) {
            $this->wire('session')->remove(self::SESSION_KEY);
            $this->geoData = null;
        }

        return $ok;
    }

    // ── Logging ───────────────────────────────────────────────────────────────

    protected function logLookup(array $data): void
    {
        $session   = $this->wire('session');
        $loggedIPs = $session->get('geoip_logged') ?? [];
        if (in_array($data['ip'], $loggedIPs, true)) return;

        try {
            $stmt = $this->wire('database')->prepare("
                INSERT INTO `" . self::TABLE_LOG . "`
                    (ip, country_code, region_code, city, status, created)
                VALUES (:ip, :cc, :rc, :city, :status, NOW())
            ");
            $stmt->execute([
                ':ip'     => $data['ip'],
                ':cc'     => $data['countryCode'] ?? '',
                ':rc'     => $data['regionCode']  ?? '',
                ':city'   => $data['city']        ?? '',
                ':status' => $data['status']      ?? '',
            ]);
            $loggedIPs[] = $data['ip'];
            $session->set('geoip_logged', $loggedIPs);
        } catch (\Throwable $e) {
            $this->wire('log')->save('geoip', 'logLookup error: ' . $e->getMessage());
        }
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

    protected function renderCorrectionWidget(array $geo): string
    {
        $country  = htmlspecialchars($geo['country']     ?? '', ENT_QUOTES);
        $cc       = htmlspecialchars($geo['countryCode'] ?? '', ENT_QUOTES);
        $region   = htmlspecialchars($geo['region']      ?? '', ENT_QUOTES);
        $rc       = htmlspecialchars($geo['regionCode']  ?? '', ENT_QUOTES);
        $city     = htmlspecialchars($geo['city']        ?? '', ENT_QUOTES);
        $endpoint = './?geoip_action=correct';

        return <<<HTML
<div id="geoip-widget" style="position:fixed;bottom:16px;right:16px;z-index:9999;font-family:system-ui,sans-serif;font-size:13px;background:#fff;border:1px solid #ddd;border-radius:8px;padding:12px 16px;box-shadow:0 4px 16px rgba(0,0,0,.12);max-width:280px">
  <div style="font-weight:600;margin-bottom:6px">&#128205; Your location</div>
  <div style="color:#555;margin-bottom:8px">{$country}, {$region}, {$city}</div>
  <div id="geoip-form" style="display:none">
    <input type="hidden" id="geoip-cc" value="{$cc}">
    <input type="hidden" id="geoip-rc" value="{$rc}">
    <div style="margin-bottom:4px"><input type="text" id="geoip-c"  placeholder="Country"      value="{$country}" style="width:100%;box-sizing:border-box;padding:4px 6px;border:1px solid #ccc;border-radius:4px"></div>
    <div style="margin-bottom:4px"><input type="text" id="geoip-r"  placeholder="Region/State" value="{$region}"  style="width:100%;box-sizing:border-box;padding:4px 6px;border:1px solid #ccc;border-radius:4px"></div>
    <div style="margin-bottom:8px"><input type="text" id="geoip-ci" placeholder="City"         value="{$city}"    style="width:100%;box-sizing:border-box;padding:4px 6px;border:1px solid #ccc;border-radius:4px"></div>
    <button onclick="geoipSave()" style="background:#2d6df6;color:#fff;border:none;padding:5px 12px;border-radius:4px;cursor:pointer;margin-right:6px">Save</button>
    <button onclick="document.getElementById('geoip-form').style.display='none'" style="background:#eee;border:none;padding:5px 10px;border-radius:4px;cursor:pointer">Cancel</button>
  </div>
  <div style="margin-top:6px">
    <a href="#" onclick="document.getElementById('geoip-form').style.display='block';return false" style="color:#2d6df6;text-decoration:none;font-size:12px">Incorrect? Fix it</a>
    &nbsp;&middot;&nbsp;
    <a href="#" onclick="document.getElementById('geoip-widget').remove();return false" style="color:#aaa;text-decoration:none;font-size:12px">&#10005;</a>
  </div>
</div>
<script>
function geoipSave(){
  var fd=new FormData();
  fd.append('country',document.getElementById('geoip-c').value);
  fd.append('country_code',document.getElementById('geoip-cc').value);
  fd.append('region',document.getElementById('geoip-r').value);
  fd.append('region_code',document.getElementById('geoip-rc').value);
  fd.append('city',document.getElementById('geoip-ci').value);
  fetch('{$endpoint}',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.success)location.reload();});
}
</script>
HTML;
    }

    // ── DB tables ─────────────────────────────────────────────────────────────

    protected function createTables(): void
    {
        $db = $this->wire('database');

        $db->exec("CREATE TABLE IF NOT EXISTS `" . self::TABLE_LOG . "` (
            `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `ip`           VARCHAR(45)  NOT NULL DEFAULT '',
            `country_code` VARCHAR(2)   NOT NULL DEFAULT '',
            `region_code`  VARCHAR(10)  NOT NULL DEFAULT '',
            `city`         VARCHAR(100) NOT NULL DEFAULT '',
            `status`       VARCHAR(20)  NOT NULL DEFAULT '',
            `created`      DATETIME     NOT NULL,
            PRIMARY KEY (`id`),
            INDEX `idx_ip`      (`ip`),
            INDEX `idx_created` (`created`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $db->exec("CREATE TABLE IF NOT EXISTS `" . self::TABLE_CORRECTIONS . "` (
            `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `ip`           VARCHAR(45)  NOT NULL DEFAULT '',
            `country`      VARCHAR(100) NOT NULL DEFAULT '',
            `country_code` VARCHAR(2)   NOT NULL DEFAULT '',
            `region`       VARCHAR(100) NOT NULL DEFAULT '',
            `region_code`  VARCHAR(10)  NOT NULL DEFAULT '',
            `city`         VARCHAR(100) NOT NULL DEFAULT '',
            `created`      DATETIME     NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_ip` (`ip`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
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
        return [
            'enable_logging'         => 1,
            'log_retention_days'     => 90,
            'show_correction_widget' => 1,
            'session_cache'          => 1,
            'fallback_country_code'  => 'US',
            'fallback_region_code'   => '',
            'fallback_city'          => '',
        ];
    }

    public static function getModuleConfigInputfields(array $data)
    {
        $data    = array_merge(self::getDefaultConfig(), $data);
        $modules = wire('modules');
        $wrapper = new InputfieldWrapper();

        // Setup status notice — use instance to get correct paths
        $module      = wire('modules')->get('GeoIP');
        $dataPath    = $module->getDataPath();
        $geoipPath   = $module->getGeoIPPath();
        $autoload    = $module->getAutoloadPath();

        // Try loading local autoload so class_exists check works
        if (file_exists($autoload) && !class_exists('\\GeoIp2\\Database\\Reader')) {
            require_once $autoload;
        }

        $hasComposer = class_exists('\\GeoIp2\\Database\\Reader');
        $hasCity     = file_exists($geoipPath . 'GeoLite2-City.mmdb');
        $hasCnt      = file_exists($geoipPath . 'GeoLite2-Country.mmdb');
        $hasDb       = $hasCity || $hasCnt;
        $notice      = '';

        if (!$hasComposer) {
            $notice .= "<div class='uk-alert uk-alert-danger' style='margin-bottom:10px'>
                <p><i class='fa fa-exclamation-circle'></i>
                <strong>Composer package <code>geoip2/geoip2</code> not installed.</strong></p>
                <p>Run this command:</p>
                <pre style='background:#fff;border:1px solid #ddd;padding:6px 10px;border-radius:3px;margin:4px 0 8px'>cd {$dataPath} &amp;&amp; composer require geoip2/geoip2</pre>
                <p class='description'>Composer not installed?
                <a href='https://getcomposer.org/download/' target='_blank'>getcomposer.org/download</a></p>
            </div>";
        }

        if (!$hasDb) {
            $notice .= "<div class='uk-alert uk-alert-warning' style='margin-bottom:10px'>
                <p><i class='fa fa-exclamation-triangle'></i>
                <strong>No GeoLite2 database found.</strong></p>
                <ol style='margin:4px 0 4px 18px;padding:0'>
                    <li>Register free at <a href='https://www.maxmind.com/en/geolite2/signup' target='_blank'>maxmind.com</a></li>
                    <li>Download <code>GeoLite2-City.mmdb</code> (recommended) or <code>GeoLite2-Country.mmdb</code></li>
                    <li>Upload to: <code>{$geoipPath}</code></li>
                </ol>
            </div>";
        }

        if ($hasComposer && $hasDb) {
            $found = array_filter(['GeoLite2-City.mmdb' => $hasCity, 'GeoLite2-Country.mmdb' => $hasCnt]);
            $notice .= "<div class='uk-alert uk-alert-success' style='margin-bottom:10px'>
                <i class='fa fa-check-circle'></i>
                Ready. Database: <strong>" . implode(', ', array_keys($found)) . "</strong>
            </div>";
        }

        if ($notice) {
            $f = wire('modules')->get('InputfieldMarkup');
            $f->label = 'Setup Status';
            $f->value = $notice;
            $wrapper->add($f);
        }


        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'enable_logging');
        $f->label   = 'Enable lookup logging to database';
        $f->value   = 1;
        $f->checked = !empty($data['enable_logging']);
        $wrapper->add($f);

        $f = $modules->get('InputfieldInteger');
        $f->attr('name', 'log_retention_days');
        $f->label       = 'Log retention (days)';
        $f->description = 'Logs older than this will be pruned on demand. Set 0 to keep forever.';
        $f->value       = (int) ($data['log_retention_days'] ?? 90);
        $wrapper->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'show_correction_widget');
        $f->label   = 'Show "Fix my location" widget on frontend pages';
        $f->value   = 1;
        $f->checked = !empty($data['show_correction_widget']);
        $wrapper->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'session_cache');
        $f->label   = 'Cache geo result in session (recommended)';
        $f->value   = 1;
        $f->checked = !empty($data['session_cache']);
        $wrapper->add($f);

        $fs = $modules->get('InputfieldFieldset');
        $fs->label = 'Fallback values (used when detection fails)';

        $f = $modules->get('InputfieldText');
        $f->attr('name', 'fallback_country_code');
        $f->label       = 'Country code';
        $f->value       = $data['fallback_country_code'] ?? 'US';
        $f->columnWidth = 33;
        $fs->add($f);

        $f = $modules->get('InputfieldText');
        $f->attr('name', 'fallback_region_code');
        $f->label       = 'Region/State code';
        $f->value       = $data['fallback_region_code'] ?? '';
        $f->columnWidth = 33;
        $fs->add($f);

        $f = $modules->get('InputfieldText');
        $f->attr('name', 'fallback_city');
        $f->label       = 'City';
        $f->value       = $data['fallback_city'] ?? '';
        $f->columnWidth = 34;
        $fs->add($f);

        $wrapper->add($fs);

        return $wrapper;
    }

}