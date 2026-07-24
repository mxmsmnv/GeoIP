<?php

final class GeoIPCorrectionWidget
{
    public function render(array $geo, string $endpoint = './?geoip_action=correct'): string
    {
        $country = htmlspecialchars($geo['country'] ?? '', ENT_QUOTES);
        $countryCode = htmlspecialchars($geo['countryCode'] ?? '', ENT_QUOTES);
        $region = htmlspecialchars($geo['region'] ?? '', ENT_QUOTES);
        $regionCode = htmlspecialchars($geo['regionCode'] ?? '', ENT_QUOTES);
        $city = htmlspecialchars($geo['city'] ?? '', ENT_QUOTES);
        $endpoint = htmlspecialchars($endpoint, ENT_QUOTES);

        return <<<HTML
<div id="geoip-widget" style="position:fixed;bottom:16px;right:16px;z-index:9999;font-family:system-ui,sans-serif;font-size:13px;background:#fff;border:1px solid #ddd;border-radius:8px;padding:12px 16px;box-shadow:0 4px 16px rgba(0,0,0,.12);max-width:280px">
  <div style="font-weight:600;margin-bottom:6px">&#128205; Your location</div>
  <div style="color:#555;margin-bottom:8px">{$country}, {$region}, {$city}</div>
  <div id="geoip-form" style="display:none">
    <input type="hidden" id="geoip-cc" value="{$countryCode}">
    <input type="hidden" id="geoip-rc" value="{$regionCode}">
    <div style="margin-bottom:4px"><input type="text" id="geoip-c" placeholder="Country" value="{$country}" style="width:100%;box-sizing:border-box;padding:4px 6px;border:1px solid #ccc;border-radius:4px"></div>
    <div style="margin-bottom:4px"><input type="text" id="geoip-r" placeholder="Region/State" value="{$region}" style="width:100%;box-sizing:border-box;padding:4px 6px;border:1px solid #ccc;border-radius:4px"></div>
    <div style="margin-bottom:8px"><input type="text" id="geoip-ci" placeholder="City" value="{$city}" style="width:100%;box-sizing:border-box;padding:4px 6px;border:1px solid #ccc;border-radius:4px"></div>
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
}
