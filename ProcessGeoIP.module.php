<?php
if (!defined("PROCESSWIRE")) die();

/**
 * ProcessGeoIP - Admin UI for GeoIP module
 *
 * @author Maxim Semenov <maxim@smnv.org> (smnv.org)
 * @license MIT
 */
class ProcessGeoIP extends Process implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title'    => 'ProcessGeoIP',
            'version'  => 101,
            'summary'  => 'Admin UI for GeoIP module.',
            'author'   => 'Maxim Semenov',
            'href'     => 'https://smnv.org',
            'singular' => true,
            'autoload' => false,
            'icon'     => 'globe',
            'requires' => ['GeoIP'],
            'permission'  => 'geoip-admin',
            'permissions' => [
                'geoip-admin' => 'GeoIP: access admin panel',
            ],
        ];
    }

    const TABLE_LOG         = GeoIP::TABLE_LOG;
    const TABLE_CORRECTIONS = GeoIP::TABLE_CORRECTIONS;

    // ── Install / uninstall ──────────────────────────────────────────────────

    public function ___install(): void
    {
        $pages = $this->wire('pages');
        $setup = $pages->get('name=setup, template=admin');
        if (!$setup->id) return;

        $existing = $pages->get('name=geoip, parent=' . $setup->id);
        if ($existing->id) return;

        $p           = new Page();
        $p->template = $this->wire('templates')->get('admin');
        $p->parent   = $setup;
        $p->name     = 'geoip';
        $p->title    = 'GeoIP';
        $p->process  = $this;
        $p->save();
    }

    public function ___uninstall(): void
    {
        $pages = $this->wire('pages');
        $p = $pages->get('name=geoip, template=admin');
        if ($p->id) $pages->delete($p);
    }

    // ── Route ─────────────────────────────────────────────────────────────────

    public function ___execute(): string
    {
        $action = $this->wire('input')->get->pageName('action') ?: 'log';

        // DB notice injected at top of every page
        $notice = $this->renderDbNotice();

        return $notice . match ($action) {
            'corrections' => $this->renderCorrections(),
            'lookup'      => $this->renderLookup(),
            'prune'       => $this->handlePrune(),
            default       => $this->renderLog(),
        };
    }

    // ── DB notice ─────────────────────────────────────────────────────────────

    protected function renderDbNotice(): string
    {
        /** @var GeoIP $geoip */
        $geoip     = $this->wire('modules')->get('GeoIP');
        $dataPath  = $geoip->getDataPath();
        $geoipPath = $geoip->getGeoIPPath();
        $autoload  = $geoip->getAutoloadPath();

        if (file_exists($autoload) && !class_exists('\GeoIp2\Database\Reader')) {
            require_once $autoload;
        }

        $hasComposer = class_exists('\GeoIp2\Database\Reader');
        $hasCity     = file_exists($geoipPath . 'GeoLite2-City.mmdb');
        $hasCnt      = file_exists($geoipPath . 'GeoLite2-Country.mmdb');
        $hasDb       = $hasCity || $hasCnt;
        $out         = '';

        if (!$hasComposer) {
            $out .= "<div class='uk-alert uk-alert-danger' style='margin-bottom:12px'>
                <p><i class='fa fa-exclamation-circle'></i>
                <strong>Composer package <code>geoip2/geoip2</code> not installed.</strong></p>
                <p>Run this command:</p>
                <pre style='background:#fff;border:1px solid #ddd;padding:8px 12px;border-radius:3px;margin:6px 0'>cd {$dataPath} &amp;&amp; composer require geoip2/geoip2</pre>
                <p class='description'>Composer not installed? <a href='https://getcomposer.org/download/' target='_blank'>getcomposer.org/download</a></p>
            </div>";
        }

        if (!$hasDb) {
            $out .= "<div class='uk-alert uk-alert-warning' style='margin-bottom:12px'>
                <p><i class='fa fa-exclamation-triangle'></i>
                <strong>No GeoLite2 database found.</strong></p>
                <ol style='margin:6px 0 6px 20px;padding:0'>
                    <li>Register free at <a href='https://www.maxmind.com/en/geolite2/signup' target='_blank'>maxmind.com</a></li>
                    <li>Download <code>GeoLite2-City.mmdb</code> (recommended) or <code>GeoLite2-Country.mmdb</code></li>
                    <li>Upload to: <code>{$geoipPath}</code></li>
                </ol>
            </div>";
        }

        return $out;
    }


    // ── Log ───────────────────────────────────────────────────────────────────

    protected function renderLog(): string
    {
        $db      = $this->wire('database');
        $pg      = max(1, (int) ($this->wire('input')->get('p') ?? 1));
        $perPage = 20;
        $offset  = ($pg - 1) * $perPage;

        $total = (int) $db->query("SELECT COUNT(*) FROM `" . self::TABLE_LOG . "` WHERE status='success'")->fetchColumn();

        $stmt = $db->prepare("SELECT * FROM `" . self::TABLE_LOG . "` WHERE status='success' ORDER BY created DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit',  $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Fetch failed lookups separately
        $failRows = $db->query("SELECT * FROM `" . self::TABLE_LOG . "` WHERE status!='success' ORDER BY created DESC")->fetchAll(\PDO::FETCH_ASSOC);

        $out  = $this->renderNav('log');
        $out .= $this->renderStats();

        $out .= "<p style='margin-bottom:16px'>
            <a href='?action=prune' class='ui-button'
               onclick=\"return confirm('Prune logs older than configured retention period?')\">
               <i class='fa fa-trash'></i> Prune old logs
            </a>
        </p>";

        if (empty($rows)) {
            $out .= "<p class='description'>No log entries yet. Geo lookups will appear here once visitors access the site.</p>";
            return $out;
        }


        $out .= "<table style='width:100%;border-collapse:collapse;font-size:13px;border:1px solid #ddd'>";
        $out .= "<thead><tr style='background:#f5f5f5;border-bottom:2px solid #ddd'>
            <th style='padding:8px 12px;text-align:left;font-weight:600;color:#555'>#</th>
            <th style='padding:8px 12px;text-align:left;font-weight:600;color:#555'>IP</th>
            <th style='padding:8px 12px;text-align:left;font-weight:600;color:#555'>Country</th>
            <th style='padding:8px 12px;text-align:left;font-weight:600;color:#555'>Region</th>
            <th style='padding:8px 12px;text-align:left;font-weight:600;color:#555'>City</th>
            <th style='padding:8px 12px;text-align:left;font-weight:600;color:#555;white-space:nowrap'>Date</th>
        </tr></thead><tbody>";

        foreach ($rows as $idx => $r) {
            $bg         = $idx % 2 === 0 ? '#fff' : '#fafafa';
            $lookupUrl  = '?action=lookup&ip=' . urlencode($r['ip']);
            $cc         = $r['country_code'] ? htmlspecialchars($r['country_code']) : '<span style="color:#ccc">&mdash;</span>';
            $rc         = $r['region_code']  ? htmlspecialchars($r['region_code'])  : '<span style="color:#ccc">&mdash;</span>';
            $city       = $r['city']         ? htmlspecialchars($r['city'])         : '<span style="color:#ccc">&mdash;</span>';
            // Check if this IP has a correction
            $hasCorrectionBadge = $this->wire('database')
                ->prepare("SELECT id FROM `" . self::TABLE_CORRECTIONS . "` WHERE ip=:ip LIMIT 1");
            $hasCorrectionBadge->execute([':ip' => $r['ip']]);
            $corrBadge = $hasCorrectionBadge->fetchColumn()
                ? " <span style='display:inline-block;width:8px;height:8px;background:#e74c3c;border-radius:50%;margin-left:4px;vertical-align:middle' title='Location corrected'></span>"
                : '';

            $out .= "<tr style='background:{$bg};border-bottom:1px solid #eee'>
                <td style='padding:7px 12px;color:#bbb;font-size:11px;border-right:1px solid #eee'>" . (int) $r['id'] . "</td>
                <td style='padding:7px 12px;border-right:1px solid #eee'>
                    <a href='{$lookupUrl}' style='font-family:monospace;font-size:12px;color:#2d6df6'>" . htmlspecialchars($r['ip']) . "</a>{$corrBadge}
                </td>
                <td style='padding:7px 12px;font-weight:600;border-right:1px solid #eee'>{$cc}</td>
                <td style='padding:7px 12px;border-right:1px solid #eee'>{$rc}</td>
                <td style='padding:7px 12px;border-right:1px solid #eee'>{$city}</td>
                <td style='padding:7px 12px;color:#999;font-size:11px;white-space:nowrap'>" . htmlspecialchars($r['created']) . "</td>
            </tr>";
        }

        $out .= "</tbody></table>";

        // Failed lookups table
        if (!empty($failRows)) {
            $out .= "<h4 style='margin-top:28px;margin-bottom:8px;color:#c0392b'><i class='fa fa-exclamation-triangle'></i> Failed lookups (" . count($failRows) . ")</h4>";
            $out .= "<table style='width:100%;border-collapse:collapse;font-size:13px;border:1px solid #f5c6cb'>";
            $out .= "<thead><tr style='background:#fff5f5;border-bottom:2px solid #f5c6cb'>
                <th style='padding:8px 12px;text-align:left;font-weight:600;color:#c0392b'>#</th>
                <th style='padding:8px 12px;text-align:left;font-weight:600;color:#c0392b'>IP</th>
                <th style='padding:8px 12px;text-align:left;font-weight:600;color:#c0392b'>Status</th>
                <th style='padding:8px 12px;text-align:left;font-weight:600;color:#c0392b;white-space:nowrap'>Date</th>
            </tr></thead><tbody>";
            foreach ($failRows as $idx => $r) {
                $bg        = $idx % 2 === 0 ? '#fff' : '#fff8f8';
                $lookupUrl = '?action=lookup&ip=' . urlencode($r['ip']);
                $out .= "<tr style='background:{$bg};border-bottom:1px solid #fde'>
                    <td style='padding:7px 12px;color:#bbb;font-size:11px;border-right:1px solid #fde'>" . (int) $r['id'] . "</td>
                    <td style='padding:7px 12px;border-right:1px solid #fde'>
                        <a href='{$lookupUrl}' style='font-family:monospace;font-size:12px;color:#c0392b'>" . htmlspecialchars($r['ip']) . "</a>
                    </td>
                    <td style='padding:7px 12px;color:#c0392b;border-right:1px solid #fde'>&#10007; " . htmlspecialchars($r['status']) . "</td>
                    <td style='padding:7px 12px;color:#999;font-size:11px;white-space:nowrap'>" . htmlspecialchars($r['created']) . "</td>
                </tr>";
            }
            $out .= "</tbody></table>";
        }

        $pages = (int) ceil($total / $perPage);
        if ($pages > 1) {
            $out .= "<ul class='uk-pagination' style='margin-top:12px'>";
            for ($i = 1; $i <= $pages; $i++) {
                $cls = $i === $pg ? " class='uk-active'" : '';
                $out .= "<li{$cls}><a href='?action=log&p={$i}'>{$i}</a></li>";
            }
            $out .= "</ul>";
        }

        $out .= "<p class='description' style='margin-top:8px'>Total: {$total} records</p>";

        return $out;
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    protected function renderStats(): string
    {
        $db    = $this->wire('database');
        $total = (int) $db->query("SELECT COUNT(*) FROM `" . self::TABLE_LOG . "`")->fetchColumn();
        $today = (int) $db->query("SELECT COUNT(*) FROM `" . self::TABLE_LOG . "` WHERE DATE(created)=CURDATE()")->fetchColumn();
        $corrs = (int) $db->query("SELECT COUNT(*) FROM `" . self::TABLE_CORRECTIONS . "`")->fetchColumn();
        $topCC = $db->query(
            "SELECT country_code, COUNT(*) c FROM `" . self::TABLE_LOG . "` GROUP BY country_code ORDER BY c DESC LIMIT 5"
        )->fetchAll(\PDO::FETCH_ASSOC);
        $topStr = implode(', ', array_map(fn($r) => $r['country_code'] . ' (' . $r['c'] . ')', $topCC)) ?: '&mdash;';

        $out  = "<div style='display:flex;gap:12px;margin-bottom:20px'>";
        $out .= $this->statCard('Total lookups',    (string) $total,  'globe');
        $out .= $this->statCard('Today',            (string) $today,  'calendar');
        $out .= $this->statCard('User corrections', (string) $corrs,  'pencil');
        $out .= $this->statCard('Top countries',    $topStr,          'bar-chart');
        $out .= "</div>";

        return $out;
    }

    protected function statCard(string $label, string $value, string $icon): string
    {
        return "<div style='flex:1;border:1px solid #e0e0e0;border-radius:3px;padding:10px 14px;background:#fafafa'>
            <div class='description' style='font-size:11px;text-transform:uppercase;letter-spacing:.5px'>
                <i class='fa fa-{$icon}'></i> {$label}
            </div>
            <div style='font-size:1.7em;font-weight:700;margin-top:4px;line-height:1'>{$value}</div>
        </div>";
    }

    // ── Corrections ───────────────────────────────────────────────────────────

    protected function renderCorrections(): string
    {
        $db    = $this->wire('database');
        $input = $this->wire('input');
        $san   = $this->wire('sanitizer');

        if ($input->post('delete_id')) {
            $stmt = $db->prepare("DELETE FROM `" . self::TABLE_CORRECTIONS . "` WHERE id=:id");
            $stmt->execute([':id' => (int) $input->post('delete_id')]);
            $this->message('Correction deleted.');
        }

        if ($input->post('save_id')) {
            $stmt = $db->prepare("UPDATE `" . self::TABLE_CORRECTIONS . "` SET
                country=:country, country_code=:cc, region=:region, region_code=:rc, city=:city
                WHERE id=:id");
            $stmt->execute([
                ':country' => $san->text($input->post('country')),
                ':cc'      => strtoupper($san->text($input->post('country_code'))),
                ':region'  => $san->text($input->post('region')),
                ':rc'      => strtoupper($san->text($input->post('region_code'))),
                ':city'    => $san->text($input->post('city')),
                ':id'      => (int) $input->post('save_id'),
            ]);
            $this->message('Correction saved.');
        }

        $rows = $db->query(
            "SELECT * FROM `" . self::TABLE_CORRECTIONS . "` ORDER BY created DESC"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $out = $this->renderNav('corrections');
        $out .= "<p class='description'>User-submitted location corrections. Each IP can have one active correction which overrides the auto-detected value.</p>";

        if (empty($rows)) {
            $out .= "<p class='description'>No corrections recorded yet.</p>";
            return $out;
        }

        $out .= "<table style='width:100%;border-collapse:collapse;font-size:13px'>";
        $out .= "<thead><tr style='background:#f5f5f5;border-bottom:2px solid #ddd'>
            <th style='padding:8px 12px;text-align:left;font-weight:600;color:#555'>IP</th>
            <th style='padding:8px 12px;text-align:left;font-weight:600;color:#555'>Country</th>
            <th style='padding:8px 12px;text-align:left;font-weight:600;color:#555'>CC</th>
            <th style='padding:8px 12px;text-align:left;font-weight:600;color:#555'>Region</th>
            <th style='padding:8px 12px;text-align:left;font-weight:600;color:#555'>RC</th>
            <th style='padding:8px 12px;text-align:left;font-weight:600;color:#555'>City</th>
            <th style='padding:8px 12px;text-align:left;font-weight:600;color:#555;white-space:nowrap'>Saved</th>
            <th style='padding:8px 12px;text-align:left;font-weight:600;color:#555'></th>
        </tr></thead><tbody>";

        foreach ($rows as $idx => $r) {
            $id    = (int) $r['id'];
            $ipEsc = htmlspecialchars($r['ip'], ENT_QUOTES);
            $bg    = $idx % 2 === 0 ? '#fff' : '#fafafa';
            $lookupUrl = '?action=lookup&ip=' . urlencode($r['ip']);

            $inp = "style='width:100%;box-sizing:border-box;padding:4px 6px;border:1px solid #ddd;border-radius:3px;font-size:12px'";

            $out .= "<form method='post' action='?action=corrections'>";
            $out .= "<input type='hidden' name='save_id' value='{$id}'>";
            $out .= "<tr style='background:{$bg};border-bottom:1px solid #eee'>";
            $out .= "<td style='padding:7px 12px'>
                <a href='{$lookupUrl}' style='font-family:monospace;font-size:12px;color:#2d6df6'>{$ipEsc}</a>
            </td>";
            $out .= "<td style='padding:6px 8px'><input type='text' name='country'      value='" . htmlspecialchars($r['country'],      ENT_QUOTES) . "' {$inp}></td>";
            $out .= "<td style='padding:6px 8px'><input type='text' name='country_code' value='" . htmlspecialchars($r['country_code'], ENT_QUOTES) . "' {$inp} style='width:44px;padding:4px 6px;border:1px solid #ddd;border-radius:3px;font-size:12px;text-transform:uppercase'></td>";
            $out .= "<td style='padding:6px 8px'><input type='text' name='region'       value='" . htmlspecialchars($r['region'],       ENT_QUOTES) . "' {$inp}></td>";
            $out .= "<td style='padding:6px 8px'><input type='text' name='region_code'  value='" . htmlspecialchars($r['region_code'],  ENT_QUOTES) . "' {$inp} style='width:44px;padding:4px 6px;border:1px solid #ddd;border-radius:3px;font-size:12px;text-transform:uppercase'></td>";
            $out .= "<td style='padding:6px 8px'><input type='text' name='city'         value='" . htmlspecialchars($r['city'],         ENT_QUOTES) . "' {$inp}></td>";
            $out .= "<td style='padding:7px 12px;color:#999;font-size:11px;white-space:nowrap'>" . htmlspecialchars($r['created']) . "</td>";
            $out .= "<td style='padding:6px 8px;white-space:nowrap'>
                <button type='submit' class='ui-button' title='Save'><i class='fa fa-save'></i> Save</button>
                <button type='button' class='uk-button uk-button-danger' title='Delete'
                    style='margin-left:4px'
                    onclick=\"if(confirm('Delete correction for {$ipEsc}?')){var i=document.createElement('input');i.type='hidden';i.name='delete_id';i.value='{$id}';this.closest('form').appendChild(i);this.closest('form').submit();}\"><i class='fa fa-trash'></i></button>
            </td>";
            $out .= "</tr></form>";
        }

        $out .= "</tbody></table>";

        return $out;
    }

    // ── IP Lookup ─────────────────────────────────────────────────────────────

    protected function renderLookup(): string
    {
        $ip  = $this->wire('input')->get->text('ip') ?: '';
        $out = $this->renderNav('lookup');

        $out .= "<p class='description'>Look up geo data for any IP address using the installed GeoLite2 database.</p>";

        $out .= "<form method='get' style='max-width:480px;margin-bottom:24px'>
            <input type='hidden' name='action' value='lookup'>
            <div style='display:flex;gap:8px;align-items:center'>
                <input type='text' name='ip' value='" . htmlspecialchars($ip, ENT_QUOTES) . "'
                    placeholder='e.g. 8.8.8.8'
                    class='uk-input'
                    style='flex:1'>
                <button type='submit' class='ui-button'><i class='fa fa-search'></i> Lookup</button>
            </div>
        </form>";

        if (!$ip) return $out;

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $out .= "<p class='ui-state-error-text'><i class='fa fa-exclamation-triangle'></i> Invalid IP address.</p>";
            return $out;
        }

        /** @var GeoIP $geoip */
        $geoip = $this->wire('modules')->get('GeoIP');
        $data  = $geoip->detect($ip);

        if ($data['status'] === 'fail') {
            $msg = htmlspecialchars($data['message'] ?? 'Lookup failed.');
            $out .= "<div class='uk-alert uk-alert-warning'>
                <i class='fa fa-exclamation-triangle'></i> {$msg}
            </div>";
            return $out;
        }

        $location = array_filter([$data['city'], $data['region'], $data['country']]);
        $locationStr = htmlspecialchars(implode(', ', $location) ?: 'Unknown location');

        $groups = [
            'Location' => [
                'country'     => ['Country',          $data['country']],
                'countryCode' => ['Country Code',     $data['countryCode']],
                'continent'   => ['Continent',        $data['continent']],
                'region'      => ['Region',           $data['region']],
                'regionCode'  => ['Region Code',      $data['regionCode']],
                'city'        => ['City',             $data['city']],
                'zip'         => ['ZIP / Postal Code',$data['zip']],
            ],
            'Coordinates' => [
                'lat'         => ['Latitude',         $data['lat']],
                'lon'         => ['Longitude',        $data['lon']],
                'timezone'    => ['Timezone',         $data['timezone']],
            ],
            'Meta' => [
                'ip'          => ['IP Address',       $data['ip']],
                'corrected'   => ['User Corrected',   $data['corrected'] ? 'yes' : 'no'],
                'status'      => ['Status',           $data['status']],
            ],
        ];

        $out .= "<div style='max-width:560px'>";
        $out .= "<div style='background:#f5f5f5;border:1px solid #ddd;border-radius:4px 4px 0 0;padding:10px 16px;display:flex;align-items:center;gap:10px'>
            <i class='fa fa-map-marker' style='color:#888;font-size:16px'></i>
            <strong style='font-size:15px'>" . htmlspecialchars($ip) . "</strong>
            <span class='description'>&mdash; {$locationStr}</span>
        </div>";

        foreach ($groups as $groupLabel => $rows) {
            $out .= "<div style='border:1px solid #ddd;border-top:none'>";
            $out .= "<div style='background:#fafafa;padding:4px 16px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#888;border-bottom:1px solid #eee'>{$groupLabel}</div>";

            foreach ($rows as [$label, $value]) {
                if (is_bool($value)) $value = $value ? 'yes' : 'no';
                $display = ($value === null || $value === '')
                    ? '<span style="color:#bbb">&mdash;</span>'
                    : '<span>' . htmlspecialchars((string) $value) . '</span>';

                $out .= "<div style='display:flex;border-bottom:1px solid #f0f0f0'>
                    <div style='width:160px;min-width:160px;padding:8px 16px;color:#666;font-size:13px;background:#fafafa;border-right:1px solid #eee'>{$label}</div>
                    <div style='padding:8px 16px;font-size:13px;font-family:monospace'>{$display}</div>
                </div>";
            }

            $out .= "</div>";
        }

        $out .= "</div>";

        return $out;
    }

    // ── Prune ─────────────────────────────────────────────────────────────────

    protected function handlePrune(): string
    {
        /** @var GeoIP $geoip */
        $geoip = $this->wire('modules')->get('GeoIP');
        $days  = (int) ($geoip->get('log_retention_days') ?? 90);

        if ($days > 0) {
            $stmt = $this->wire('database')->prepare(
                "DELETE FROM `" . self::TABLE_LOG . "` WHERE created < NOW() - INTERVAL :days DAY"
            );
            $stmt->execute([':days' => $days]);
            $this->message("Pruned {$stmt->rowCount()} log entries older than {$days} days.");
        } else {
            $this->message("Retention is set to 0 — nothing pruned.");
        }

        $this->wire('session')->redirect('./?action=log');
        return '';
    }

    // ── Nav ───────────────────────────────────────────────────────────────────

    protected function renderNav(string $active): string
    {
        $tabs = [
            'log'         => ['Log',        'list'],
            'corrections' => ['Corrections', 'pencil'],
            'lookup'      => ['IP Lookup',   'search'],
        ];

        $out = "<div style='margin-bottom:20px;display:flex;gap:6px'>";
        foreach ($tabs as $key => [$label, $icon]) {
            $cls = $active === $key ? 'ui-button ui-state-active' : 'ui-button ui-state-default';
            $out .= "<a href='?action={$key}' class='{$cls}'><i class='fa fa-{$icon}'></i> {$label}</a>";
        }
        $out .= "</div>";

        return $out;
    }
}