<?php
$log = file_get_contents(__DIR__.'/storage/logs/laravel.log');
preg_match_all('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\].*?(?=\n\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]|\Z)/s', $log, $matches);
$last = array_slice($matches[0], -2);
$out = [];
foreach($last as $l) { 
    $lines = explode("\n", $l);
    $out[] = array_slice($lines, 0, 15);
}
file_put_contents('error3.json', json_encode($out, JSON_PRETTY_PRINT));
