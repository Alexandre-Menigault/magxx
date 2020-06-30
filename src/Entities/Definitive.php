<?php

require_once __DIR__ . "/Observatory.php";
require_once __DIR__ . "/Baseline.php";
require_once __DIR__ . "/../Teno.php";
require_once __DIR__ . "/../Utils.php";

class Definitive
{
    /**
     * Compute the definitive data for the selected observatory and baseline interval try
     *
     * @param string $obs
     * @param int $year
     * @param string $intervalString
     * @param string $try
     * @return array
     * @throws Exception
     */
    public static function compute($obs, $year, $intervalString, $try)
    {
        ["try" => $tryConfig, "obs" => $obsConfig] = Baseline::getTryConfigWithObsConf($obs, $year, $intervalString, $try);

        [$blvFilePath, $rawDataFilePath] = Baseline::getTryBLVandRawDataList($obs, $year, $intervalString, $try);
        [$startTeno, $endTeno] = Definitive::getTenoIntervalFromIntervalString(intval($year), $intervalString);
        $leapFilePath = LEAPS_FILE_PATH;

        //NOTE: The start of baseline is day - 1 and the end of baseline is day +1
        //NOTE: To not break the definitive script
        $startBlTeno = $startTeno->teno - 2 * 86400 + Teno::getNumberOfLeaps($startTeno->teno);
        $endBlTeno = $endTeno->teno + 2 * 86400 + Teno::getNumberOfLeaps($startTeno->teno);
        $startDefTeno = $startTeno->teno + Teno::getNumberOfLeaps($startTeno->teno);
        $endDefTeno = $endTeno->teno + Teno::getNumberOfLeaps($startTeno->teno);

        $baseDir = Definitive::getBaseDirOrCreate($obs, $year, $intervalString, $try);
        $tempDir = Path::join($baseDir, 'Temp', "");
        mkdir($tempDir, 0777, true);
        $resultPath = Path::join($baseDir, 'Results', "");
        mkdir($resultPath, 0777, true);
        $eulerA = number_format(floatval($obsConfig->euler_a), 5);
        $eulerB = number_format(floatval($obsConfig->euler_b), 5);
        $eulerG = number_format(floatval($obsConfig->euler_g), 5);

        $noiseX = number_format(floatval($obsConfig->noise_XYZF->X), 5);
        $noiseY = number_format(floatval($obsConfig->noise_XYZF->Y), 5);
        $noiseZ = number_format(floatval($obsConfig->noise_XYZF->Z), 5);
        $noiseF = number_format(floatval($obsConfig->noise_XYZF->F), 5);

        $timeStep = $tryConfig->baseline_time_step;

        $meanX = number_format(floatval($tryConfig->mean_XYZF->X), 5);
        $meanY = number_format(floatval($tryConfig->mean_XYZF->Y), 5);
        $meanZ = number_format(floatval($tryConfig->mean_XYZF->Z), 5);
        $meanF = number_format(floatval($tryConfig->mean_XYZF->F), 5);

        $scalingX = number_format(floatval($tryConfig->scaling_XYZF->X), 5);
        $scalingY = number_format(floatval($tryConfig->scaling_XYZF->Y), 5);
        $scalingZ = number_format(floatval($tryConfig->scaling_XYZF->Z), 5);
        $scalingF = number_format(floatval($tryConfig->scaling_XYZF->F), 5);

        $input = "{$eulerA}
{$eulerB}
{$eulerG}
{$noiseX}
{$noiseY}
{$noiseZ}
{$noiseF}
{$timeStep}
{$meanX}
{$meanY}
{$meanZ}
{$meanF}
{$scalingX}
{$scalingY}
{$scalingZ}
{$scalingF}
{$leapFilePath}
{$rawDataFilePath}
{$blvFilePath}
{$obs}
{$resultPath}
{$startBlTeno}
{$endBlTeno}
{$startDefTeno}
{$endDefTeno}
";

        // $this->SaveConfigLocal($baseDir);
        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
            1 => array("file", Path::join($tempDir, "screen.out"), "a"),  // stdout is a pipe that the child will write to
            2 => array("file", Path::join($tempDir, "screen.err"), "a"),  // stdout is a pipe that the child will write to
        );
        Definitive::SaveInputLocal($baseDir, $input);

        $process = proc_open(DEF_BINARY_PATH, $descriptorspec, $pipes);
        if (is_resource($process)) {
            fwrite($pipes[0], $input);
            fclose($pipes[0]);

            proc_close($process);
        }
    }

    private static function getBaseDirOrCreate($obs, $year, $intervalString, $try)
    {
        $baseBlvDir = Path::join(DATABANK_PATH, "magstore", $obs, $year, "definitive", $intervalString);
        $id = 0;
        if (!is_dir($baseBlvDir)) {
            $id = Utils::twoDigits(1);
            mkdir($baseBlvDir, 0777, true);
        } else {
            $dirFiles = array_diff(scandir($baseBlvDir, SCANDIR_SORT_DESCENDING), array('.', '..'));
            $id = Utils::twoDigits(intval($dirFiles[0]) + 1);
        }
        $endpath = Path::join($baseBlvDir, $id);
        mkdir($endpath, 0777, true);
        return $endpath;
    }

    private static function getResultDir($obs, $year, $intervalString, $try)
    {
        return Path::join(DATABANK_PATH, "magstore", $obs, $year, "definitive", $intervalString, $try, "Results");
    }
    public static function getTrys($obs, $year, $intervalString)
    {
        $baseDir = Path::join(DATABANK_PATH, "magstore", $obs, $year, "definitive", $intervalString);
        $dirFiles = array_diff(scandir($baseDir, SCANDIR_SORT_DESCENDING), array('.', '..'));
        return $dirFiles;
    }

    public static function SaveInputLocal($baseBlvDir, $input)
    {
        file_put_contents(Path::join($baseBlvDir, "input.dat"), $input);
    }

    /**
     * Get the interval from a year and interval string
     *
     * @param int $year
     * @param string $intervalString
     * @return Teno[]
     */
    private static function getTenoIntervalFromIntervalString($year, $intervalString)
    {
        $splitedString = explode("_", $intervalString);
        $startStr = $splitedString[0];
        $endStr = $splitedString[1];
        $startSplited = explode("-", $startStr);
        $startMonth = intval($startSplited[0]);
        $startDay = intval($startSplited[1]);
        $start = Teno::fromYYYYDDMMHHMMSS($year, $startMonth, $startDay, 0, 0, 0);
        $endSplited = explode("-", $endStr);
        $endMonth = intval($endSplited[0]);
        $endDay = intval($endSplited[1]);
        $end = Teno::fromYYYYDDMMHHMMSS($year, $endMonth, $endDay, 0, 0, 0);

        return [$start, $end];
    }

    /**
     * Get the file contents of a definitive data for a obs in interval try 
     *
     * @param string $obs
     * @param string $year
     * @param string $intervalString
     * @param string $try
     * @param string $startTeno
     * @return Generator|string
     * @throws Exception
     */
    public static function getFileContentFromIntervalTry($obs, $year, $intervalString, $try, $startTeno)
    {
        $resultsDir = Definitive::getResultDir($obs, $year, $intervalString, $try);
        $tenDigitsStartTeno = Teno::toUTC($startTeno)->fixedTeno();
        $filename = "{$obs}-{$tenDigitsStartTeno}-def.csv";
        $filePath = Path::join($resultsDir, $filename);
        if (!is_file($filePath))
            throw new Exception("No file this day {$startTeno}");
        // Read the content
        return file_get_contents($filePath);
    }
}
