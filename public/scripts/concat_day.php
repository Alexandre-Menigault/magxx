<?php

require_once __DIR__ . '/../../config.php';

$time_start = microtime(true);

$raw_headers = ["t", "ms", "X", "Y", "Z", "F"];

$date = new DateTime();
$date->setTime(0, 0, 0, 0);
$date->sub(new DateInterval('P1D'));

$end_date = new DateTime();
$end_date->setTime(0, 0, 0, 0);

$nb_files = 0;

// On parcours l'intervale depuis `$date` jusqu'a `$end_date`
while ($date < $end_date) {
    // On récupère les infos du jour en cours
    $Y = $date->format("Y");
    $m = $date->format("m");
    $d = $date->format("d");
    $directory = $GLOBALS["DATABANK_PATH"] . "/upstore/CLF3/" . $Y . "/" . $m . "/" . $d . "/raw/";
    $files = array_filter(scandir($directory), function ($item) {
        return $item[0] !== '.'; // Retire les dossiers '.', '..' et les fichers/dossiers cachés
    });
    // On crée un fichier vide du jour
    $filename_day = "CLF3" . $Y . $m . $d . "-raw.csv";
    $end_dir =  $GLOBALS["DATABANK_PATH"] . "/magstore/CLF3/" . $Y . "/raw/";
    $end_file = fopen($end_dir . $filename_day, "w");
    fwrite($end_file, implode(",", $raw_headers) . PHP_EOL);

    // On parcours les fichiers du dossier du jour en cours
    foreach ($files as $file) {
        // On récupère chaque ligne du fichier 5min en cours
        foreach (read($directory . $file) as $line) {
            fputs($end_file, $line);
        }
        $nb_files++;
    }
    $date->add(new DateInterval('P1D'));
}

// Mesure du temps écoulé
$time_end = microtime(true);
$time = $time_end - $time_start;

echo "Parsed " . $nb_files . " filed in " . $time * 1000 . "ms<br/>";




function read($link)
{
    if (file_exists($link)) {
        $fp = fopen($link, "rb");
        fgets($fp);
        while (($line = fgets($fp)) != false) {
            yield $line;
        }
        fclose($fp);
    }
}
