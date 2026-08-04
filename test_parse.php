<?php
$url = 'https://${RAILWAY_PUBLIC_DOMAIN}';
var_dump(parse_url($url));

$url2 = 'https://';
var_dump(parse_url($url2));
