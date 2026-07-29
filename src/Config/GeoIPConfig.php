<?php

final class GeoIPConfig
{
    public static function defaults(): array
    {
        return [
            'enable_logging' => 1,
            'log_retention_days' => 90,
            'show_correction_widget' => 1,
            'enable_embedded_widget' => 0,
            'session_cache' => 1,
            'http_fallback_enabled' => 0,
            'ipgeolocation_api_key' => '',
            'http_timeout' => 2,
            'fallback_country_code' => 'US',
            'fallback_region_code' => '',
            'fallback_city' => '',
        ];
    }

    public static function buildInputfields(array $data)
    {
        $data = array_merge(self::defaults(), $data);
        $modules = wire('modules');
        $wrapper = new InputfieldWrapper();

        /** @var GeoIP $module */
        $module = $modules->get('GeoIP');
        $dataPath = $module->getDataPath();
        $geoipPath = $module->getGeoIPPath();
        $autoload = $module->getAutoloadPath();

        if (is_file($autoload) && !class_exists('\\GeoIp2\\Database\\Reader')) {
            require_once $autoload;
        }

        $hasComposer = class_exists('\\GeoIp2\\Database\\Reader');
        $hasCity = is_file($geoipPath . 'GeoLite2-City.mmdb');
        $hasCountry = is_file($geoipPath . 'GeoLite2-Country.mmdb');
        $hasDatabase = $hasCity || $hasCountry;
        $httpFallbackReady = !empty($data['http_fallback_enabled'])
            && !empty($data['ipgeolocation_api_key']);
        $notice = '';

        if (!$hasComposer && !$httpFallbackReady) {
            $notice .= "<div class='uk-alert uk-alert-danger' style='margin-bottom:10px'>
                <p><i class='fa fa-exclamation-circle'></i>
                <strong>Composer package <code>geoip2/geoip2</code> not installed.</strong></p>
                <p>Run this command:</p>
                <pre style='background:#fff;border:1px solid #ddd;padding:6px 10px;border-radius:3px;margin:4px 0 8px'>cd {$dataPath} &amp;&amp; composer require geoip2/geoip2</pre>
                <p class='description'>Composer not installed?
                <a href='https://getcomposer.org/download/' target='_blank'>getcomposer.org/download</a></p>
            </div>";
        }

        if (!$hasDatabase && !$httpFallbackReady) {
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

        if ($hasComposer && $hasDatabase) {
            $found = array_filter([
                'GeoLite2-City.mmdb' => $hasCity,
                'GeoLite2-Country.mmdb' => $hasCountry,
            ]);
            $notice .= "<div class='uk-alert uk-alert-success' style='margin-bottom:10px'>
                <i class='fa fa-check-circle'></i>
                Ready. Database: <strong>" . implode(', ', array_keys($found)) . "</strong>
            </div>";
        }

        if ($httpFallbackReady) {
            $notice .= "<div class='uk-alert uk-alert-success' style='margin-bottom:10px'>
                <i class='fa fa-cloud'></i>
                IPGeolocation.io HTTPS fallback is enabled.
            </div>";
        }

        if ($notice !== '') {
            $field = $modules->get('InputfieldMarkup');
            $field->label = 'Setup Status';
            $field->value = $notice;
            $wrapper->add($field);
        }

        $field = $modules->get('InputfieldCheckbox');
        $field->attr('name', 'enable_logging');
        $field->label = 'Enable lookup logging to database';
        $field->value = 1;
        $field->checked = !empty($data['enable_logging']);
        $wrapper->add($field);

        $field = $modules->get('InputfieldInteger');
        $field->attr('name', 'log_retention_days');
        $field->label = 'Log retention (days)';
        $field->description = 'Logs older than this will be pruned on demand. Set 0 to keep forever.';
        $field->value = (int) $data['log_retention_days'];
        $wrapper->add($field);

        $field = $modules->get('InputfieldCheckbox');
        $field->attr('name', 'show_correction_widget');
        $field->label = 'Show "Fix my location" widget on frontend pages';
        $field->value = 1;
        $field->checked = !empty($data['show_correction_widget']);
        $wrapper->add($field);

        $field = $modules->get('InputfieldCheckbox');
        $field->attr('name', 'enable_embedded_widget');
        $field->label = 'Enable template-integrated location widget';
        $field->description = 'Lets templates place the correction control with $geoip->renderLocationWidget(). This does not inject a floating widget.';
        $field->value = 1;
        $field->checked = !empty($data['enable_embedded_widget']);
        $wrapper->add($field);

        $field = $modules->get('InputfieldCheckbox');
        $field->attr('name', 'session_cache');
        $field->label = 'Cache geo result in session (recommended)';
        $field->value = 1;
        $field->checked = !empty($data['session_cache']);
        $wrapper->add($field);

        $httpFieldset = $modules->get('InputfieldFieldset');
        $httpFieldset->label = 'HTTP fallback';
        $httpFieldset->description = 'Used only when local MaxMind lookup is unavailable or fails.';

        $field = $modules->get('InputfieldCheckbox');
        $field->attr('name', 'http_fallback_enabled');
        $field->label = 'Enable IPGeolocation.io fallback';
        $field->description = 'Sends the visitor IP to IPGeolocation.io over HTTPS. Subject to your API plan and privacy policy.';
        $field->value = 1;
        $field->checked = !empty($data['http_fallback_enabled']);
        $httpFieldset->add($field);

        $field = $modules->get('InputfieldText');
        $field->attr('name', 'ipgeolocation_api_key');
        $field->attr('type', 'password');
        $field->label = 'IPGeolocation.io API key';
        $field->value = $data['ipgeolocation_api_key'];
        $field->columnWidth = 75;
        $httpFieldset->add($field);

        $field = $modules->get('InputfieldInteger');
        $field->attr('name', 'http_timeout');
        $field->label = 'Timeout (seconds)';
        $field->description = 'Clamped to 1–10 seconds.';
        $field->value = (int) $data['http_timeout'];
        $field->columnWidth = 25;
        $httpFieldset->add($field);

        $wrapper->add($httpFieldset);

        $staticFieldset = $modules->get('InputfieldFieldset');
        $staticFieldset->label = 'Static fallback values';
        $staticFieldset->description = 'Used only after both MaxMind and the optional HTTP fallback fail.';

        foreach ([
            'fallback_country_code' => ['Country code', 33],
            'fallback_region_code' => ['Region/State code', 33],
            'fallback_city' => ['City', 34],
        ] as $name => [$label, $width]) {
            $field = $modules->get('InputfieldText');
            $field->attr('name', $name);
            $field->label = $label;
            $field->value = $data[$name];
            $field->columnWidth = $width;
            $staticFieldset->add($field);
        }

        $wrapper->add($staticFieldset);
        return $wrapper;
    }
}
