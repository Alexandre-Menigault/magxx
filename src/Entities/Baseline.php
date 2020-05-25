<?php

require_once __DIR__ . "../Path.php";
require_once __DIR__ . "../Teno.php";
require_once __DIR__ . "../File.php";
require_once __DIR__ . "../Measureme.php";
require_once __DIR__ . "/../../config.php";

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
    public $characteristicTimescale;

    public $BslScalingX;
    public $BslScalingY;
    public $BslScalingZ;
    public $BslScalingF;
    // End configuration

    /** @var string the path to the file listused for computing the baseline */
    public $fileListPath;

    public function Compute()
    {
        $leapSecondPath = LEAPS_FILE_PATH;
        $absFilePath = "";
        $inBlv = "whatever";
        $inWeight = "whatever";

        $baseDir = $this->getBaseDirOrCreate();
        $tempDir = Path::join($baseDir, 'Temp');
        $outputBlvFile = Path::join($baseDir, $this->observatory . ".blv");
        $outputWeightFile = Path::join($baseDir, "weights.out");
        $outputHDZFFile = Path::join($baseDir, "HDZF.blv");

        try {
            $absFilePath = Measurement::getFinalFilepathWithoutChecking($this->observatory, $this->startDate->yyyy);
        } catch (FileNotFoundException $e) {
            return false;
        }

        $rawDataListPath = Path::join($baseDir, "data_raw.lst");
        File::getRawFilesBetweenDates($rawDataListPath, $this->observatory, $this->startDate, $this->endDate);


        // TODO: Handle 1st and 2nd given points: 99999.0 instead
        // TODO: get raw files list in a file
        // TODO: Create the directory for this particular baseline in DATABANK/magstore/OBS/YEAR/baseline/<id>
        // NOTE: The selected one fhould be renamed /baseline/final/
        // NOTE: Find a good spot for the /Temp directory

        $input = "{$this->startDate->yyyy} {$this->startDate->mmmm} {$this->startDate->dddd}
        {$this->endDate->yyyy} {$this->endDate->mmmm} {$this->endDate->dddd}
        {$this->numberIterations}
        {$this->searchTimeHalfInterval}
        {$this->numberIterations}
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
        {$this->characteristicTimescale}
        {$this->BslScalingX}
        {$this->BslScalingY}
        {$this->BslScalingZ}
        {$this->BslScalingF}
        2020 01 01
        99999.
        99999.
        99999.
        2020 01 01
        99999.
        99999.
        99999.
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

        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
            1 => array("file", Path::join($baseDir, "screen.out"), "a"),  // stdout is a pipe that the child will write to
            1 => array("file", Path::join($baseDir, "screen.err"), "a"),  // stdout is a pipe that the child will write to
        );

        $process = proc_open(ABS_BINARY_PATH, $descriptorspec, $pipes);
        if (is_resource($process)) {
            fwrite($pipes[0], $input);
            fclose($pipes[0]);

            proc_close($process);
        }
    }

    private function getBaseDirOrCreate()
    {
        $baseBlvDir = Path::join(DATABANK_PATH, $this->observatory, "magstore", $this->startDate->yyyy, "baseline");
        $id = 0;
        if (!is_dir($baseBlvDir)) {
            $id = 1;
        }
        $dirFiles = array_diff(scandir($baseBlvDir, SCANDIR_SORT_DESCENDING), array('.', '..'));
        $id = intval($dirFiles[0]) + 1;
        $endpath = Path::join($baseBlvDir, $id);
        mkdir($endpath, 0777, true);
        return $endpath;
    }
}
