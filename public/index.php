<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/Entities/File.php';

route('GET', '^/$', function () { });

route("GET", '^/api/data/(?<obs>.+)/(?<date>.+)/(?<type>.+)$', function ($params) {
    $file = new File($params["obs"], $params["type"], $params["date"]);

    @ini_set('zlib.output_compression', 0);
    header(http_response_code(200));
    header("Content-Type: application/json");

    $fh = fopen('php://output', 'w');
    $i = 0;
    fputs($fh, '[');
    foreach ($file->read() as $line) {
        if ($i == 0) {
            $line = array("header" => explode(",", $line), "type" => $file->type, "date" => $file->date);
            if($file->type == $file::TYPE_RAW ) $line["colors"] = ["#080", "#008b8b", "#ff8c00", "#9400d3", "#000"];
            $i++;
        }
        fputs($fh, json_encode($line) . ',');
    }
    // FIXME: find a way not to send an empty JSONObject, because of the trailing comma
    // WORKAROUND: Remove the last object in the array client side
    fputs($fh, '{}]');
    $st = ob_get_clean();
    exit($st);
});

header('HTTP/1.0 404 Not Found');
echo '404 Not Found';
