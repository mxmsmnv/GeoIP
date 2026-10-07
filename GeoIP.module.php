<?php
if (!defined('PROCESSWIRE')) die();

require_once __DIR__ . '/src/Lookup/GeoIPResult.php';
require_once __DIR__ . '/src/Lookup/GeoIPMaxMindProvider.php';
require_once __DIR__ . '/src/Lookup/GeoIPGeolocationProvider.php';
require_once __DIR__ . '/src/Lookup/GeoIPLookupService.php';
require_once __DIR__ . '/src/Storage/GeoIPStore.php';
require_once __DIR__ . '/src/Frontend/GeoIPCorrectionWidget.php';
require_once __DIR__ . '/src/Config/GeoIPConfig.php';
require_once __DIR__ . '/src/Traits/GeoIPDetectionTrait.php';
require_once __DIR__ . '/src/Traits/GeoIPFrontendTrait.php';
require_once __DIR__ . '/src/Traits/GeoIPPathsTrait.php';

/**
 * GeoIP — MaxMind GeoLite2 geolocation module for ProcessWire.
 *
 * Autoloading only registers the public wire variable and request hooks.
 * Providers, databases and visitor detection remain lazy until an API helper,
 * widget fragment or manual lookup actually requests location data.
 *
 * @author Maxim Semenov <maxim@smnv.org> (smnv.org)
 * @license MIT
 */
class GeoIP extends WireData implements Module, ConfigurableModule
{
    use GeoIPDetectionTrait;
    use GeoIPFrontendTrait;
    use GeoIPPathsTrait;

    public const TABLE_LOG = 'geoip_log';
    public const TABLE_CORRECTIONS = 'geoip_corrections';
    public const SESSION_KEY = 'geoip_data';

    public static function getModuleInfo(): array
    {
        return [
            'title' => 'GeoIP',
            'version' => 133,
            'summary' => 'IP geolocation with local MaxMind lookup, optional IPGeolocation.io fallback, user corrections, and conditional content helpers.',
            'author' => 'Maxim Semenov',
            'href' => 'https://smnv.org',
            'singular' => true,
            'autoload' => true,
            'icon' => 'globe',
            'requires' => ['ProcessWire>=3.0.200', 'PHP>=8.2'],
        ];
    }

    public function ___install(): void
    {
        $this->getStore()->createTables();
        $this->createAssetsDir();
    }

    public function ___uninstall(): void
    {
        // Preserve lookup history and visitor corrections on uninstall.
    }

    public function init(): void
    {
        $this->wire->set('geoip', $this);
        $this->addHookBefore('ProcessPageView::execute', $this, 'handleCorrectionRequest');

        if ($this->get('show_correction_widget')) {
            $this->addHookAfter('Page::render', $this, 'injectCorrectionWidget');
        }
    }

    public static function getDefaultConfig(): array
    {
        return GeoIPConfig::defaults();
    }

    public static function getModuleConfigInputfields(array $data)
    {
        return GeoIPConfig::buildInputfields($data);
    }
}
