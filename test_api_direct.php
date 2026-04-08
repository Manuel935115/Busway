<?php
$raw = file_get_contents('https://retrasometro.com/api/auth/request-key', false, stream_context_create(['ssl'=>['verify_peer'=>false]]));
$k = json_decode($raw)->apiKey;
$ctx = stream_context_create(['http'=>['header'=>"x-api-key: $k\r\n"],'ssl'=>['verify_peer'=>false]]);
$data = @file_get_contents('https://retrasometro.com/api/trains/01460', false, $ctx);
if ($data) {
    print_r(json_decode($data));
} else {
    echo "No direct endpoint data for /api/trains/code\n";
}
