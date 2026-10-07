<?php

trait GeoIPPathsTrait
{
    public function getDataPath(): string
    {
        return $this->wire('config')->paths->assets . 'GeoIP/';
    }

    public function getDataUrl(): string
    {
        return $this->wire('config')->urls->assets . 'GeoIP/';
    }

    public function getGeoIPPath(): string
    {
        return $this->getDataPath();
    }

    public function getGeoIPUrl(): string
    {
        return $this->getDataUrl();
    }

    public function getVendorPath(): string
    {
        return $this->getDataPath() . 'vendor/';
    }

    public function getAutoloadPath(): string
    {
        return $this->getVendorPath() . 'autoload.php';
    }

    protected function createAssetsDir(): void
    {
        $dataPath = $this->getDataPath();
        if (!is_dir($dataPath)) {
            wireMkdir($dataPath, true);
        }

        $composerJson = $dataPath . 'composer.json';
        if (!file_exists($composerJson)) {
            file_put_contents($composerJson, json_encode([
                'require' => ['geoip2/geoip2' => '^3.0'],
                'config' => ['vendor-dir' => 'vendor'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }
}
