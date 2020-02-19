<?php

include_once "../../config.php";
include_once "../../src/Teno.php";

$time = microtime(TRUE);
$raw_headers = ["t", "ms", "X", "Y", "Z", "F", "flag"];

$directory = Path::join(DATABANK_PATH, '/magstore', "CLF5", "2019", "raw");

function trim_magis_line($line)
{
    return explode(" ", preg_replace('/\s+/', ' ', $line));
}

$files = array_filter(scandir($directory), function ($item) {
    return $item[0] !== '.' && // Retire les dossiers '.', '..' et les fichers/dossiers cachés
        count(explode('.', $item)) == 2 && // Garde uniquement les fichiers .sec de magis
        explode('.', $item)[1] === "sec";
});


for ($i = 0; $i < count($files); $i++) {
    if (empty($files[$i])) continue;
    $file = $files[$i];
    $fp = fopen(Path::join($directory, $file), "rb");

    $date = substr($file, 3, 8);
    $cur_year = intval(substr($date, 0, 4));
    $cur_month = intval(substr($date, 4, 2));
    $cur_day = intval(substr($date, 6, 2));
    $t = Teno::fromYYYYDDMMHHMMSS($cur_year, $cur_month, $cur_day, 0, 0, 0)->teno;

    // TODO: change format to OBSX-teno-type.csv
    $end_file = fopen(Path::join($directory, "CLF5" . $t . "-raw.csv"), "wa+");
    // $end_file = fopen(Path::join($directory, "CLF5-" . $date . "-raw.csv"), "w");

    writeMagisToTeno($fp, $end_file, $raw_headers);

    fclose($fp);
    fclose($end_file);
}


function writeMagisToTeno($fp, $end_file, $headers)
{

    fwrite($end_file, implode(',', $headers) . PHP_EOL);

    for ($i = 0; $i < 15; $i++) fgets($fp); // REMOVE headers from magis file

    $end = false;
    while (!$end && $line = fgets($fp)) {
        $data = trim_magis_line($line);
        list($yyyy, $mmmm, $dddd) = explode("-", $data[0]);
        // Read ms
        $ms = explode(".", $data[1])[1];
        // Read hh:mm:ss
        $data[1] = substr($data[1], 0, strlen($data[1]) - 4);
        list($hh, $mm, $ss) = explode(":", $data[1]);
        // Build Teno
        $teno = Teno::fromYYYYDDMMHHMMSS(intval($yyyy), intval($mmmm), intval($dddd), intval($hh), intval($mm), intval($ss));

        fwrite(
            $end_file,
            $teno->teno . ',' .
                $ms . ',' .
                $data[3] . ',' .
                $data[4] . ',' .
                $data[5] . ',' .
                $data[6] . ',' .
                "0" . PHP_EOL
        );
    }
}

$time2 = microtime(TRUE) - $time;
echo "Done in " . $time2 . " s";
