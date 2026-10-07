<?php

trait GeoIPFrontendTrait
{
    protected ?GeoIPCorrectionWidget $correctionWidget = null;
    protected bool $correctionWidgetAssetsRendered = false;

    public function handleCorrectionRequest(HookEvent $event): void
    {
        $input = $this->wire('input');
        $action = (string)$input->get('geoip_action');

        if ($action === 'fragment' && $input->requestMethod('GET')) {
            $this->beginJsonResponse();
            $id = $this->wire('sanitizer')->name((string)$input->get('widget_id')) ?: 'geoip-widget';
            $variant = (string)$input->get('variant') === 'floating' ? 'floating' : 'embedded';
            $payload = json_encode([
                'success' => true,
                'html' => $this->renderCorrectionMarkup($this->detect(), '/?geoip_action=correct', [
                    'id' => $id,
                    'variant' => $variant,
                    'defer_csrf' => true,
                    'csrf_url' => '/?geoip_action=csrf',
                ]),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            // A read-only fragment must not turn an anonymous visitor into a
            // session user and make shared full-page caches bypass later views.
            header_remove('Set-Cookie');
            echo $payload;
            exit;
        }

        if ($action === 'csrf' && $input->requestMethod('GET')) {
            $this->beginJsonResponse();
            $csrf = $this->wire('session')->CSRF;
            echo json_encode([
                'success' => true,
                'name' => $csrf->getTokenName(),
                'value' => $csrf->getTokenValue(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action !== 'correct' || !$input->requestMethod('POST')) {
            return;
        }

        $this->beginJsonResponse();
        if (!$this->wire('session')->CSRF->hasValidToken()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        $post = $input->post;
        $saved = $this->saveCorrection([
            'country' => $post->text('country'),
            'country_code' => $post->text('country_code'),
            'region' => $post->text('region'),
            'region_code' => $post->text('region_code'),
            'city' => $post->text('city'),
        ]);

        echo json_encode(['success' => $saved]);
        exit;
    }

    public function injectCorrectionWidget(HookEvent $event): void
    {
        if ($this->wire('page')->template == 'admin') {
            return;
        }

        $widget = $this->renderDeferredLocationWidget([
            'id' => 'geoip-floating',
            'variant' => 'floating',
        ]);
        $event->return = str_replace('</body>', $widget . '</body>', $event->return);
    }

    public function renderLocationWidget(array $options = []): string
    {
        if (!$this->get('enable_embedded_widget')) {
            return '';
        }

        if (!empty($options['defer'])) {
            $options['variant'] = 'embedded';
            return $this->renderDeferredLocationWidget($options);
        }

        $options['variant'] = 'embedded';
        $endpoint = (string)($options['endpoint'] ?? './?geoip_action=correct');
        unset($options['endpoint']);

        return $this->renderCorrectionWidget($this->detect(), $endpoint, $options);
    }

    protected function beginJsonResponse(): void
    {
        $this->wire('config')->ajax = true;
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
    }

    protected function renderCorrectionWidget(
        array $geo,
        string $endpoint = './?geoip_action=correct',
        array $options = []
    ): string {
        return $this->renderWidgetAssets()
            . $this->renderCorrectionMarkup($geo, $endpoint, $options);
    }

    protected function renderCorrectionMarkup(array $geo, string $endpoint, array $options = []): string
    {
        $this->correctionWidget ??= new GeoIPCorrectionWidget();
        $options['csrf_input'] = !empty($options['defer_csrf'])
            ? ''
            : $this->wire('session')->CSRF->renderInput();

        return $this->correctionWidget->render($geo, $endpoint, $options);
    }

    protected function renderDeferredLocationWidget(array $options): string
    {
        $id = $this->wire('sanitizer')->name((string)($options['id'] ?? 'geoip-widget')) ?: 'geoip-widget';
        $variant = ($options['variant'] ?? '') === 'floating' ? 'floating' : 'embedded';
        $fragmentUrl = '/?geoip_action=fragment&widget_id=' . rawurlencode($id)
            . '&variant=' . rawurlencode($variant);
        $placeholder = '<div class="geoip-widget-fragment" data-geoip-fragment'
            . ' data-geoip-fragment-url="' . htmlspecialchars($fragmentUrl, ENT_QUOTES) . '"'
            . ' aria-live="polite">'
            . '<span class="geoip-widget-fragment__status">Loading location…</span>'
            . '</div>';

        return $this->renderWidgetAssets() . $placeholder;
    }

    protected function renderWidgetAssets(): string
    {
        if ($this->correctionWidgetAssetsRendered) {
            return '';
        }

        $this->correctionWidgetAssetsRendered = true;
        $baseUrl = rtrim((string)$this->wire('config')->urls->siteModules, '/') . '/GeoIP/assets/';
        $version = self::getModuleInfo()['version'];

        return '<link rel="stylesheet" href="' . $baseUrl . 'geoip-widget.css?v=' . $version . '">'
            . '<script src="' . $baseUrl . 'geoip-widget.js?v=' . $version . '" defer></script>';
    }
}
