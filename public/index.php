<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/Entities/File.php';
require_once __DIR__ . '/../src/Upload.php';
require_once __DIR__ . '/../src/Entities/Observatory.php';
require_once __DIR__ . '/../src/Entities/User.php';
require_once __DIR__ . '/../src/Entities/Measure.php';
require_once __DIR__ . '/../src/exceptions/FileNotFoundException.php';
require_once __DIR__ . '/../src/exceptions/CannotWriteOnFileException.php';

route('GET', '^/$', function () { });

route("GET", '^/api/data/(?<obs>.+)/(?<date>.+)/(?<type>.+)$', function ($params) {
    $interval = isset($_GET["interval"]) ? $_GET["interval"] : "1d";
    try {
        $file = new File($params["obs"], $params["type"], $params["date"], $interval);
        @ini_set('zlib.output_compression', 0);
        header(http_response_code(200));
        header("Content-Type: application/json");

        $fh = fopen('php://output', 'w');
        $i = 0;
        fputs($fh, '[');
        foreach ($file->read() as $line) {
            if ($i == 0) {
                $line = array("header" => explode(",", $line), "type" => $file->type, "date" => $file->date);
                if ($file->type == $file::TYPE_RAW) $line["colors"] = ["#080", "#008b8b", "#ff8c00", "#9400d3", "#000"];
                $i++;
            }
            fputs($fh, json_encode($line) . ',');
        }
        // FIXME: find a way not to send an empty JSONObject, because of the trailing comma
        // WORKAROUND: Remove the last object in the array client side
        fputs($fh, '{}]');
        $st = ob_get_clean();
        exit($st);
    } catch (FileNotFoundException $ex) {
        header("Content-Type: application/json");
        header(http_response_code(404));
        echo json_encode(array("message" => $ex->getMessage(), "trace" => $ex->getTrace()));
    }
});

route(['GET', 'POST'], "^/api/upload-csv$", function ($params) {
    $res = Upload::uploader();
    $log = fopen("log.txt", "w");
    fwrite($log, print_r($_FILES, true));
    fwrite($log, print_r($res, true));
    fclose($log);

    echo json_encode($res);
});
// Routes for Observatories
route(['GET',], "^/api/observatories/?$", function ($params) {
    $obs = Observatory::ListAllObs();

    header("Content-Type: application/json");
    echo json_encode($obs);
});
route(['GET',], "^/api/observatory/(?<obs>.+)$", function ($params) {
    $obs = Observatory::CreateFromConfig($params["obs"]);

    header("Content-Type: application/json");
    echo json_encode($obs->config);
});

// Routes for Users
route(['GET',], "^/api/users/?$", function ($params) {
    $user = User::ListAllUsers();

    header("Content-Type: application/json");
    echo json_encode($user);
});
route(['GET',], "^/api/users/(?<user_login>.+)$", function ($params) {
    $user = User::CreateFromConfig($params["user_login"]);

    header("Content-Type: application/json");
    echo json_encode($user->config);
});
route(['POST',], "^/api/measure/?$", function ($params) {
    $data = json_decode(file_get_contents("php://input"));
    try {
        $meas = Measurement::CreateMeasure($data);
        header(http_response_code(200));
        echo json_encode($meas);
    } catch (CannotWriteOnFileException $e) {

        header("Content-Type: application/json");
        header(http_response_code(500));
        echo json_encode(array("message" => $e->getMessage(), "trace" => $e->getTrace()));
    }
    // echo var_dump($data);
});

route(['GET'], '^/api/files/seconds/?$', function ($params) {
    if (!isset($_GET["start"])) {
        header("Content-Type: application/json");
        header(http_response_code(400));
        echo json_encode(array("message" => "Start date is not defined"));
        return;
    }
    if (!isset($_GET["end"])) {
        header("Content-Type: application/json");
        header(http_response_code(400));
        echo json_encode(array("message" => "End date is not defined"));
        return;
    }
    if (!isset($_GET["obs"])) {
        header("Content-Type: application/json");
        header(http_response_code(400));
        echo json_encode(array("message" => "Observatory is not defined"));
        return;
    }
    $start = $_GET["start"];
    $end = $_GET["end"];
    $obsCode = $_GET["obs"];

    @ini_set('zlib.output_compression', 9);
    header("Content-Type: application/json");
    $fh = fopen('php://output', 'w');
    fputs($fh, '[');
    foreach (File::getSecondsFilesPathBetweenTwoDates($obsCode, $start, $end) as $file) {
        foreach (File::sampleSecondsData($file, new DateTime($start), new DateTime($end), DateInterval::createFromDateString("1 minutes")) as $meanSample) {
            fputs($fh, json_encode($meanSample) . ',');
        }
    }

    fputs($fh, '{}]');
    $st = ob_get_clean();
    exit($st);
});

header('HTTP/1.0 404 Not Found');
echo '404 Not Found';
