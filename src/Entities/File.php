<?php
class File
{
    const TYPE_RAW = "raw";
    const TYPE_ENV = "env";
    const TYPE_LOG = "log";

    const INTERVAL_DAY = "1d";
    const INTERVAL_2HOURS = "2h";

    const DATABANK_UPLINK_ROOT = DIRECTORY_SEPARATOR . "upstore";
    const DATABANK_MAGSTORE_ROOT = DIRECTORY_SEPARATOR . "magstore";

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
     * The date interval data to get between
     *
     * @var \DateTime
     */
    public $intervalDate;

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

    function __construct($obs, $type, $posix, $interval = self::INTERVAL_DAY)
    {
        $this->obs = $obs;
        $this->type = $type;
        $this->date = DateTime::createFromFormat("YmdHis", date("YmdHis", $posix), new DateTimeZone("UTC"));
        $this->intervalDate = clone $this->date;
        if ($interval == self::INTERVAL_DAY)
            $this->intervalDate->add(new DateInterval("P1D"));
        else if ($interval == self::INTERVAL_2HOURS)
            $this->intervalDate->add(new DateInterval("PT2H"));
    }

    public function getFilepath($upload = false)
    {
        return $GLOBALS["DATABANK_PATH"] . ($upload == false ? self::DATABANK_MAGSTORE_ROOT : self::DATABANK_UPLINK_ROOT) .
            DIRECTORY_SEPARATOR . $this->obs . DIRECTORY_SEPARATOR . ($upload == false ? $this->date->format("Y") : ($this->date->format("Y") . DIRECTORY_SEPARATOR . $this->date->format("m") . DIRECTORY_SEPARATOR . $this->date->format("d"))) . DIRECTORY_SEPARATOR .
            $this->type . DIRECTORY_SEPARATOR .
            $this->obs . ($upload == false ? $this->date->format("Ymd") : $this->date->format("YmdHis")) . ($upload == false ? "-" : ".") . $this->type . ".csv";
    }

    public function read()
    {
        $link = $this->getFilepath();
        if (file_exists($link)) {
            $fp = fopen($link, "rb");
            $i = 0;
            $end = false;
            while (!$end && ($line = fgets($fp)) != false) {
                if ($i == 0) {
                    if ($this->type == self::TYPE_RAW) $line = trim($line) . ",Fv-Fs";
                    yield trim($line);
                    $i++;
                } else {
                    $parsed = $this->parseLine($line);
                    $isBetweenInterval = $this->isLineBetweenInterval($parsed);
                    if ($isBetweenInterval == 1) {
                        $end = true;
                    } else if ($isBetweenInterval == 0)
                        yield $parsed;
                }
            }
            fclose($fp);
        } else yield "File not found: " . $link;
    }

    private function isLineBetweenInterval($line)
    {
        $d = DateTime::createFromFormat("YmdHis", date("YmdHis", $line["t"]), new DateTimeZone("UTC"));
        $offset = (new DateTime())->getTimezone()->getOffset($d);
        $d->sub(new DateInterval("PT" . $offset . "S"));
        if ($this->intervalDate->getTimestamp() < $d->getTimestamp()) return 1;
        else if ($this->date->getTimestamp() <= $d->getTimestamp() && $this->intervalDate->getTimestamp() >= $d->getTimestamp())
            return 0;
        return -1;
        // return ($this->date->getTimestamp() <= $d->getTimestamp() && $this->intervalDate->getTimestamp() >= $d->getTimestamp());
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
                "Lightning" => $r[11],
            );
        } else if ($this->type == self::TYPE_LOG) {
            return array(
                "t" => $r[0],
                "ms" => $r[1],
                "Source" => $r[2],
                "Level" => $r[3],
                "Message" => join(",", array_slice($r, 4)),
            );
        }
    }
}
