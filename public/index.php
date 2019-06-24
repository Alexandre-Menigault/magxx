<?php
require_once __DIR__.'/../src/functions.php';
require_once __DIR__.'/../src/Entities/File.php';

route('GET', '^/$', function () {
});

route('GET', '^/users/$', function () {
    echo "/users/";
}, false);
route('GET', '^/upload/file/(?<filename>.+)$', function ($params) {
    header(http_response_code(200));
    header("Content-Type: application/json; charset=UTF-8");
    header("Content-Encoding:gzip");
    // Stream the content of the file to get the data faster
    $fh = fopen('php://output', 'w');
    ob_start();
    $filename = $params["filename"];
    $file = new File($filename);
    fputs($fh, '[');
    foreach($file->read() as $line) {
        fputs($fh, json_encode($line).',');
    }
     // FIXME: find a way not to send an empty JSONObject, because of the trailing comma
     // WORKAROUND: Remove the last object in the array client side
    fputs($fh,'{}]');
    $st = ob_get_clean();
    exit($st);
});

header('HTTP/1.0 404 Not Found');
echo '404 Not Found';