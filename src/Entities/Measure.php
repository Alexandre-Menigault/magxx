<?php


require_once __DIR__ . "/../Path.php";
require_once __DIR__ . "/../Teno.php";
require_once __DIR__ . "/Observatory.php";
require_once __DIR__ . "/../../config.php";
require_once __DIR__ . "/File.php";

require_once __DIR__ . "/../exceptions/CannotWriteOnFileException.php";

class IndexMeasurement
{
    /** @var float $declinaiton*/
    public $declinaiton;
    /** @var float $inclinaiton*/
    public $inclinaiton;
    /** @var TimedValue[] $residues */
    public $residues;
    /** @var float[] $sighting*/
    public $sighting;

    function __construct($d, $i, $r, $s)
    {
        $this->declinaiton = $d;
        $this->inclinaiton = $i;
        $this->sighting = [];
        $this->residues = [];

        foreach ($r as $_r) {
            $_pm = new TimedValue($_r->time != "" ? $_r->time : DEFAULT_VALUE, $_r->value != "" ? $_r->value : DEFAULT_VALUE);
            array_push($this->residues, $_pm);
        }

        foreach ($s as $_s) {
            array_push($this->sighting, $_s);
        }
    }
}

class TimedValue
{
    /**
     * @var Teno $time
     */
    public $time;
    /**
     * @var float $value
     */
    public $value;

    function __construct($t, $v)
    {
        if ($t == "00:00:00" || $v == DEFAULT_VALUE) {
            $this->time = new Teno(0, 0, 0, 0, 0, 0, 0);
            $this->value = DEFAULT_VALUE;
        } else {
            $HHMMSS = explode(":", $t);
            $this->time = Teno::fromYYYYDDMMHHMMSS(2000, 1, 1, intval($HHMMSS[0]), intval($HHMMSS[1]), intval($HHMMSS[2]));
            $this->value = $v;
        }
    }
}

class Measurement
{
    /** @var int $id*/
    public $id;
    /** @var Teno $date*/
    public $date;
    /** @var string $obs*/
    public $obs;
    /** @var string $observer*/
    public $observer;
    /** @var TimedValue[] $pillarMeasurements*/
    public  $pillarMeasurements;
    /** @var IndexMeasurement[] $measurements */
    public $measurements;
    /** @var float */
    public $fp_fs;
    /** @var float */
    public $fabs_fp;
    /** @var float */
    public $azimuth_ref;
    /**
     * @param Object $data
     * @return Measurement
     */
    static function CreateMeasure($data)
    {
        $meas = new Measurement();
        $meas->observer = $data->observer;
        $meas->obs = $data->obs;

        $YYYYMMDD = explode("-", $data->date);

        $meas->date =  Teno::fromYYYYDDMMHHMMSS(intval($YYYYMMDD[0]), intval($YYYYMMDD[1]), intval($YYYYMMDD[2]), 0, 0, 0);
        $meas->fp_fs = $data->fp_fs;
        $meas->fabs_fp = $data->fabs_fp;

        $meas->azimuth_ref = $data->azimuth_ref;
        $meas->pillarMeasurements = [];
        foreach ($data->pillarMeasurements as $pm) {
            $_pm = new TimedValue($pm->time != "" ? $pm->time : "00:00:00", $pm->value != "" ? $pm->value : DEFAULT_VALUE);
            array_push($meas->pillarMeasurements, $_pm);
        }
        $meas->measurements = [];
        array_push($meas->measurements, new IndexMeasurement($data->measurementA->declination, $data->measurementA->inclination, $data->measurementA->residues, $data->measurementA->sighting));
        array_push($meas->measurements, new IndexMeasurement($data->measurementB->declination, $data->measurementB->inclination, $data->measurementB->residues, $data->measurementB->sighting));
        return $meas;
    }

    public function Test()
    {
        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
            1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
        );

        $process = proc_open(ABS_BINARY_PATH, $descriptorspec, $pipes);

        if (is_resource($process)) {
            $observer = $this->observer;
            $leapsFile = LEAPS_FILE_PATH;
            $date = str_replace("-", " ", $this->date->format("dmY"));
            $rawPath = $this->GetVariationFilePath();
            $stiv = 5;
            $id = File::countLines($this->getFilepath()) - 1;
            $azimuth_ref = number_format($this->azimuth_ref, 4);
            $az1 = number_format($this->measurements[0]->sighting[0], 4);
            $az2 = number_format($this->measurements[0]->sighting[1], 4);
            $az3 = number_format($this->measurements[0]->sighting[2], 4);
            $az4 = number_format($this->measurements[0]->sighting[3], 4);
            $fp_fs = number_format($this->fp_fs, 2);
            $fabs_fp = number_format($this->fabs_fp, 2);

            $startD = number_format($this->measurements[0]->declinaiton, 4);
            $d1_time = $this->measurements[0]->residues[0]->time->format("His");
            $d1_val = number_format($this->measurements[0]->residues[0]->value, 4);
            $d2_time = $this->measurements[0]->residues[1]->time->format("His");
            $d2_val = number_format($this->measurements[0]->residues[1]->value, 4);
            $d3_time = $this->measurements[0]->residues[2]->time->format("His");
            $d3_val = number_format($this->measurements[0]->residues[2]->value, 4);
            $d4_time = $this->measurements[0]->residues[3]->time->format("His");
            $d4_val = number_format($this->measurements[0]->residues[3]->value, 4);

            $startI = number_format($this->measurements[0]->declinaiton, 4);
            $i1_time = $this->measurements[0]->residues[4]->time->format("His");
            $i1_val = number_format($this->measurements[0]->residues[4]->value, 4);
            $i2_time = $this->measurements[0]->residues[5]->time->format("His");
            $i2_val = number_format($this->measurements[0]->residues[5]->value, 4);
            $i3_time = $this->measurements[0]->residues[6]->time->format("His");
            $i3_val = number_format($this->measurements[0]->residues[6]->value, 4);
            $i4_time = $this->measurements[0]->residues[7]->time->format("His");
            $i4_val = number_format($this->measurements[0]->residues[7]->value, 4);

            $input = "{$observer}
{$leapsFile}
{$date}
{$id}
{$rawPath}
{$stiv}
{$azimuth_ref}
{$az1}
{$az2}
{$az3}
{$az4}
{$fp_fs}
{$fabs_fp}
{$startD}
D1
{$d1_time}
{$d1_val}
D2
{$d2_time}
{$d2_val}
D3
{$d3_time}
{$d3_val}
D4
{$d4_time}
{$d4_val}
{$startI}
I1
{$i1_time}
{$i1_val}
I2
{$i2_time}
{$i2_val}
I3
{$i3_time}
{$i3_val}
I4
{$i4_time}
{$i4_val}

";
            // Debug: send the piped input
            // echo $input;

            fwrite($pipes[0], $input);
            fclose($pipes[0]);

            $res = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            proc_close($process);

            echo $res;
        }
    }

    private function GetVariationFilePath()
    {
        $filename = strtoupper($this->obs) . "" . $this->date->teno . "-raw.csv";
        return Path::join(DATABANK_PATH, "magstore", $this->obs, $this->date->yyyy, "raw", $filename);
    }

    public function Save()
    {
        $filepath = $this->getFilepath();
        if (!file_exists($filepath)) {

            // If file doesn't exists, create it and put headers
            $headers = [
                "NB1", "Observer", "Date", "Fp-Fs", "Fabs-Fp",
                "Heure FP1", "FP1", "Heure FP2", "FP2", "Heure FP3", "FP3", "Heure FP4", "FP4", "Heure FP5", "FP5", "Heure FP6", "FP6",
                "Azimuth_ref", "V1", "V2", "V3", "V4", "V5", "V6",
                "PDD1", "Hres1", "res1", "Hres2", "res2", "Hres3", "res3", "Hres4", "res4",
                "PDI1", "Hres5", "res5", "Hres6", "res6", "Hres7", "res7", "Hres8", "res8",
                "PDD2", "Hres9", "res9", "Hres10", "res10", "Hres11", "res11", "Hres12", "res12",
                "PDI2", "Hres13", "res13", "Hres14", "res14", "Hres15", "res15", "Hres16", "res16",
            ];
            // If cannot write on file, throw exception
            if (!file_put_contents($filepath, join(",", $headers) . PHP_EOL)) {
                throw CannotWriteOnFileException($filepath, "Cannot write headers of new measure");
            }
        }
        // Id is number of lines in the file - 1 (remove headers line)
        $this->id = File::countLines($filepath) - 1;
        // Add id, obs, observer and date
        $parts = [$this->id, $this->observer, $this->date->format('Ymd'), $this->fp_fs, $this->fabs_fp];


        // Add pillar measurement time and values
        foreach ($this->pillarMeasurements as $pm) {
            $time = $pm->time != "" ? $pm->time->format("His") : DEFAULT_VALUE;
            $value = $pm->value != "" ? $pm->value : DEFAULT_VALUE;
            array_push($parts, $time, $value);
        }
        // Add sighting values
        array_push(
            $parts,
            $this->measurements[0]->sighting[0],
            $this->measurements[0]->sighting[1],
            $this->measurements[0]->sighting[2],
            $this->measurements[0]->sighting[3],
            $this->measurements[1]->sighting[2] != "" ? $this->measurements[1]->sighting[2] : DEFAULT_VALUE,
            $this->measurements[1]->sighting[3] != "" ? $this->measurements[1]->sighting[3] : DEFAULT_VALUE,
        );
        // Add residues time and values of each measure
        foreach ($this->measurements as $meas) {
            array_push($parts, $meas->declinaiton);
            for ($i = 0; $i < 4; $i++) {
                $time = $meas->residues[$i]->time != "" ? $meas->residues[$i]->time->format("His") : DEFAULT_VALUE;
                $value = $meas->residues[$i]->value != "" ? $meas->residues[$i]->value : DEFAULT_VALUE;
                array_push($parts, $time, $value);
            }
            array_push($parts, $meas->inclinaiton);
            for ($i = 4; $i < 8; $i++) {
                $time = $meas->residues[$i]->time != "" ? $meas->residues[$i]->time->format("His") : DEFAULT_VALUE;
                $value = $meas->residues[$i]->value != "" ? $meas->residues[$i]->value : DEFAULT_VALUE;
                array_push($parts, $time, $value);
            }
        }
        // Write to file and add a new line char

        if (!file_put_contents($filepath, join(',', $parts) . PHP_EOL, FILE_APPEND)) {
            throw CannotWriteOnFileException($filepath, "Cannot write content of new measure");
        }

        $this->Test();
    }

    public function getFilepath()
    {
        return Path::join(DATABANK_PATH, File::DATABANK_MAGSTORE_ROOT, $this->obs, $this->date->yyyy, $this->obs . $this->date->yyyy . '.abr');
    }
}
