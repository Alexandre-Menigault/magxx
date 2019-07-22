<?php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/Path.php';

$time_start = microtime(true);

$raw_headers = ["t", "ms", "X", "Y", "Z", "F"];

$interval = isset($_GET["days"]) ? $_GET["days"] : "1";

$date = new DateTime();
$date->setTime(0, 0, 0, 0);
$date->sub(new DateInterval('P' . $interval . 'D'));

$end_date = new DateTime();
$end_date->setTime(0, 0, 0, 0);
$date->sub(new DateInterval('P0D'));

$nb_files = 0;
$day_count = 0;
$types = ["raw", "env", "log"];

// TODO: concat tous les fichiers de tous les observatoires
// On parcours l'intervale depuis `$date` jusqu'a `$end_date`
while ($date < $end_date) {
    // On récupère les infos du jour en cours
    $Y = $date->format("Y");
    $m = $date->format("m");
    $d = $date->format("d");
    foreach ($types as $type) {
        $directory = Path::join($GLOBALS["DATABANK_PATH"], '/upstore/CLF3', $Y, $m, $d, $type);
        $files = array_filter(scandir($directory), function ($item) {
            return $item[0] !== '.'; // Retire les dossiers '.', '..' et les fichers/dossiers cachés
        });
        // On crée un fichier vide du jour
        $filename_day = "CLF3" . $Y . $m . $d . "-" . $type . ".csv";
        $end_dir =  Path::join($GLOBALS["DATABANK_PATH"], "/magstore/CLF3/", $Y, $type);
        if (!file_exists($end_dir)) mkdir($end_dir, 0777, true);

        if ($type != "raw") {
            copy(Path::join($directory, $files[2]), Path::join($end_dir, $filename_day));
            $nb_files++;
        } else {
            $end_file = fopen(Path::join($end_dir, $filename_day), "w");
            fwrite($end_file, implode(",", $raw_headers) . PHP_EOL);

            // On parcours les fichiers du dossier du jour en cours
            foreach ($files as $file) {
                // On récupère chaque ligne du fichier 5min en cours
                foreach (read(Path::join($directory, $file)) as $line) {
                    fputs($end_file, $line);
                }
                $nb_files++;
            }
        }
    }
    $date->add(new DateInterval('P1D'));
    $day_count++;
}

// Mesure du temps écoulé
$time_end = microtime(true);
$time = $time_end - $time_start;

echo "Parsed " . $nb_files . " files of " . $day_count . " days in " . $time * 1000 . "ms<br/>";




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
