<?php

include_once __DIR__ . "/../exceptions/FileNotFoundException.php";
include_once __DIR__ . "/../Teno.php";
include_once __DIR__ . "/../Path.php";

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
     * @var \Teno
     */
    public $date;

    /**
     * The date interval data to get between
     *
     * @var string
     */
    public $interval;

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

    // function __construct($obs, $type, $posix, $interval = self::INTERVAL_DAY)
    // {
    //     $this->obs = $obs;
    //     $this->type = $type;
    //     $this->date = DateTime::createFromFormat("YmdHis", date("YmdHis", $posix), new DateTimeZone("UTC"));
    //     $this->intervalDate = clone $this->date;
    //     if ($interval == self::INTERVAL_DAY)
    //         $this->intervalDate->add(new DateInterval("P1D"));
    //     else if ($interval == self::INTERVAL_2HOURS)
    //         $this->intervalDate->add(new DateInterval("PT2H"));

    //     $filepath = $this->getFilepath();
    //     if (!is_file($filepath)) {
    //         throw new FileNotFoundException($filepath);
    //     }
    // }

    function __construct($obs, $type, $teno, $interval = self::INTERVAL_DAY)
    {
        if (!$obs || !$type) {
            throw new Exception("Malformed");
        }
        $this->obs = $obs;
        $this->type = $type;
        $this->interval = $interval;
        $this->date = Teno::toUTC($teno);
    }


    /**
     * Compute the filepath for the upcpming uploaded files
     *
     * @return string
     */
    public function computeFilePath()
    {
        return Path::join(
            DATABANK_PATH . self::DATABANK_UPLINK_ROOT,
            $this->obs,
            $this->date->yyyy,
            Teno::getFullTime($this->date->mmmm),
            Teno::getFullTime($this->date->dddd),
            $this->type,
            $this->obs . "-" . $this->date->fixedTeno() . "." . $this->type . ".csv"
        );
    }


    /**
     * Get the filepath of the concat files
     *
     * @return string
     */
    public function getFilepath()
    {
        $teno = Teno::fromYYYYDDMMHHMMSS($this->date->yyyy, $this->date->mmmm, $this->date->dddd, 0, 0, 0);
        return Path::join(
            DATABANK_PATH . self::DATABANK_MAGSTORE_ROOT,
            $this->obs,
            $this->date->yyyy,
            $this->type,
            // TODO: change format to OBSX-teno-type.csv
            $this->obs . "-" . $teno->fixedTeno() . "-" . $this->type . ".csv"
        );
    }

    /**
     * Reads files and yields parsed lines
     *
     * @return Generator|string
     */
    public function read()
    {
        $link = $this->getFilepath();
        if (file_exists($link)) {
            $fp = fopen($link, "rb");
            $i = 0;
            $end = false;
            while (!$end && ($line = fgets($fp)) != false) {
                if ($i == 0) {
                    if ($this->type == self::TYPE_RAW) $line = trim($line) . ",Fs-Fv";
                    yield trim($line);
                    $i++;
                } else {
                    $parsed = $this->parseLine($line);

                    $isBetweenInterval = 0;
                    // $isBetweenInterval = $this->isLineBetweenInterval($parsed);
                    if ($isBetweenInterval == 1) {
                        $end = true;
                    } else if ($isBetweenInterval == 0)
                        yield $parsed;
                }
            }
            fclose($fp);
        } else yield "File not found: " . $link;
    }


    /**
     * Get if a data line is in a date interval
     *
     * @param string $line - Data csv format
     * @return int Returns 0 if in interval. Returns 1 if data line is after interval. Returns 1 if data time is before interval
     */
    private function isLineBetweenInterval($line)
    {
        $end_date = $this->date->teno;
        $moment = mb_strcut($this->interval, -1, 1);
        $amount = intval(mb_strcut($this->interval, 0, strlen($this->interval) - 1));
        switch ($moment) {
            case "d":
                $end_date += $amount * Teno::$DAYS_SECONDS;
                break;
            case "h":
                $end_date += $amount * Teno::$HOURS_SECONDS;
                break;
            case "m":
                $end_date += $amount * Teno::$MINUTES_SECONDS;
                break;
        }
        $d = intval($line["t"]);
        if ($end_date < $d) return 1;
        else if ($this->date->teno <= $d && $end_date >= $d)
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
            if ($F >= 99999 || $x >= 99999 || $y >= 99999 || $z >= 99999) $Fv = 99999;
            else $Fv = sqrt($x * $x + $y * $y + $z * $z);
            return array(
                "t" => $r[0],
                "ms" => $r[1],
                "X" => $r[2],
                "Y" => $r[3],
                "Z" => $r[4],
                "F" => $r[5],
                "Fs-Fv" => "" . $Fv >= 99999 ? 99999 : ($F - $Fv) . "",
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

    /**
     * Count number of lines of a specific file
     *
     * @param string $path
     * @return int
     */
    public static function countLines($path)
    {
        $linecount = 0;
        $handle = fopen($path, 'r');
        if (!$handle) return 0;
        while (!feof($handle)) {
            fgets($handle);
            $linecount++;
        }
        fclose($handle);
        return $linecount;
    }

    /**
     * - Get the fully qualified filepath of every 
     * - FileFormat = [ObsCode][YYYY][MM][DD]
     *
     * @param string $obsCode
     * @param DateTime $date1
     * @param DateTime $date2
     * @return string[] Array of seconds data file path 
     */
    public static function getSecondsFilesPathBetweenTwoDates($obsCode, $date1, $date2)
    {
        $base = Path::join(DATABANK_PATH, 'magstore/', $obsCode);
        $begin = new DateTime($date1);
        $end = new DateTime($date2);

        for ($i = $begin; $i < $end; $i->modify("1 day")) {
            $filename = $obsCode . $i->format("Ymd"); // = FileFormat
            // TODO: change format to OBSX-teno-type.csv
            $file = Path::join($base, $i->format('Y'), "raw", $filename . "-raw.csv");
            if (is_file($file)) {
                yield $file;
            }
        }
    }

    /**
     * Get the absolute path for every raw file of a observatory between two dates
     * File format -> [ObsCode]-[teno 10 digits]-raw.csv
     *
     * @param string $obsCode
     * @param Teno $tenoStart
     * @param Teno $tenoEnd
     * @return Generator|string[] 
     */
    public static function getRawFilesBetweenDates($obsCode, $tenoStart, $tenoEnd)
    {
        $base = Path::join(DATABANK_PATH, 'magstore/', $obsCode);
        /** @var Teno $current */
        for ($current = $tenoStart->teno; $current <= $tenoEnd->teno; $current = Teno::toUTC($current->teno + 86400)) {
            $filename = "${$obsCode}-{$current->teno}-raw.csv";
            $file = Path::join($base, $current->yyyy, 'raw', $filename);
            if (is_file($file)) {
                yield $file;
            }
        }
    }

    public static function getRawFilesBetweenDatesInFile($inFilePath, $obsCode, $tenoStart, $tenoEnd)
    {
        $output = "";
        $base = Path::join(DATABANK_PATH, 'magstore/', $obsCode);
        /** @var Teno $current */
        for ($current = $tenoStart; $current->teno <= $tenoEnd->teno; $current = Teno::toUTC($current->teno + 86400)) {
            $filename = "{$obsCode}-{$current->fixedTeno()}-raw.csv";
            $file = Path::join($base, $current->yyyy, 'raw', $filename);
            if (is_file($file)) {
                $output .= $file . PHP_EOL;
            }
        }

        $file = file_put_contents($inFilePath, $output);
    }

    /**
     * Return an array of sampled seconds data
     * Each value in array is mean of all points within samplerate range
     *
     * @param string $filePath
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param DateInterval $sampleRate
     * @return number[] mean of all points within samplerate range
     */
    public static function sampleSecondsData($filePath, $startDate, $endDate, $sampleRate)
    {
        $sum = array("t" => $startDate->getTimestamp(), "ms" => 0, "X" => 0, "Y" => 0, "Z" => 0, "F" => 0);
        // $sum = array("t" => $startDate, "ms" => 0, "X" => 0, "Y" => 0, "Z" => 0, "F" => 0);
        $count = 0;
        $isFirst = true;
        if (!is_file($filePath)) {
            throw new FileNotFoundException($filePath);
        }

        $handle = fopen($filePath, 'r');
        fgets($handle);
        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line == "") break;
            $r = explode(",", trim($line));
            $currentDate = DateTime::createFromFormat("YmdHis", date("YmdHis", intval($r[0])));
            if ($endDate < $currentDate) break; // If the current date is after end date, stop;
            $x = floatval($r[2]);
            $y = floatval($r[3]);
            $z = floatval($r[4]);
            $F = floatval($r[5]);

            if ($isFirst) {
                $blobDate = clone $currentDate;
                $nextDate = $blobDate->add(($sampleRate));
                $sum = array("t" => $currentDate->getTimestamp(), "ms" => 0, "X" => $x, "Y" => $y, "Z" => $z, "F" => $F);
                $count = 1;
                $isFirst = false;
            }

            if ($currentDate >= $nextDate) { // if date is after nextdate
                $currentDate = $currentDate;
                $nextDate = $currentDate->add($sampleRate);

                yield array("t" => $sum["t"], "ms" => 0, "X" => $sum["X"] / $count, "Y" => $sum["Y"] / $count, "Z" => $sum["Z"] / $count, "F" => $sum["F"] / $count);
                $sum = array("t" => $currentDate->getTimestamp(), "ms" => 0, "X" => $x, "Y" => $y, "Z" => $z, "F" => $F);
                $count = 1;
            } else {
                $sum["X"] += $x;
                $sum["Y"] += $y;
                $sum["Z"] += $z;
                $sum["F"] += $F;
                $count++;
            }
        }
        $countSum = count($sum);
        if ($countSum > 0)
            yield array("t" => $sum["t"], "ms" => 0, "X" => $sum["X"] / $countSum, "Y" => $sum["Y"] / $countSum, "Z" => $sum["Z"] / $countSum, "F" => $sum["F"] / $countSum);
    }
}
