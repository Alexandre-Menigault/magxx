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

        $startBlTeno = $startTeno->teno + Teno::getNumberOfLeaps($startTeno->teno);
        $endBlTeno = $endTeno->teno + 86400 + Teno::getNumberOfLeaps($endTeno->teno);
        $startDefTeno = $startTeno->teno + 86400 + Teno::getNumberOfLeaps($startTeno->teno);
        $endDefTeno = $endTeno->teno - (86400 + Teno::getNumberOfLeaps($endTeno->teno));
        // $startBlTeno = $startTeno->teno + 5;
        // $endBlTeno = $endTeno->teno + 86405;
        // $startDefTeno = $startTeno->teno + 86405;
        // $endDefTeno = $endTeno->teno - 86405;
        // Call the definitive fortran program
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
        // $timeStep = number_format(floatval($tryConfig->baseline_time_step), 5);

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

        Definitive::SaveInputLocal($baseDir, $input);
        // $this->SaveConfigLocal($baseDir);
        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
            1 => array("file", Path::join($tempDir, "screen.out"), "a"),  // stdout is a pipe that the child will write to
            2 => array("file", Path::join($tempDir, "screen.err"), "a"),  // stdout is a pipe that the child will write to
        );

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
}
