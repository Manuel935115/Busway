<?php
$raw = file_get_contents('https://retrasometro.com/api/auth/request-key', false, stream_context_create(['ssl'=>['verify_peer'=>false]]));
$k = json_decode($raw)->apiKey;
$ctx = stream_context_create(['http'=>['header'=>"x-api-key: $k\r\n"],'ssl'=>['verify_peer'=>false]]);
$data = file_get_contents('https://retrasometro.com/api/trains?q=01460', false, $ctx);
print_r(json_decode($data));
