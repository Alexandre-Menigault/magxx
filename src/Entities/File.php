<?php
class File
{
    const TYPE_RAW = "raw";
    const TYPE_ENV = "env";
    const TYPE_LOG = "log";

    const DATABANK_UPLINK_ROOT = "/upstore";
    const DATABANK_MAGSTORE_ROOT = "/magstore";

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
        $this->date = DateTime::createFromFormat("YmdHis", date("YmdHis", $posix), new DateTimeZone("UTC"));
    }

    public function getFilepath()
    {
        return $GLOBALS["DATABANK_PATH"] . self::DATABANK_MAGSTORE_ROOT . DIRECTORY_SEPARATOR . $this->obs . DIRECTORY_SEPARATOR .
            $this->date->format("Y") . DIRECTORY_SEPARATOR . $this->type . DIRECTORY_SEPARATOR . $this->obs . $this->date->format("Ymd") . "-" . $this->type . ".csv";
    }

    public function read()
    {
        $link = $this->getFilepath();
        if (file_exists($link)) {
            $fp = fopen($link, "rb");
            $i = 0;
            while (($line = fgets($fp)) != false) {
                if ($i == 0) {
                    if ($this->type == self::TYPE_RAW) $line=trim($line) . ",Fv-Fs";
                    yield trim($line);
                    $i++;
                } else {
                    yield $this->parseLine($line);
                }
            }
            fclose($fp);
        } else yield "File not found: " . $link;
    }

    public function parseLine($line)
    {
        $r = explode(",", trim($line));
        if ($this->type == self::TYPE_RAW) {
            $x = floatval($r[2]);
            $y = floatval($r[3]);
            $z = floatval($r[4]);
            $F = floatval($r[5]);
            $Fv = sqrt($x * $x + $y * $y + $z * $z);
            return array(
                "t" => $r[0],
                "ms" => $r[1],
                "X" => $r[2],
                "Y" => $r[3],
                "Z" => $r[4],
                "F" => $r[5],
                "Fv-Fs" => "" . $Fv - $F . "",
            );
        } else if ($this->type == self::TYPE_ENV) {
            return array(
                "t" => $r[0],
                "ms" => $r[1],
                "Ts" => $r[2],
                "Te" => $r[3],
                "Ibat1" => $r[4],
                "Vbat1" => $r[5],
                "Ibat2" => $r[6],
                "Vbat2" => $r[7],
                "Iused" => $r[8],
                "Vused" => $r[9],
                "Tbat" => $r[10],
                "Lighting" => $r[11],
            );
        } else if ($this->type == self::TYPE_LOG) {
            return array(
                "t" => $r[0],
                "ms" => $r[1],
                "Source" => $r[2],
                "Level" => $r[3],
                "Message" => $r[4],
            );
        }
    }
}
