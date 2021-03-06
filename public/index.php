<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Utils.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/authenticate.php';
require_once __DIR__ . '/../src/Entities/File.php';
require_once __DIR__ . '/../src/Teno.php';
require_once __DIR__ . '/../src/Upload.php';
require_once __DIR__ . '/../src/Entities/Observatory.php';
require_once __DIR__ . '/../src/Entities/User.php';
require_once __DIR__ . '/../src/Entities/Measure.php';
require_once __DIR__ . '/../src/Entities/Baseline.php';
require_once __DIR__ . '/../src/Entities/Definitive.php';
require_once __DIR__ . '/../src/exceptions/FileNotFoundException.php';
require_once __DIR__ . '/../src/exceptions/CannotWriteOnFileException.php';

route('GET', '^/$', function () {
});

route("GET", '^/api/data/(?<obs>.+)/(?<date>.+)/(?<type>.+)$', function ($params) {
    $interval = isset($_GET["interval"]) ? $_GET["interval"] : "1d";
    try {
        $file = new File($params["obs"], $params["type"], intval($params["date"]), $interval);
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
    // If the user failed to authenticate, this function will cause the rest of the upload function not to be executed
    authenticate();
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

/**
 * Get the last config for this observatory
 */
route(['GET',], "^/api/observatory/(?<obs>.+)$", function ($params) {
    $obs = Observatory::CreateFromConfig($params["obs"]);

    header("Content-Type: application/json");
    echo json_encode($obs->config[count($obs->config) - 1]);
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
route(['GET'], "^/api/measure/?$", function ($params) {
    $obs = $_GET["obs"];
    $year = $_GET["year"];

    header("Content-Type: application/json");
    header(http_response_code(200));
    echo json_encode(Measurement::GetFinalList($obs, $year));
});
route(['POST',], "^/api/measure/?$", function ($params) {
    $data = json_decode(file_get_contents("php://input"));
    try {
        $meas = Measurement::CreateMeasure($data);
        $meas->Save();
        header("Content-Type: application/json");
        header(http_response_code(200));
        echo json_encode($meas);
    } catch (CannotWriteOnFileException $e) {

        header("Content-Type: application/json");
        header(http_response_code(500));
        echo json_encode(array("message" => $e->getMessage(), "trace" => $e->getTrace()));
    }
    // echo var_dump($data);
});
route(['POST',], "^/api/measure/test?$", function ($params) {
    $data = json_decode(file_get_contents("php://input"));
    try {
        $meas = Measurement::CreateMeasure($data);
        header("Content-Type: application/json");
        header(http_response_code(200));
        echo json_encode($meas->Test());
    } catch (CannotWriteOnFileException $e) {

        header("Content-Type: application/json");
        header(http_response_code(500));
        echo json_encode(array("message" => $e->getMessage(), "trace" => $e->getTrace()));
    }
    // echo var_dump($data);
});

route(['POST',], "^/api/baseline/?$", function ($params) {
    $data = json_decode(file_get_contents("php://input"));
    try {
        $baseline = new Baseline($data);
        header("Content-Type: application/json");
        header(http_response_code(200));
        $hdzfPath = $baseline->Compute();
        $baseline->sendHDZF($hdzfPath);
        //TODO: Envoyer les mesure absolues de cette période avec
    } catch (Exception $e) {

        header("Content-Type: application/json");
        header(http_response_code(500));
        echo json_encode(array("message" => $e->getMessage(), "trace" => $e->getTrace()));
    }
    // echo var_dump($data);
});

route(['GET',], "^/api/baseline/screen?$", function ($params) {
    $data = json_decode(file_get_contents("php://input"));
    header("Content-Disposition: inline");
    try {

        if (!isset($_GET["obs"]) || !isset($_GET["startTeno"]) || !isset($_GET["endTeno"])) {
            header("Content-Type: plain/text");
            header(http_response_code(400));
            echo "The request is malformed, observatory or interval is not set";
            return;
        }
        header("Content-Type: plain/text");
        header(http_response_code(200));
        $obs = $_GET["obs"];
        $startTeno = Teno::toUTC(intval($_GET["startTeno"]));
        $endTeno = Teno::toUTC(intval($_GET["endTeno"]));
        echo Baseline::getLastScreenOut($obs, $startTeno, $endTeno);
    } catch (Exception $e) {

        header("Content-Type: plain/text");
        header(http_response_code(500));
        echo $e->getMessage();
    }
    // echo var_dump($data);
});

route(['GET'], '^/api/baseline/intervals/?$', function ($params) {
    if (!isset($_GET["obs"]) || !isset($_GET["year"])) {
        header("Content-Type: plain/text");
        header(http_response_code(400));
        echo "The request is malformed, observatory or year is not set";
        return;
    }
    $year = intval($_GET["year"]);
    $obs = $_GET["obs"];
    header("Content-Type: application/json");
    header(http_response_code(200));
    echo json_encode(Baseline::getIntervalsForYear($obs, $year));
});

route(['GET'], '^/api/baseline/interval-trys/?$', function ($params) {
    if (!isset($_GET["obs"]) || !isset($_GET["year"]) || !isset($_GET["intervalString"])) {
        header("Content-Type: application/json");
        header(http_response_code(400));
        echo json_encode(array("message" => "The request is malformed, observatory, year, intervalString is not set"));
        return;
    }
    $year = intval($_GET["year"]);
    $obs = $_GET["obs"];
    $intervalString = $_GET["intervalString"];
    header("Content-Type: application/json");
    header(http_response_code(200));
    echo json_encode(Baseline::getIntervalsTrysForYear($obs, $year, $intervalString));
});

route(['GET'], '^/api/baseline/try-config/?$', function ($params) {
    if (!isset($_GET["obs"]) || !isset($_GET["year"]) || !isset($_GET["intervalString"]) || !isset($_GET["try"])) {
        header("Content-Type: application/json");
        header(http_response_code(400));
        echo json_encode(array("message" => "The request is malformed, observatory, year, intervalString, try is not set"));
        return;
    }
    $year = intval($_GET["year"]);
    $obs = $_GET["obs"];
    $intervalString = $_GET["intervalString"];
    $try = $_GET["try"];
    header("Content-Type: application/json");
    header(http_response_code(200));
    echo json_encode(Baseline::getTryConfigWithObsConf($obs, $year, $intervalString, $try));
});

route(['GET'], '^/api/definitive/compute/?$', function ($params) {
    if (!isset($_GET["obs"]) || !isset($_GET["year"]) || !isset($_GET["intervalString"]) || !isset($_GET["try"])) {
        header("Content-Type: application/json");
        header(http_response_code(400));
        echo json_encode(array("message" => "The request is malformed, observatory, year, intervalString, try is not set"));
        return;
    }
    $year = intval($_GET["year"]);
    $obs = $_GET["obs"];
    $intervalString = $_GET["intervalString"];
    $try = $_GET["try"];

    try {
        Definitive::compute($obs, $year, $intervalString, $try);

        header("Content-Type: application/json");
        header(http_response_code(200));
        echo json_encode(array(
            "message" => "done"
        ));
    } catch (Exception $e) {
        header("Content-Type: application/json");
        header(http_response_code(500));
        echo json_encode(array(
            "message" => $e->getMessage()
        ));
        return;
    }
});

route(['GET'], '^/api/definitive/trys/?$', function ($params) {
    if (!isset($_GET["obs"]) || !isset($_GET["year"]) || !isset($_GET["intervalString"])) {
        header("Content-Type: application/json");
        header(http_response_code(400));
        echo json_encode(array("message" => "The request is malformed, observatory, year, intervalString, try is not set"));
        return;
    }
    $year = intval($_GET["year"]);
    $obs = $_GET["obs"];
    $intervalString = $_GET["intervalString"];

    header("Content-Type: application/json");
    header(http_response_code(200));
    echo json_encode(Definitive::getTrys($obs, $year, $intervalString));
});

route(['GET'], '^/api/definitive/(?<obs>.+)/(?<year>.+)/(?<intervalString>.+)/(?<try>.+)/(?<startTeno>.+)/?$', function ($params) {
    header("Content-Type: plain/text");
    try {
        header(http_response_code(200));
        echo Definitive::getFileContentFromIntervalTry($params["obs"], $params["year"], $params["intervalString"], $params["try"], $params["startTeno"]);
    } catch (Exception $e) {
        header(http_response_code(500));
    }
});

route(['GET'], '^/api/files/seconds/?$', function ($params) {
    $errors = [];
    if (!isset($_GET["start"])) {
        array_push($errors, "Start date is not defined");
    } else if (isDateValid($_GET["start"])) {
        array_push($errors, "Start date (" . $_GET['start'] . ") is not a valid date: Format YYYY-MM-DD");
    }
    if (!isset($_GET["end"])) {
        array_push($errors, "End date is not defined");
    } else if (isDateValid($_GET["end"])) {
        array_push($errors, "End date (" . $_GET['end'] . ") is not a valid date: Format YYYY-MM-DD");
    }
    if (!isset($_GET["obs"])) {
        array_push($errors, "Observatory is not defined");
    }
    if (count($errors) > 0) {
        header("Content-Type: application/json");
        header(http_response_code(400));
        echo json_encode($errors);
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

route(["GET"], '^/api/teno/utc$', function ($params) {
    if (!isset($_GET["teno"])) {
        header(http_response_code(400));
        echo "Teno is not defined";
        return;
    }
    $val = intval($_GET["teno"]);
    $teno = Teno::toUTC($val);

    header(http_response_code(200));
    header("Content-Type: application/json");
    echo json_encode($teno);
});

route(["GET"], '^/api/utc/teno$', function ($params) {
    if (!isset($_GET["year"], $_GET["day"], $_GET["month"], $_GET["hour"], $_GET["minutes"], $_GET["seconds"])) {
        header(http_response_code(400));
        echo "Request malformed";
        return;
    }

    $year = intval($_GET["year"]);
    $month = intval($_GET["month"]);
    $day = intval($_GET["day"]);
    $hour = intval($_GET["hour"]);
    $minutes = intval($_GET["minutes"]);
    $seconds = intval($_GET["seconds"]);


    $teno = Teno::fromYYYYDDMMHHMMSS($year, $month, $day, $hour, $minutes, $seconds);

    header(http_response_code(200));
    header("Content-Type: application/json");
    echo json_encode($teno);
});

route(["POST"], '^/api/teno/utc/batch$', function ($params) {
    if (!isset($_POST["teno_obj"])) {
        header(http_response_code(400));
        header("Content-Type: application/json");
        echo json_encode(array("error" => "teno_obj is not defined"));
        return;
    }
    $teno_obj = json_decode($_POST["teno_obj"]);
    $end = array();


    foreach ($teno_obj as $key => $teno) {
        $end[$key] = Teno::toUTC(intval($teno));
    }

    header(http_response_code(200));
    header("Content-Type: application/json");
    echo json_encode($end);
});

route(["GET"], '^/api/file/tree/?$', function ($params) {

    $path = "";
    if (!isset($_GET["path"])) $path = Path::join("/");
    else $path = Path::join("/", $_GET["path"]);

    $baseURI = Path::join(DATABANK_PATH, "magstore", $path);

    if (strpos($path, '..') !== false || strpos($path, '../' . DIRECTORY_SEPARATOR) !== false) {

        header(http_response_code(400));
        header("Content-Type: application/json");
        echo json_encode(array("message" => "Cannot use '../' in path", "path" => $path));
        return;
    }
    if (!is_dir($baseURI)) {
        header(http_response_code(404));
        header("Content-Type: application/json");
        echo json_encode(array("message" => "Observatory or year not valid"));
        return;
    }

    function array_group_by(array $arr, callable $key_selector)
    {
        $result = array();
        foreach ($arr as $i) {
            $key = call_user_func($key_selector, $i);
            $result[$key][] = $i;
        }
        return $result;
    }

    $files = scandir($baseURI);
    $res = array();
    for ($i = 0; $i < count($files); $i++) {
        if (in_array($files[$i], array('..', '.'))) continue; // Ignore .. and . directories
        if (is_dir(Path::join($baseURI, $files[$i])))
            array_push($res, array("name" => $files[$i], "type" => "group"));
        if (is_file(Path::join($baseURI, $files[$i])))
            array_push($res, array("name" => $files[$i], "type" => "file"));
    }
    $res = array_group_by($res, function ($i) {
        return $i["type"];
    });
    header(http_response_code(200));
    header("Content-Type: application/json");
    echo json_encode($res);
});

function isDateValid($dateString, $format = "Y-m-d")
{
    date_default_timezone_set('UTC');
    $date = DateTime::createFromFormat($format, $dateString);
    return $date && $date->format($format) === $date;
}

header('HTTP/1.0 404 Not Found');
echo '404 Not Found';
