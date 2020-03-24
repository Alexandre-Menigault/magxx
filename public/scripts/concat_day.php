<?php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/Path.php';
require_once __DIR__ . '/../../src/Teno.php';

echo "============Start Concat==============";
// Start timer
$timeStart = microtime(true);
// Set the variation files header
const rawHeaders = ["t", "ms", "X", "Y", "Z", "F", "flag"];
// The files types to concat
global $fileTypes;


global $filesCount; // The total file count
global $daysCount; // The total file count

define("upstorePath", Path::join(DATABANK_PATH, '/upstore'));
define("magstorePath", Path::join(DATABANK_PATH, '/magstore'));

// Get params
// $daysToConcat = The number of days to concat from now(excluded)
//                 If not defined, 1 is default
// $obs = The observatory set to concat
//        If not defined, XXX if default
$daysToConcat = isset($_GET["days"]) ? $_GET["days"] : "1";
define("obs", isset($_GET["obs"]) ? $_GET["obs"] : "XXX");

// Get curernt time
$now = new DateTime("now", new DateTimeZone("UTC"));
$nowYear = intval($now->format("Y"));
$nowMonth = intval($now->format("m"));
$nowDay = intval($now->format("d"));
// Set the end search to today at 00:00:00 tenoUTC
$endTeno = Teno::fromYYYYDDMMHHMMSS($nowYear, $nowMonth, $nowDay, 0, 0, 0)->teno;
// Set the current date to the first day to search at 00:00:00 tenoUTC
$currentTeno = $endTeno - intval($daysToConcat) * Teno::$DAYS_SECONDS;

$fileTypes =  ["raw", "env", "log"];
$filesCount = 0;
$daysCount = 0; // The total days count;

while ($currentTeno < $endTeno) {

    concatDay($currentTeno);

    $currentTeno += Teno::$DAYS_SECONDS;
    $daysCount++;
}

$timeEnd = microtime(true);
$ellapsedSeconds = ($timeEnd - $timeStart) * 1000;
$obs = obs;
echo "
    
    Observatory: {$obs}
    Number of files : {$filesCount}
    Number of days : {$daysCount}
    Ellapsed time: {$ellapsedSeconds} ms
";

echo "======================================" . PHP_EOL;

/**
 * Concat a whole day
 *
 * @global $fileTypes
 * @param int $currentTime
 * @return void
 */
function concatDay($currentTime)
{
    global $fileTypes;
    $curentTeno = Teno::toUTC($currentTime);
    $year = $curentTeno->yyyy;
    $month = Teno::getFullTime($curentTeno->mmmm);
    $day = Teno::getFullTime($curentTeno->dddd);
    foreach ($fileTypes as $type) {
        concatType($year, $month, $day, $curentTeno, $type);
    }
}

/**
 * Concat all files of a given type
 *
 * @global $filesCount
 * @param int $year
 * @param int $month
 * @param int $day
 * @param Teno $currentTeno
 * @param string $type
 * @return void
 */
function concatType($year, $month, $day, $currentTeno, $type)
{
    global $filesCount;
    $directory = Path::join(upstorePath, obs, $year, $month, $day, $type);
    if (!is_dir($directory)) return;
    $files = array_filter(scandir($directory), function ($item) {
        return $item[0] !== ".";/* Remove "." and ".." directories form the list*/
    });
    $obs = obs;
    $fixedTeno = $currentTeno->teno;
    $todayFilename = "{$obs}{$fixedTeno}-{$type}.csv";
    $endDir = Path::join(magstorePath, $obs, $year, $type);
    // If endDir dont exists, create it and all its previous directories if needed
    if (!file_exists($endDir))
        mkdir($endDir, 0777, true);
    if ($type != "raw") {
        if (file_exists(Path::join($directory, $files[2]))) {
            // If not raw files and exists, just copy to the end dir
            copy(Path::join($directory, $files[2]), Path::join($endDir, $todayFilename));
            $filesCount++;
        }
    } else {
        // Loop through all raw files and concat enery line into the end file
        $endFile = fopen(Path::join($endDir, $todayFilename), "w");
        fwrite($endFile, implode(",", rawHeaders) . PHP_EOL);
        foreach ($files as $file) {
            foreach (read(Path::join($directory, $file)) as $line) {
                if (!$endFile) continue;
                fputs($endFile, trim($line) . ',' . "0" . PHP_EOL);
            }
            $filesCount++;
        }
    }
}

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
