<?php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/Path.php';
require_once __DIR__ . '/../../src/Teno.php';

$time_start = microtime(true);

$raw_headers = ["t", "ms", "X", "Y", "Z", "F"];

$interval = isset($_GET["days"]) ? $_GET["days"] : "1";
$obs = isset($_GET["obs"]) ? $_GET["obs"] : "CLF3";

echo $obs, PHP_EOL;



// $date = new DateTime("2019-09-17T00:00:00", new DateTimeZone("UTC"));
$date = new DateTime("now", new DateTimeZone("UTC"));
// $Y = 2019;
// $m = 8;
// $d = 18;
$Y = intval($date->format("Y"));
$m = intval($date->format("m"));
$d = intval($date->format("d"));
// $end_teno = Teno::fromYYYYDDMMHHMMSS(2019, 8, 19, 0, 0, 0)->teno; // Basically today at 00:00:00
$end_teno = Teno::fromYYYYDDMMHHMMSS($Y, $m, $d + 1, 0, 0, 0)->teno; // Basically today at 00:00:00
$cur_teno = $end_teno - intval($interval) * Teno::$DAYS_SECONDS;
// $date->setTime(0, 0, 0, 0);
// $date->sub(new DateInterval('P' . $interval . 'D'));

// $end_date = new DateTime();
// $end_date->setTime(0, 0, 0, 0);
// $date->sub(new DateInterval('P0D'));

$nb_files = 0;
$day_count = 0;
$types = ["raw", "env", "log"];

// TODO: concat tous les fichiers de tous les observatoires
// On parcours l'intervale depuis `$date` jusqu'a `$end_date`


while ($cur_teno < $end_teno) {
    // On récupère les infos du jour en cours
    $d = Teno::toUTC($cur_teno);
    $Y = $d->yyyy;
    $m = Teno::getFullTime($d->mmmm);
    $day = Teno::getFullTime($d->dddd);
    foreach ($types as $type) {
        $directory = Path::join($GLOBALS["DATABANK_PATH"], '/upstore', $obs, $Y, $m, $day, $type);
        if (!is_dir($directory)) continue;
        var_dump($directory);
        $files = array_filter(scandir($directory), function ($item) {
            return $item[0] !== '.'; // Retire les dossiers '.', '..' et les fichers/dossiers cachés
        });
        // On crée un fichier vide du jour
        $filename_day = $obs . $d->teno . "-" . $type . ".csv";
        $end_dir =  Path::join($GLOBALS["DATABANK_PATH"], "/magstore", $obs, $Y, $type);
        if (!file_exists($end_dir)) mkdir($end_dir, 0777, true);

        if ($type != "raw") {
            if ($type != "raw" && file_exists(Path::join($directory, $files[2]))) {
                copy(Path::join($directory, $files[2]), Path::join($end_dir, $filename_day));
                $nb_files++;
            }
        } else {
            var_dump(Path::join($end_dir, $filename_day));
            // if (!file_exists(Path::join($end_dir, $filename_day))) continue;
            $end_file = fopen(Path::join($end_dir, $filename_day), "w");
            fwrite($end_file, implode(",", $raw_headers) . PHP_EOL);
            // On parcours les fichiers du dossier du jour en cours
            foreach ($files as $file) {
                // On récupère chaque ligne du fichier 5min en 
                foreach (read(Path::join($directory, $file)) as $line) {
                    if (!$end_file) continue;
                    fputs($end_file, $line);
                }
                $nb_files++;
            }
        }
    }
    $cur_teno += Teno::$DAYS_SECONDS;
    $day_count++;
}

// Mesure du temps écoulé
$time_end = microtime(true);
$time = $time_end - $time_start;

echo "Parsed " . $nb_files . " files of " . $day_count . " days in " . $time * 1000 . "ms" . PHP_EOL;




function read($link)
{
    set_time_limit(0);
    if (file_exists($link)) {
        $fp = fopen($link, "rb");
        fgets($fp);
        while (($line = fgets($fp)) != false) {
            yield $line;
        }
        fclose($fp);
    }
}
