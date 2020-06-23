<?php
// require_once __DIR__ . "/../Path.php";
// require_once __DIR__ . "/../Teno.php";
// require_once __DIR__ . "/../File.php";
// require_once __DIR__ . "/Measure.php";
// require_once __DIR__ . "/../../config.php";
require_once __DIR__ . "/../Utils.php";
require_once __DIR__ . "/Observatory.php";

class Baseline
{
    public $observatory;
    /** @var Teno */
    public $startDate;
    /** @var Teno */
    public $endDate;

    // Start Observations
    /** @var int Whole number */
    public $numberOfObservations;
    /** @var float Float number */
    public $searchTimeHalfInterval;

    public $angle1;
    public $angle2;
    public $angle3;

    public $noiseIVarX;
    public $noiseIVarY;
    public $noiseIVarZ;
    public $noiseIVarF;

    public $noiseVarD;
    public $noiseVarI;
    public $noiseVarF;
    // End Observations

    // Start Configuration
    public $numberIterations;
    /** @var number In days  */
    public $baselineTimeStep;
    public $baselineTimeScale;

    public $BslMeanX;
    public $BslMeanY;
    public $BslMeanZ;
    public $BslMeanF;

    public $BslScalingX;
    public $BslScalingY;
    public $BslScalingZ;
    public $BslScalingF;

    public $imposedValue1_Date;
    public $imposedValue1_ValueH;
    public $imposedValue1_ValueD;
    public $imposedValue1_ValueZ;

    public $imposedValue2_Date;
    public $imposedValue2_ValueH;
    public $imposedValue2_ValueD;
    public $imposedValue2_ValueZ;
    // End configuration

    /** @var string the path to the file listused for computing the baseline */
    public $fileListPath;

    public function __construct($data)
    {
        // var_dump($data);
        $this->observatoryName = $data->obs;
        $this->observatory = Observatory::CreateFromConfig($this->observatoryName, true);
        $this->startDate = Teno::toUTC(intval($data->start_teno));
        $this->endDate = Teno::toUTC(intval($data->end_teno));
        $this->numberOfObservations = $data->observations_number;
        $this->searchTimeHalfInterval = $data->stiv;
        $this->angle1 = $data->angles[0];
        $this->angle2 = $data->angles[1];
        $this->angle3 = $data->angles[2];
        $this->noiseIVarX = $data->noiseXYZF->x;
        $this->noiseIVarY = $data->noiseXYZF->y;
        $this->noiseIVarZ = $data->noiseXYZF->z;
        $this->noiseIVarF = $data->noiseXYZF->f;

        $this->noiseVarD = $data->noiseDIF->d;
        $this->noiseVarI = $data->noiseDIF->i;
        $this->noiseVarF = $data->noiseDIF->f;

        // echo Path::join(DATABANK_PATH, "cfgstore", strtoupper($this->observatoryName), "baseline_base.json") . PHP_EOL;
        $obs_baseline_config = $this->observatory->lastBaselineConfig;
        // var_dump($obs_baseline_config);
        $this->numberIterations = $obs_baseline_config->iterations_count;
        $this->baselineTimeStep = $obs_baseline_config->baseline_time_step;
        $this->baselineTimeScale = $obs_baseline_config->baseline_time_scale;

        $this->BslMeanX = $obs_baseline_config->mean_XYZF->X;
        $this->BslMeanY = $obs_baseline_config->mean_XYZF->Y;
        $this->BslMeanZ = $obs_baseline_config->mean_XYZF->Z;
        $this->BslMeanF = $obs_baseline_config->mean_XYZF->F;

        $this->BslScalingX = $obs_baseline_config->scaling_XYZF->X;
        $this->BslScalingY = $obs_baseline_config->scaling_XYZF->Y;
        $this->BslScalingZ = $obs_baseline_config->scaling_XYZF->Z;
        $this->BslScalingF = $obs_baseline_config->scaling_XYZF->F;

        $this->imposedValue1_Date = Teno::toUTC(intval($data->imposed_values[0]->date));
        $this->imposedValue1_ValueH = $data->imposed_values[0]->value->H;
        $this->imposedValue1_ValueD = $data->imposed_values[0]->value->D;
        $this->imposedValue1_ValueZ = $data->imposed_values[0]->value->Z;

        $this->imposedValue2_Date = Teno::toUTC(intval($data->imposed_values[1]->date));
        $this->imposedValue2_ValueH = $data->imposed_values[1]->value->H;
        $this->imposedValue2_ValueD = $data->imposed_values[1]->value->D;
        $this->imposedValue2_ValueZ = $data->imposed_values[1]->value->Z;
    }

    public function Compute()
    {
        $leapSecondPath = LEAPS_FILE_PATH;
        $absFilePath = "";
        $inBlv = "whatever";
        $inWeight = "whatever";

        $baseDir = $this->getBaseDirOrCreate();
        $tempDir = Path::join($baseDir, 'Temp', "");
        mkdir($tempDir, 0777, true);
        $outputBlvFile = Path::join($baseDir, $this->observatoryName . ".blv");
        $outputWeightFile = Path::join($baseDir, "weights.out");
        $outputHDZFFile = Path::join($baseDir, "HDZF.blv");

        try {
            $absFilePath = Measurement::getFinalFilepathWithoutChecking($this->observatoryName, $this->startDate->yyyy);
        } catch (FileNotFoundException $e) {
            return false;
        }

        $rawDataListPath = Path::join($baseDir, "data_raw.lst");
        if ($this->imposedValue1_Date->teno < $this->startDate->teno) {
            File::getRawFilesBetweenDatesInFile($rawDataListPath, $this->observatoryName, $this->imposedValue1_Date, $this->endDate);
        } else {
            File::getRawFilesBetweenDatesInFile($rawDataListPath, $this->observatoryName, $this->startDate, $this->endDate);
        }


        // TODO: Handle 1st and 2nd given points: 99999.0 instead
        // TODO: get raw files list in a file
        // TODO: Create the directory for this particular baseline in DATABANK/magstore/OBS/YEAR/baseline/<id>
        // NOTE: The selected one fhould be renamed /baseline/final/
        // NOTE: Find a good spot for the /Temp directory

        $startImposedFullYear = Teno::getFullTime($this->imposedValue1_Date->yyyy);
        $startImposedFullMonth = Teno::getFullTime($this->imposedValue1_Date->mmmm);
        $startImposedFullDay = Teno::getFullTime($this->imposedValue1_Date->dddd);
        $endImposedFullYear = Teno::getFullTime($this->imposedValue2_Date->yyyy);
        $endImposedFullMonth = Teno::getFullTime($this->imposedValue2_Date->mmmm);
        $endImposedFullDay = Teno::getFullTime($this->imposedValue2_Date->dddd);

        $input = "{$this->startDate->yyyy} {$this->startDate->mmmm} {$this->startDate->dddd}
{$this->endDate->yyyy} {$this->endDate->mmmm} {$this->endDate->dddd}
{$this->numberOfObservations}
{$this->searchTimeHalfInterval}
{$this->angle1}
{$this->angle2}
{$this->angle3}
{$this->noiseIVarX}
{$this->noiseIVarY}
{$this->noiseIVarZ}
{$this->noiseIVarF}
{$this->noiseVarD}
{$this->noiseVarI}
{$this->noiseVarF}
{$this->numberIterations}
{$this->baselineTimeStep}
{$this->baselineTimeScale}
{$this->BslMeanX}
{$this->BslMeanY}
{$this->BslMeanZ}
{$this->BslMeanF}
{$this->BslScalingX}
{$this->BslScalingY}
{$this->BslScalingZ}
{$this->BslScalingF}
{$startImposedFullYear} {$startImposedFullMonth} {$startImposedFullDay}
{$this->imposedValue1_ValueH}
{$this->imposedValue1_ValueD}
{$this->imposedValue1_ValueZ}
{$endImposedFullYear} {$endImposedFullMonth} {$endImposedFullDay}
{$this->imposedValue2_ValueH}
{$this->imposedValue2_ValueD}
{$this->imposedValue2_ValueZ}
{$leapSecondPath}
{$rawDataListPath}
{$absFilePath}
{$inBlv}
{$inWeight}
{$outputBlvFile}
{$outputWeightFile}
{$outputHDZFFile}
{$tempDir}
C
";
        // echo $input;

        $this->SaveInputLocal($baseDir, $input);
        $this->SaveConfigLocal($baseDir);
        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
            1 => array("file", Path::join($tempDir, "screen.out"), "a"),  // stdout is a pipe that the child will write to
            2 => array("file", Path::join($tempDir, "screen.err"), "a"),  // stdout is a pipe that the child will write to
        );

        $process = proc_open(BSL_BINARY_PATH, $descriptorspec, $pipes);
        if (is_resource($process)) {
            fwrite($pipes[0], $input);
            fclose($pipes[0]);

            proc_close($process);
        }
        return $outputHDZFFile;
    }

    private function getBaseDirOrCreate()
    {
        $startFullMonth = Teno::getFullTime($this->startDate->mmmm);
        $startFullDay = Teno::getFullTime($this->startDate->dddd);
        $endFullMonth = Teno::getFullTime($this->endDate->mmmm);
        $endFullDay = Teno::getFullTime($this->endDate->dddd);
        $intervalDir = "{$startFullMonth}-{$startFullDay}_{$endFullMonth}-{$endFullDay}";
        $baseBlvDir = Path::join(DATABANK_PATH, "magstore", $this->observatoryName, $this->startDate->yyyy, "baseline", $intervalDir);
        $id = 0;
        if (!is_dir($baseBlvDir)) {
            $id = $this->twoDigits(1);
            mkdir($baseBlvDir, 0777, true);
        } else {
            $dirFiles = array_diff(scandir($baseBlvDir, SCANDIR_SORT_DESCENDING), array('.', '..'));
            $id = $this->twoDigits(intval($dirFiles[0]) + 1);
        }
        $endpath = Path::join($baseBlvDir, $id);
        mkdir($endpath, 0777, true);
        return $endpath;
    }

    /**
     * Get the baseline base directory for this interval
     *
     * @param string $obs
     * @param Teno $startTeno
     * @param Teno $endTeno
     * @return string
     */
    public static function getLastBaseDir($obs, $startTeno, $endTeno)
    {
        $startFullMonth = Teno::getFullTime($startTeno->mmmm);
        $startFullDay = Teno::getFullTime($startTeno->dddd);
        $endFullMonth = Teno::getFullTime($endTeno->mmmm);
        $endFullDay = Teno::getFullTime($endTeno->dddd);
        $intervalDir = "{$startFullMonth}-{$startFullDay}_{$endFullMonth}-{$endFullDay}";


        $baseBlvDir = Path::join(DATABANK_PATH, "magstore", $obs, $startTeno->yyyy, "baseline", $intervalDir);
        $dirFiles = array_diff(scandir($baseBlvDir, SCANDIR_SORT_DESCENDING), array('.', '..'));
        $id = Teno::getFullTime(intval($dirFiles[0]));
        return Path::join($baseBlvDir, $id, "");
    }

    /**
     * Get the baseline base directory for this interval
     *
     * @param string $obs
     * @param Teno $startTeno
     * @param Teno $endTeno
     * @return string
     */
    public static function getLastScreenOut($obs, $startTeno, $endTeno)
    {
        $baseDir = Baseline::getLastBaseDir($obs, $startTeno, $endTeno);
        $screenOutFilePath = Path::join($baseDir, 'Temp', "screen.out");
        if (!is_file($screenOutFilePath)) {
            throw new FileNotFoundException($screenOutFilePath);
        }
        return file_get_contents($screenOutFilePath);
    }

    /**
     * Get the list of baseline intervals for this year of the observatory
     *
     * @param string $obs
     * @param int $year
     * @return string[]
     */
    public static function getIntervalsForYear($obs, $year)
    {
        $baseBlvDir = Path::join(DATABANK_PATH, "magstore", $obs, $year, "baseline");
        if (!is_dir($baseBlvDir)) return [];
        return array_diff(scandir($baseBlvDir, SCANDIR_SORT_DESCENDING), array('.', '..'));
    }

    /**
     * Get the list of baseline intervals trys for this interval this year of the observatory
     *
     * @param string $obs
     * @param int $year
     * @param string $intervalString
     * @return string[]
     */
    public static function getIntervalsTrysForYear($obs, $year, $intervalString)
    {
        $baseBlvDir = Path::join(DATABANK_PATH, "magstore", $obs, $year, "baseline", $intervalString);
        if (!is_dir($baseBlvDir)) return [];
        return array_diff(scandir($baseBlvDir, SCANDIR_SORT_DESCENDING), array('.', '..'));
    }


    /**
     * Get the try config with the Obs config 
     *
     * @param string $obs
     * @param int $year
     * @param string $intervalString
     * @param string $try
     * @return array
     */
    public static function getTryConfigWithObsConf($obs, $year, $intervalString, $try)
    {

        $baseBlvDir = Path::join(DATABANK_PATH, "magstore", $obs, $year, "baseline", $intervalString, $try);
        if (!is_dir($baseBlvDir)) throw new Exception("Try not exists");

        $tryConfigFilePath = Path::join($baseBlvDir, "config.json");
        if (!is_file($tryConfigFilePath)) throw new Exception("Try config not exists");
        $tryConfig = json_decode(file_get_contents($tryConfigFilePath));
        $obsConfig = Observatory::CreateFromConfig($obs)->getLastConfig();
        return array("try" => $tryConfig, "obs" => $obsConfig);
    }

    public static function getTryBLVandRawDataList($obs, $year, $intervalString, $try)
    {
        $baseBlvDir = Path::join(DATABANK_PATH, "magstore", $obs, $year, "baseline", $intervalString, $try);
        if (!is_dir($baseBlvDir)) throw new Exception("Try not exists");
        $OBSBLVFilePath = Path::join($baseBlvDir, "{$obs}.blv");
        if (!is_file($OBSBLVFilePath)) throw new Exception("{$obs}.bvl file not exists");
        $RawDataFilePath = Path::join($baseBlvDir, "data_raw.lst");
        if (!is_file($RawDataFilePath)) throw new Exception("data_raw.lst file not exists");
        return [$OBSBLVFilePath, $RawDataFilePath];
    }

    public function SaveInputLocal($baseBlvDir, $input)
    {
        file_put_contents(Path::join($baseBlvDir, "input.dat"), $input);
    }

    public function SaveConfigLocal($baseBlvDir)
    {

        $config = array();

        $config["start_date"] = "{$this->startDate->yyyy} {$this->startDate->mmmm} {$this->startDate->dddd}";
        $config["end_date"] = "{$this->endDate->yyyy} {$this->endDate->mmmm} {$this->endDate->dddd}";
        $config["iterations_count"] = $this->numberIterations;
        $config["baseline_time_step"] = $this->baselineTimeStep;
        $config["baseline_time_scale"] = $this->baselineTimeScale;
        $config["mean_XYZF"] = array("X" => $this->BslMeanX, "Y" => $this->BslMeanY, "Z" => $this->BslMeanZ, "F" => $this->BslMeanF);
        $config["scaling_XYZF"] = array("X" => $this->BslScalingX, "Y" => $this->BslScalingY, "Z" => $this->BslScalingZ, "F" => $this->BslScalingF);
        $config["imposed_values"] = [
            array(
                "date" => "{$this->imposedValue1_Date->yyyy} {$this->imposedValue1_Date->mmmm} {$this->imposedValue1_Date->dddd}",
                "value" => array("H" => $this->imposedValue1_ValueH, "D" => $this->imposedValue1_ValueD, "Z" => $this->imposedValue1_ValueZ)
            ),
            array(
                "date" => "{$this->imposedValue2_Date->yyyy} {$this->imposedValue2_Date->mmmm} {$this->imposedValue2_Date->dddd}",
                "value" => array("H" => $this->imposedValue2_ValueH, "D" => $this->imposedValue2_ValueD, "Z" => $this->imposedValue2_ValueZ)
            ),
        ];

        $jsonConfig = json_encode($config, JSON_PRETTY_PRINT);
        file_put_contents(Path::join($baseBlvDir, "config.json"), $jsonConfig);
    }

    public function sendHDZF($filePath)
    {
        echo Utils::HDZF_TO_CSV(file_get_contents($filePath));
    }

    private function twoDigits($number)
    {
        if ($number < 10)
            return "0" . $number;
        return $number;
    }
}
