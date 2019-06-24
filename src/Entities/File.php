<?php
class File
{
    const TYPE_RAW = "raw";
    const TYPE_ENV = "env";
    const TYPE_LOG = "log";

    const DATABANK_UPLINK_ROOT = "/upstore";
    const DATABANK_MAGSTORE_ROOT ="/magstore";

    /**
     * The name of the file
     *
     * @var string
     */
    public $name;

    /**
     * The infered date from the file name
     *
     * @var \DateTime
     */
    public $date;

    /**
     * The codename of the observatory
     *
     * @var string
     */
    public $obs;

    /**
     * The type of file
     * Raw, env, or log
     *
     * @var strig
     */
    public $type;

    /**
     * If day file
     * Raw, env, or log
     *
     * @var bool
     */
    public $day;

    function __construct($obs, $type, $posix)
    {
        $this->obs = $obs;
        $this->type = $type;
        $this->date = DateTime::createFromFormat("YmdHis",date("YmdHis", $posix), new DateTimeZone("UTC"));
        // $this->name = $filename;
        // $this->day = false;
        // $this->decodeFilename($filename);
    }

    public function getFilepath() {
        return $GLOBALS["DATABANK_PATH"].self::DATABANK_MAGSTORE_ROOT."/".$this->obs."/".$this->date->format("Y")."/".$this->type."/".$this->obs.$this->date->format("Ymd")."-".$this->type.".csv";
    }

    public function read() {
        $link = $this->getFilepath();
        if(file_exists($link)) {
            $fp = fopen($link, "rb");
            fgets($fp); // Ignore firstline
            while(($line = fgets($fp)) != false) {
                yield $this->parseLine($line);
            }
            fclose($fp);
        } else yield "File not found: ".$link;
    }

    public function parseLine($line) {
        $r = explode(",", trim($line));
        return array(
          "posix" => $r[0],
          "x" => $r[2],
          "y" => $r[3],
          "z" => $r[4],
          "f" => $r[5] 
        );
    }

    public function decodeFilename(string $filename)
    {
        $parts = explode('.', $filename);
        // TODO: Ajouter les fichers de mesures absolues
        /* // ABS
        if (2 === count($parts)) {
            if ('txt' === $parts[1]) {
                if (null !== $extract = $this->extractCodeAndDate($parts[0], 'Ymd', 8)) {
                    $type = self::TYPE_ABS;
                    list($code, $date) = $extract;

                    return new File($code, $date, $type);
                }
            }
        }
        */

        // Fixed old format with dashes against dots
        // Old format : CLF120170123070000.raw.csv
        // New format : CLF120170123070000-raw.csv
        if (3 !== count($parts)) {
            // $parts = explode('-', $parts[0]);
            $patern = ["CLF1", "YYYYMMddhhmmss", ".raw.csv"];
            $error = array("Error" => $filename." does not match patern ".join("", $patern));
            echo json_encode($error);
            exit();
        }

        if (null === $extract = $this->extractCodeAndDate($parts[0])) {
            if (null === $extract = $this->extractCodeAndDate($parts[0], false)) {
                return null;
            }
        }
        $this->type = mb_strtolower($parts[1]);
        $this->obs = $extract[0];
        $this->date = $extract[1];
    }

    /**
     * Extract code and date (without knowing the code length)
     *
     * @param string $string      String
     * @param bool   $includeTime Include time
     *
     * @return null|array
     */
    private function extractCodeAndDate(string $string, bool $includeTime = true)
    {
        $dateFormat = $includeTime ? 'YmdHis' : 'Ymd';
        $dateLength = $includeTime ? 14 : 8;

        // example : CLF120170118
        if (mb_strlen($string) > $dateLength) {
            $code = mb_strtoupper(mb_strcut($string, 0, -$dateLength));
            $date = mb_strcut($string, -$dateLength);
            if ($code) {
                if (false !== $date = DateTime::createFromFormat($dateFormat, $date)) {
                    if (!$includeTime) {
                        $this->day = true;
                        $date->setTime(0, 0, 0, 0);
                    }

                    return [$code, $date];
                }
            }
        }

        return null;
    }
}
