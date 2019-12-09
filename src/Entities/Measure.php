<?php


require_once __DIR__ . "/../Path.php";
require_once __DIR__ . "/../Teno.php";
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
            $_pm = new TimedValue($_r->time, $_r->value);
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
        $HHMMSS = explode(":", $t);
        $this->time = Teno::fromYYYYDDMMHHMMSS(2000, 1, 1, intval($HHMMSS[0]), intval($HHMMSS[1]), intval($HHMMSS[2]));
        $this->value = $v;
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

    /**
     * @param Object $data
     * @return Measurement
     */
    static function CreateMeasure($data)
    {
        $meas = new Measurement();
        // TODO: validate data
        $meas->observer = $data->observer;
        $meas->obs = $data->obs;

        $YYYYMMDD = explode("-", $data->date);

        $meas->date =  Teno::fromYYYYDDMMHHMMSS(intval($YYYYMMDD[0]), intval($YYYYMMDD[1]), intval($YYYYMMDD[2]), 0, 0, 0);

        $meas->pillarMeasurements = [];
        foreach ($data->pillarMeasurements as $pm) {
            $_pm = new TimedValue($pm->time, $pm->value);
            array_push($meas->pillarMeasurements, $_pm);
        }
        $meas->measurements = [];
        array_push($meas->measurements, new IndexMeasurement($data->measurementA->declination, $data->measurementA->inclination, $data->measurementA->residues, $data->measurementA->sighting));
        array_push($meas->measurements, new IndexMeasurement($data->measurementB->declination, $data->measurementB->inclination, $data->measurementB->residues, $data->measurementB->sighting));

        $meas->Save();

        return $meas;
    }

    public function Save()
    {
        $filepath = $this->getFilepath();
        if (!file_exists($filepath)) {

            // If file doesn't exists, create it and put headers
            $headers = [
                "NB1", "Observer", "Date",
                "Heure FP1", "FP1", "Heure FP2", "FP2", "Heure FP3", "FP3", "Heure FP4", "FP4", "Heure FP5", "FP5", "Heure FP6", "FP6",
                "V1", "V2", "V3", "V4", "V5", "V6",
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


        // TODO: change DATE to YYYY-MM-SS
        $parts = [$this->id, $this->observer, $this->date];
        // $parts = [$this->id, $this->observer, join("-", explode("/", $this->date))];



        // TODO: change TIME to HH:MM:SS on each time 

        // Add pillar measurement time and values
        foreach ($this->pillarMeasurements as $pm) {
            $time = $pm->time != "" ? $pm->time : $GLOBALS["DEFAULT_VALUE"];
            $value = $pm->value != "" ? $pm->value : $GLOBALS["DEFAULT_VALUE"];
            array_push($parts, $time, $value);
        }
        // Add sighting values
        array_push(
            $parts,
            $this->measurements[0]->sighting[0],
            $this->measurements[0]->sighting[1],
            $this->measurements[0]->sighting[2],
            $this->measurements[0]->sighting[3],
            $this->measurements[1]->sighting[2] != "" ? $this->measurements[1]->sighting[2] : $GLOBALS["DEFAULT_VALUE"],
            $this->measurements[1]->sighting[3] != "" ? $this->measurements[1]->sighting[3] : $GLOBALS["DEFAULT_VALUE"],
        );
        // Add residues time and values of each measure
        foreach ($this->measurements as $meas) {
            array_push($parts, $meas->declinaiton);
            for ($i = 0; $i < 4; $i++) {
                $time = $meas->residues[$i]->time != "" ? $meas->residues[$i]->time : $GLOBALS["DEFAULT_VALUE"];
                $value = $meas->residues[$i]->value != "" ? $meas->residues[$i]->value : $GLOBALS["DEFAULT_VALUE"];
                array_push($parts, $time, $value);
            }
            array_push($parts, $meas->inclinaiton);
            for ($i = 4; $i < 8; $i++) {
                $time = $meas->residues[$i]->time != "" ? $meas->residues[$i]->time : $GLOBALS["DEFAULT_VALUE"];
                $value = $meas->residues[$i]->value != "" ? $meas->residues[$i]->value : $GLOBALS["DEFAULT_VALUE"];
                array_push($parts, $time, $value);
            }
        }
        // Write to file and add a new line char

        if (!file_put_contents($filepath, join(',', $parts) . PHP_EOL, FILE_APPEND)) {
            throw CannotWriteOnFileException($filepath, "Cannot write content of new measure");
        }
    }

    public function getFilepath()
    {
        return Path::join($GLOBALS["DATABANK_PATH"], File::DATABANK_MAGSTORE_ROOT, $this->obs, $this->date->yyyy, $this->obs . $this->date->yyyy . '.abr');
    }
}
