# GeoIP

MaxMind GeoLite2-based geolocation module for ProcessWire. Detects country, region and city from the visitor IP, supports user corrections, logs lookups to the database, and exposes geo data to templates for conditional content.

- **GitHub:** [github.com/mxmsmnv/GeoIP](https://github.com/mxmsmnv/GeoIP)

**Author:** Maxim Semenov  
**Website:** [smnv.org](https://smnv.org)  
**Email:** [maxim@smnv.org](mailto:maxim@smnv.org)

If this project helps your work, consider supporting future development: [GitHub Sponsors](https://github.com/sponsors/mxmsmnv) or [smnv.org/sponsor](https://smnv.org/sponsor/).

- **License:** MIT

## Requirements

- ProcessWire 3.0.200+
- PHP 8.2+
- Composer
- MaxMind GeoLite2 database files (free account required)

## Installation

### 1. Install modules

Copy the `GeoIP/` folder to `/site/modules/` and install from **Modules → Refresh**:

1. Install **GeoIP** first — creates DB tables and `site/assets/GeoIP/` directory
2. Install **ProcessGeoIP** — creates **Setup → GeoIP** admin page

### 2. Install Composer package

The exact path with your server's full path is shown in **Modules → GeoIP → Setup Status**. General form:

```bash
cd /path/to/site/assets/GeoIP/ && composer require geoip2/geoip2
```

If Composer is not installed: [getcomposer.org/download](https://getcomposer.org/download/)

### 3. Download GeoLite2 databases

Create a free account at [maxmind.com](https://www.maxmind.com/en/geolite2/signup) and download:

- `GeoLite2-City.mmdb` — country + region + city + coordinates (recommended)
- `GeoLite2-Country.mmdb` — country only, smaller file

Upload to `/site/assets/GeoIP/` (same directory as `vendor/`).

### Directory structure after setup

```
site/assets/GeoIP/
├── GeoLite2-City.mmdb
├── GeoLite2-Country.mmdb
├── composer.json
└── vendor/
    └── autoload.php
```

## Configuration

Go to **Modules → GeoIP**:

| Setting | Description |
|---|---|
| Enable lookup logging | Write each unique IP to `geoip_log` table (one entry per session) |
| Log retention (days) | Auto-prune logs older than N days on demand. 0 = keep forever |
| Show correction widget | Inject "Fix my location" widget on all frontend pages |
| Cache in session | Avoid repeated DB lookups per session — strongly recommended |
| Fallback country/region/city | Used when detection fails (private IP, missing DB, local dev) |

## How it works

On every frontend request the module:

1. Checks in-memory cache — if already detected this request, returns immediately
2. Checks session cache — if already detected this session, returns from session
3. Resolves the real client IP (handles Cloudflare, proxies, load balancers)
4. Looks up the IP in MaxMind GeoLite2 database
5. Checks for a saved user correction for this IP — applies it if found
6. Saves result to session cache
7. Logs the lookup to `geoip_log` (one entry per unique IP per session)
8. Returns the geo data array

The `$geoip` variable is registered as a wire variable — available in all templates automatically, just like `$page`, `$user`, `$config`.

---

## Basic usage

```php
// Single condition — simplest possible usage
if ($geoip->inCountry('US')) {
    echo "Hello USA";
}

// With else
if ($geoip->inCountry('US')) {
    echo $page->us_content;
} else {
    echo $page->global_content;
}

// Inline with showIf()
echo $geoip->showIf('countryCode', 'US', $page->us_block, $page->global_block);
```

---

## Full geo data array

```php
$geo = $geoip->detect();

// $geo contains:
// [
//   'ip'          => '63.214.17.178',
//   'country'     => 'United States',
//   'countryCode' => 'US',
//   'continent'   => 'North America',
//   'region'      => 'Maryland',
//   'regionCode'  => 'MD',
//   'city'        => 'Salisbury',
//   'zip'         => '21801',
//   'lat'         => 38.3607,
//   'lon'         => -75.5994,
//   'timezone'    => 'America/New_York',
//   'corrected'   => false,   // true if user manually set their location
//   'status'      => 'success',
// ]

echo $geo['country'];     // United States
echo $geo['countryCode']; // US
echo $geo['region'];      // Maryland
echo $geo['regionCode'];  // MD
echo $geo['city'];        // Salisbury
echo $geo['zip'];         // 21801
echo $geo['lat'];         // 38.3607
echo $geo['lon'];         // -75.5994
echo $geo['timezone'];    // America/New_York
```

## Get a single field

```php
// When you only need one value — no need to call detect() manually
$country     = $geoip->getField('country');      // "United States"
$countryCode = $geoip->getField('countryCode');  // "US"
$continent   = $geoip->getField('continent');    // "North America"
$region      = $geoip->getField('region');       // "Maryland"
$regionCode  = $geoip->getField('regionCode');   // "MD"
$city        = $geoip->getField('city');         // "Salisbury"
$zip         = $geoip->getField('zip');          // "21801"
$lat         = $geoip->getField('lat');          // 38.3607
$lon         = $geoip->getField('lon');          // -75.5994
$timezone    = $geoip->getField('timezone');     // "America/New_York"
```

---

## Conditional helpers

### inCountry()

Accepts a single country code (ISO 3166-1 alpha-2) or an array. Case-insensitive.

```php
// ── Single country ────────────────────────────────────────────────────────────

if ($geoip->inCountry('US')) {
    echo $page->us_promo;
}

if ($geoip->inCountry('DE')) {
    echo $page->germany_block;
}

// ── Multiple countries ────────────────────────────────────────────────────────

if ($geoip->inCountry(['US', 'CA'])) {
    echo "North America pricing applies.";
}

if ($geoip->inCountry(['GB', 'IE', 'AU', 'NZ', 'CA'])) {
    echo "English-speaking market content.";
}

// ── Negation ─────────────────────────────────────────────────────────────────

if (!$geoip->inCountry('US')) {
    echo "This product is not available in your country.";
}

// ── EU GDPR notice ────────────────────────────────────────────────────────────

$euCountries = [
    'AT','BE','BG','CY','CZ','DE','DK','EE','ES','FI',
    'FR','GR','HR','HU','IE','IT','LT','LU','LV','MT',
    'NL','PL','PT','RO','SE','SI','SK'
];

if ($geoip->inCountry($euCountries)) {
    echo $page->gdpr_cookie_banner;
}

// ── Blocked countries ─────────────────────────────────────────────────────────

$blockedCountries = ['KP', 'IR', 'SY', 'CU'];

if ($geoip->inCountry($blockedCountries)) {
    echo "Service not available in your region.";
    return;
}
```

### inRegion()

Matches ISO 3166-2 subdivision code (state, province, canton, etc.). Case-insensitive.

```php
// ── US states ─────────────────────────────────────────────────────────────────

if ($geoip->inRegion('PA')) {
    echo "Pennsylvania visitors get free shipping!";
}

if ($geoip->inRegion('CA')) {
    echo $page->california_prop65_warning;
}

// ── Multi-state regions ───────────────────────────────────────────────────────

// Tri-state area
if ($geoip->inCountry('US') && $geoip->inRegion(['NY', 'NJ', 'CT'])) {
    echo "Same-day delivery available in the Tri-State area.";
}

// US West Coast
if ($geoip->inCountry('US') && $geoip->inRegion(['CA', 'OR', 'WA'])) {
    echo $page->west_coast_offer;
}

// US Southeast
if ($geoip->inCountry('US') && $geoip->inRegion(['FL', 'GA', 'AL', 'MS', 'LA', 'SC', 'NC', 'TN', 'AR'])) {
    echo $page->southeast_block;
}

// ── Canadian provinces ────────────────────────────────────────────────────────

if ($geoip->inCountry('CA') && $geoip->inRegion(['ON', 'QC'])) {
    echo "Bilingual support available for Ontario and Quebec customers.";
}

if ($geoip->inCountry('CA') && $geoip->inRegion(['BC', 'AB', 'SK', 'MB'])) {
    echo "Western Canada shipping rates apply.";
}

// ── German states ─────────────────────────────────────────────────────────────

if ($geoip->inCountry('DE') && $geoip->inRegion('BY')) {
    echo "Servus! Bayern-spezifische Informationen.";
}

// ── NOT in region ─────────────────────────────────────────────────────────────

if ($geoip->inCountry('US') && !$geoip->inRegion('CA')) {
    echo "Available in all US states except California.";
}
```

### inCity()

Matches city name. Case-insensitive. Use the English city name as returned by MaxMind.

```php
// ── Single city ───────────────────────────────────────────────────────────────

if ($geoip->inCity('Philadelphia')) {
    echo "Come visit our Philadelphia showroom at 123 Main St.";
}

if ($geoip->inCity('London')) {
    echo $page->london_office_hours;
}

// ── Multiple cities ───────────────────────────────────────────────────────────

if ($geoip->inCity(['New York', 'Brooklyn', 'Queens', 'Bronx', 'Staten Island'])) {
    echo "NYC same-day delivery available!";
}

if ($geoip->inCity(['Paris', 'Lyon', 'Marseille', 'Toulouse', 'Nice', 'Nantes'])) {
    echo "Livraison express disponible dans votre ville.";
}

// ── City + country combo ──────────────────────────────────────────────────────

if ($geoip->inCountry('AU') && $geoip->inCity('Sydney')) {
    echo $page->sydney_event_banner;
}

if ($geoip->inCountry('DE') && $geoip->inCity(['Berlin', 'Munich', 'Hamburg'])) {
    echo "Kostenlose Lieferung in Großstädte!";
}
```

---

## showIf() — inline conditional rendering

Renders `$content` when a geo field matches, optional `$else` fallback.
Works with any field, any page field value, or any string.

```php
// showIf(string $field, string|array $values, string $content, string $else = '')

// ── By country code ───────────────────────────────────────────────────────────

echo $geoip->showIf('countryCode', 'US', '<p>Free shipping on orders over $50</p>');

echo $geoip->showIf('countryCode', 'US', $page->us_block, $page->global_block);

echo $geoip->showIf('countryCode', ['DE', 'AT', 'CH'],
    '<p>Kostenloser Versand ab 50€</p>',
    '<p>International shipping rates apply.</p>'
);

// ── By region code ────────────────────────────────────────────────────────────

echo $geoip->showIf('regionCode', 'CA',
    '<div class="warning">' . $page->california_prop65 . '</div>'
);

echo $geoip->showIf('regionCode', ['PA', 'NJ', 'NY', 'CT'],
    $page->northeast_banner,
    $page->national_banner
);

// ── By city ───────────────────────────────────────────────────────────────────

echo $geoip->showIf('city', 'London',
    '<p>Visit our London office: 10 Downing Street</p>'
);

echo $geoip->showIf('city', ['Paris', 'Lyon', 'Marseille'],
    $page->france_city_block,
    $page->france_general_block
);

// ── By continent ──────────────────────────────────────────────────────────────

echo $geoip->showIf('continent', 'Europe', $page->eu_shipping_info);

echo $geoip->showIf('continent', ['Europe', 'Asia'],
    $page->eastern_hemisphere_block,
    $page->western_hemisphere_block
);

// ── By full country name ──────────────────────────────────────────────────────

echo $geoip->showIf('country', 'Germany', $page->germany_block);

// ── By timezone ───────────────────────────────────────────────────────────────

echo $geoip->showIf('timezone', 'America/New_York',
    '<p>Eastern time zone hours: Mon-Fri 9am-5pm ET</p>'
);

// ── With ProcessWire page fields ──────────────────────────────────────────────

// Render a repeater item based on country
foreach ($page->geo_blocks as $block) {
    echo $geoip->showIf('countryCode', $block->countries, $block->render());
}
```

---

## Combining conditions

```php
$geo = $geoip->detect();

// ── Country + region ──────────────────────────────────────────────────────────

if ($geoip->inCountry('US') && $geoip->inRegion('TX')) {
    echo "Everything is bigger in Texas — including our discounts!";
}

if ($geoip->inCountry('US') && $geoip->inRegion('CA')) {
    // California-specific legal notice required
    echo $page->california_legal_notice;
}

// ── Country + city ────────────────────────────────────────────────────────────

if ($geoip->inCountry('GB') && $geoip->inCity('London')) {
    echo "Pop into our London flagship store on Oxford Street.";
}

// ── Country + NOT region ──────────────────────────────────────────────────────

if ($geoip->inCountry('US') && !$geoip->inRegion(['AK', 'HI'])) {
    echo "Free continental US shipping!";
}

// ── Multiple countries + cities ───────────────────────────────────────────────

if ($geoip->inCountry(['US', 'CA']) && $geoip->inCity(['Seattle', 'Vancouver'])) {
    echo "Pacific Northwest special offer!";
}

// ── Logged-in user + country ──────────────────────────────────────────────────

if ($user->isLoggedIn() && $geoip->inCountry('US')) {
    echo $page->us_member_dashboard;
}

// Guest + city
if (!$user->isLoggedIn() && $geoip->inCity('San Francisco')) {
    echo $page->sf_signup_promo;
}

// ── Time of day in visitor's local timezone ───────────────────────────────────

$tz   = $geoip->getField('timezone') ?: 'UTC';
$now  = new DateTime('now', new DateTimeZone($tz));
$hour = (int) $now->format('H');
$day  = (int) $now->format('N'); // 1=Mon, 7=Sun

if ($geoip->inCountry('US') && $hour >= 9 && $hour < 17 && $day <= 5) {
    echo '<p>Our US support team is online right now. <a href="/chat">Start a chat</a></p>';
} else {
    echo '<p>Leave us a message and we will respond within 1 business day.</p>';
}

// ── Different currency by country ─────────────────────────────────────────────

$price = 99.00;

if ($geoip->inCountry(['GB', 'IE'])) {
    echo "£" . number_format($price * 0.79, 2);
} elseif ($geoip->inCountry(['DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'AT'])) {
    echo "€" . number_format($price * 0.92, 2);
} elseif ($geoip->inCountry('CA')) {
    echo "CA$" . number_format($price * 1.35, 2);
} elseif ($geoip->inCountry('AU')) {
    echo "AU$" . number_format($price * 1.52, 2);
} else {
    echo "$" . number_format($price, 2);
}

// ── Language redirect ─────────────────────────────────────────────────────────

if ($geoip->inCountry(['DE', 'AT', 'CH']) && $page->name !== 'de') {
    $session->redirect('/de/');
}

if ($geoip->inCountry(['FR', 'BE', 'CH', 'LU']) && $page->name !== 'fr') {
    $session->redirect('/fr/');
}

// ── Geo + template ────────────────────────────────────────────────────────────

if ($page->template == 'product' && $geoip->inCountry('US')) {
    echo $page->us_price_block;
} elseif ($page->template == 'product') {
    echo $page->intl_price_block;
}
```

---

## E-commerce: shipping and pricing

```php
$geo = $geoip->detect();
$cc  = $geo['countryCode'] ?: 'US';
$rc  = $geo['regionCode']  ?: '';

// ── Shipping rates ────────────────────────────────────────────────────────────

if ($geoip->inCountry('US')) {
    if ($geoip->inRegion(['AK', 'HI', 'PR', 'GU', 'VI'])) {
        $shipping = "Extended US territory — $25 flat rate";
    } else {
        $shipping = "Free shipping on orders over $50";
    }
} elseif ($geoip->inCountry(['CA', 'MX'])) {
    $shipping = "North America — $15 flat rate";
} elseif ($geoip->inCountry($euCountries)) {
    $shipping = "Europe — €12 flat rate, free over €100";
} else {
    $shipping = "International — calculated at checkout";
}

echo "<p>{$shipping}</p>";

// ── Pre-select country dropdown ───────────────────────────────────────────────

$countries = ['US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom',
              'DE' => 'Germany', 'FR' => 'France', 'AU' => 'Australia'];

echo "<select name='ship_country'>";
foreach ($countries as $code => $name) {
    $selected = ($code === $cc) ? ' selected' : '';
    echo "<option value='{$code}'{$selected}>{$name}</option>";
}
echo "</select>";

// ── Tax calculation ───────────────────────────────────────────────────────────

$price    = 100.00;
$taxRate  = 0;
$taxLabel = '';

if ($geoip->inCountry('US')) {
    // US sales tax by state
    $stateTax = [
        'CA' => 0.0725, 'NY' => 0.08, 'TX' => 0.0625,
        'FL' => 0.06,   'WA' => 0.065, 'PA' => 0.06,
    ];
    $taxRate  = $stateTax[$rc] ?? 0;
    $taxLabel = $rc ? "Sales tax ({$rc})" : '';
} elseif ($geoip->inCountry($euCountries)) {
    // EU VAT
    $vatRates = [
        'DE' => 0.19, 'FR' => 0.20, 'IT' => 0.22,
        'ES' => 0.21, 'NL' => 0.21, 'BE' => 0.21,
        'AT' => 0.20, 'PL' => 0.23, 'SE' => 0.25,
    ];
    $taxRate  = $vatRates[$cc] ?? 0.20;
    $taxLabel = "VAT ({$cc})";
} elseif ($geoip->inCountry('GB')) {
    $taxRate  = 0.20;
    $taxLabel = "VAT (UK)";
}

$tax   = $price * $taxRate;
$total = $price + $tax;

echo "<p>Subtotal: $" . number_format($price, 2) . "</p>";
if ($taxRate > 0) {
    echo "<p>{$taxLabel}: $" . number_format($tax, 2) . "</p>";
}
echo "<p>Total: $" . number_format($total, 2) . "</p>";
```

---

## Page selectors and dynamic content

```php
$cc   = $geoip->getField('countryCode');
$rc   = $geoip->getField('regionCode');
$city = $geoip->getField('city');

// ── Find pages for visitor's country ─────────────────────────────────────────

// Promos tagged with a country code field
$promos = $pages->find("template=promo, geo_countries=$cc, limit=5, sort=-created");
foreach ($promos as $promo) {
    echo $promo->render();
}

// Events in visitor's region
$events = $pages->find("template=event, region=$rc, date>=today, sort=date, limit=10");

// Distributors near visitor's city
$distributors = $pages->find("template=distributor, city=$city, sort=title");
if (!$distributors->count()) {
    // Fallback: country-level distributors
    $distributors = $pages->find("template=distributor, country=$cc, sort=title");
}

// ── Show different hero banner by country ─────────────────────────────────────

$hero = $pages->get("template=hero-banner, country_code=$cc");
if (!$hero->id) {
    // Fallback to continent
    $continent = $geoip->getField('continent');
    $hero = $pages->get("template=hero-banner, continent=$continent");
}
if (!$hero->id) {
    $hero = $pages->get("template=hero-banner, name=global");
}
echo $hero->render();

// ── Store locator ─────────────────────────────────────────────────────────────

$stores = $pages->find("template=store, country=$cc, sort=title");
if ($stores->count()) {
    echo "<h3>Stores in {$geo['country']}</h3>";
    foreach ($stores as $store) {
        echo "<div class='store'>";
        echo "<h4>{$store->title}</h4>";
        echo "<p>{$store->address}</p>";
        echo "</div>";
    }
} else {
    echo "<p>No stores in your country yet. <a href='/online-store'>Shop online</a></p>";
}
```

---

## User location correction

When **Show correction widget** is enabled, a small fixed widget appears on every frontend page. Visitors can click "Incorrect? Fix it", edit country/region/city, and save. The correction is stored per-IP in `geoip_corrections` and applied automatically on all subsequent requests.

### How it works

1. Visitor lands on the site — geo detected from IP automatically
2. Widget shows detected location in bottom-right corner
3. Visitor clicks "Incorrect? Fix it" and edits the fields
4. On save: correction stored in DB, session cleared, page reloads
5. All future requests from that IP use the corrected values

### Detecting user corrections in templates

```php
$geo = $geoip->detect();

if ($geo['corrected']) {
    // User manually set their location — respect it
    $city    = $geo['city'];
    $country = $geo['country'];
    echo "<p>Showing results for: {$city}, {$country} <a href='/?geoip_reset=1'>Change</a></p>";
} else {
    // Auto-detected
    echo "<p>Detected location: {$geo['city']}, {$geo['country']} — <a href='#' id='fix-location'>Not you?</a></p>";
}
```

### Custom correction UI — HTML form

Build your own location selector instead of using the built-in widget:

```php
<?php
$geo = $geoip->detect();
$cc  = $geo['countryCode'] ?: 'US';
$rc  = $geo['regionCode']  ?: '';

$countries = [
    'US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom',
    'DE' => 'Germany',  'FR' => 'France',  'IT' => 'Italy',
    'ES' => 'Spain',    'NL' => 'Netherlands', 'AU' => 'Australia',
    'JP' => 'Japan',    'KR' => 'South Korea', 'BR' => 'Brazil',
    'MX' => 'Mexico',   'IN' => 'India',   'CN' => 'China',
    'RU' => 'Russia',   'ZA' => 'South Africa',
];
?>
<form method="post" action="./?geoip_action=correct" class="location-form">
    <input type="hidden" name="country" value="">
    <input type="hidden" name="region"  value="">

    <label>Country</label>
    <select name="country_code" onchange="this.form.submit()">
        <?php foreach ($countries as $code => $name): ?>
            <option value="<?= $code ?>" <?= $code === $cc ? 'selected' : '' ?>>
                <?= $name ?>
            </option>
        <?php endforeach ?>
    </select>

    <?php if ($cc === 'US'): ?>
    <label>State</label>
    <select name="region_code" onchange="this.form.submit()">
        <?php
        $usStates = [
            'AL'=>'Alabama',    'AK'=>'Alaska',       'AZ'=>'Arizona',
            'AR'=>'Arkansas',   'CA'=>'California',   'CO'=>'Colorado',
            'CT'=>'Connecticut','DE'=>'Delaware',     'FL'=>'Florida',
            'GA'=>'Georgia',    'HI'=>'Hawaii',       'ID'=>'Idaho',
            'IL'=>'Illinois',   'IN'=>'Indiana',      'IA'=>'Iowa',
            'KS'=>'Kansas',     'KY'=>'Kentucky',     'LA'=>'Louisiana',
            'ME'=>'Maine',      'MD'=>'Maryland',     'MA'=>'Massachusetts',
            'MI'=>'Michigan',   'MN'=>'Minnesota',    'MS'=>'Mississippi',
            'MO'=>'Missouri',   'MT'=>'Montana',      'NE'=>'Nebraska',
            'NV'=>'Nevada',     'NH'=>'New Hampshire','NJ'=>'New Jersey',
            'NM'=>'New Mexico', 'NY'=>'New York',     'NC'=>'North Carolina',
            'ND'=>'North Dakota','OH'=>'Ohio',        'OK'=>'Oklahoma',
            'OR'=>'Oregon',     'PA'=>'Pennsylvania', 'RI'=>'Rhode Island',
            'SC'=>'South Carolina','SD'=>'South Dakota','TN'=>'Tennessee',
            'TX'=>'Texas',      'UT'=>'Utah',         'VT'=>'Vermont',
            'VA'=>'Virginia',   'WA'=>'Washington',   'WV'=>'West Virginia',
            'WI'=>'Wisconsin',  'WY'=>'Wyoming',
            'DC'=>'District of Columbia',
        ];
        foreach ($usStates as $code => $name):
        ?>
            <option value="<?= $code ?>" <?= $code === $rc ? 'selected' : '' ?>>
                <?= $name ?>
            </option>
        <?php endforeach ?>
    </select>
    <?php endif ?>

    <button type="submit">Save location</button>
</form>
```

### Custom correction UI — JavaScript fetch

No page reload — update silently and re-render content via AJAX:

```javascript
async function saveLocation(countryCode, regionCode, city, country = '', region = '') {
    const fd = new FormData();
    fd.append('country_code', countryCode);
    fd.append('region_code',  regionCode);
    fd.append('city',         city);
    fd.append('country',      country);
    fd.append('region',       region);

    const res  = await fetch('./?geoip_action=correct', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
        // Option A: reload to show corrected content
        location.reload();

        // Option B: re-fetch just the geo-dependent block
        // const block = await fetch('/partials/shipping-info/?cc=' + countryCode);
        // document.getElementById('shipping-info').innerHTML = await block.text();
    }
}

// Usage examples
saveLocation('US', 'PA', 'Philadelphia', 'United States', 'Pennsylvania');
saveLocation('DE', 'BY', 'Munich',       'Germany',       'Bavaria');
saveLocation('GB', 'ENG', 'London',      'United Kingdom', 'England');
saveLocation('JP', '13',  'Tokyo',       'Japan',         'Tokyo');

// From a select element
document.getElementById('country-select').addEventListener('change', function() {
    saveLocation(this.value, '', '', this.options[this.selectedIndex].text);
});
```

### Vivino-style ship-to dropdown

Pre-populated from visitor's detected location with full US state list:

```php
<?php
$geo = $geoip->detect();
$cc  = $geo['countryCode'] ?: 'US';
$rc  = $geo['regionCode']  ?: '';

$countries = [
    'US' => 'United States', 'CA' => 'Canada',
    'GB' => 'United Kingdom', 'AU' => 'Australia',
    'DE' => 'Germany', 'FR' => 'France',
];

$usStates = [
    'AL'=>'Alabama',    'AK'=>'Alaska',    'AZ'=>'Arizona',    'AR'=>'Arkansas',
    'CA'=>'California', 'CO'=>'Colorado',  'CT'=>'Connecticut','DE'=>'Delaware',
    'FL'=>'Florida',    'GA'=>'Georgia',   'HI'=>'Hawaii',     'ID'=>'Idaho',
    'IL'=>'Illinois',   'IN'=>'Indiana',   'IA'=>'Iowa',       'KS'=>'Kansas',
    'KY'=>'Kentucky',   'LA'=>'Louisiana', 'ME'=>'Maine',      'MD'=>'Maryland',
    'MA'=>'Massachusetts','MI'=>'Michigan','MN'=>'Minnesota',  'MS'=>'Mississippi',
    'MO'=>'Missouri',   'MT'=>'Montana',   'NE'=>'Nebraska',   'NV'=>'Nevada',
    'NH'=>'New Hampshire','NJ'=>'New Jersey','NM'=>'New Mexico','NY'=>'New York',
    'NC'=>'North Carolina','ND'=>'North Dakota','OH'=>'Ohio',  'OK'=>'Oklahoma',
    'OR'=>'Oregon',     'PA'=>'Pennsylvania','RI'=>'Rhode Island','SC'=>'South Carolina',
    'SD'=>'South Dakota','TN'=>'Tennessee', 'TX'=>'Texas',     'UT'=>'Utah',
    'VT'=>'Vermont',    'VA'=>'Virginia',  'WA'=>'Washington', 'WV'=>'West Virginia',
    'WI'=>'Wisconsin',  'WY'=>'Wyoming',   'DC'=>'District of Columbia',
];
?>
<div class="ship-to-bar">
    <form method="post" action="./?geoip_action=correct" id="ship-to-form">
        <input type="hidden" name="country" value="">
        <input type="hidden" name="region"  value="">
        <input type="hidden" name="city"    value="">

        <span>Ship to</span>

        <select name="country_code" onchange="document.getElementById('ship-to-form').submit()">
            <?php foreach ($countries as $code => $name): ?>
                <option value="<?= $code ?>" <?= $code === $cc ? 'selected' : '' ?>>
                    <?= $name ?>
                </option>
            <?php endforeach ?>
        </select>

        <?php if ($cc === 'US'): ?>
        <select name="region_code" onchange="document.getElementById('ship-to-form').submit()">
            <?php foreach ($usStates as $code => $name): ?>
                <option value="<?= $code ?>" <?= $code === $rc ? 'selected' : '' ?>>
                    <?= $name ?>
                </option>
            <?php endforeach ?>
        </select>
        <?php endif ?>
    </form>

    <span class="detected-city"><?= htmlspecialchars($geo['city'] ?: $geo['country']) ?></span>
</div>
```

### Managing corrections in admin

Go to **Setup → GeoIP → Corrections** to:

- View all saved corrections with IP, country, region, city and timestamp
- Edit any correction inline — change country code, region code, city — and save
- Delete individual corrections to reset a visitor to auto-detection

One correction per IP address. New correction from the same IP overwrites the previous one.

---

## Admin panel — Setup → GeoIP

| Tab | Description |
|---|---|
| Log | Paginated lookup history with stat cards. Click any IP to open IP Lookup for it |
| Corrections | All user-submitted corrections. Inline edit and delete |
| IP Lookup | Look up any IP — grouped result card with Location / Coordinates / Meta |

Stat cards show: total lookups, today's lookups, number of user corrections, top 5 countries.

---

## Database tables

| Table | Purpose |
|---|---|
| `geoip_log` | Lookup log — one entry per unique IP per session |
| `geoip_corrections` | User corrections — one per IP, upserted on save |

Tables are preserved on module uninstall to retain historical data.

---

## Keeping databases up to date

MaxMind updates GeoLite2 databases on the first Tuesday of each month. To update, replace the `.mmdb` files in `/site/assets/GeoIP/` — no module reinstall required. The module loads the database file on each request, so the update takes effect immediately.

---

## CHANGELOG

See [CHANGELOG.md](CHANGELOG.md).