<?php
$raw = file_get_contents('https://retrasometro.com/api/auth/request-key', false, stream_context_create(['ssl'=>['verify_peer'=>false]]));
$k = json_decode($raw)->apiKey;
$ctx = stream_context_create(['http'=>['header'=>"x-api-key: $k\r\n"],'ssl'=>['verify_peer'=>false]]);
$data = file_get_contents('https://retrasometro.com/api/trains/01460/history?hours=1', false, $ctx);
$d = json_decode($data, true);
if (!empty($d['snapshots'])) {
    print_r($d['snapshots'][0]);
} else {
    echo "No snapshots found";
}
