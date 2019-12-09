<?php

/**
 * TENO comverter from and to YYYY MM DD HH MM SS
 * @property int $teno
 * @property int $yyyy
 * @property int $mmmm
 * @property int $dddd
 * @property int $hh
 * @property int $mm
 * @property int $ss
 */
class Teno
{
    public static $FORMATS = array("YmdHis", "Ymd");

    public static $DAYS_SECONDS = 86400;
    public static $HOURS_SECONDS = 3600;
    public static $MINUTES_SECONDS = 60;

    public $teno = 0;
    public $yyyy = 2000;
    public $mmmm = 1;
    public $dddd = 1;
    public $hh = 0;
    public $mm = 0;
    public $ss = 0;

    /**
     * Constructs a new TENO object
     *
     * @param int $teno
     * @param int $yyyy
     * @param int $mmmm
     * @param int $dddd
     * @param int $hh
     * @param int $mm
     * @param int $ss
     * @return Teno
     */
    function __construct($teno, $yyyy, $mmmm, $dddd, $hh, $mm, $ss)
    {
        $this->teno = $teno;
        $this->yyyy = $yyyy;
        $this->mmmm = $mmmm;
        $this->dddd = $dddd;
        $this->hh = $hh;
        $this->mm = $mm;
        $this->ss = $ss;
    }

    public function format($format = "YmdHis")
    {
        if (in_array($format, Teno::$FORMATS)) {
            if ($format == "YmdHis")
                return $this->yyyy . $this->mmmm . $this->dddd . $this->hh . $this->mm . $this->ss;
            if ($format == "Ymd")
                return $this->yyyy . $this->mmmm . $this->dddd;
        }
        return "";
    }

    public function __toString()
    {

        return $this->teno . "";
    }

    public function isAfterNow()
    {
        $now  = new DateTime("now", new DateTimeZone('UTC'));
        $mmmm = Teno::getFullTime($this->mmmm);
        $dddd = Teno::getFullTime($this->dddd);
        $hh =  Teno::getFullTime($this->hh);
        $mm = Teno::getFullTime($this->mm);
        $ss = Teno::getFullTime($this->ss);
        $d = DateTime::createFromFormat("YmdHis", $this->yyyy . $mmmm . $dddd  . $hh . $mm . $ss, new DateTimeZone("UTC"));
        return $d == false || $now->getTimestamp() > $d->getTimestamp();
    }

    /**
     * Get the 2 digit value of a time (month, day, hour, minute, second)
     *
     * @param int $time
     * @return string
     */
    public static function getFullTime($time)
    {
        if (!is_numeric($time)) return "-1";
        return $time < 10 ? "0" . $time : $time;
    }

    /** 
     * The list of leap seconds in Teno time
     * 
     *  array["leap"]    [int] - The Teno value when a leap is addded  
     * 
     *       ["number"]  [int] - The total of added leaps at that time
     * 
     *       ["count]    [int] - The amount of leap added, can be negative
     * @property array $LEAP_SECONDS
     *      
     */
    private static  $LEAP_SECONDS = [
        array("leap" => 536544004, "number" => 5, "count" => 1),
        array("leap" => 489024003, "number" => 4, "count" => 1),
        array("leap" => 394416002, "number" => 3, "count" => 1),
        array("leap" => 284083201, "number" => 2, "count" => 1),
        array("leap" => 189388800, "number" => 1, "count" => 1),
        array("leap" => -1, "number" => 0, "count" => 0),
    ];

    /**
     * The list of Febuary 29 in Teno Time
     * 
     *  array["start"]    [int] - The Teno value when a Febuary 29 starts  
     * 
     *       ["end"]    [int] - The Teno value when a Febuary 29 ends (always start + 86400)  
     * @property array $LEAP_SECONDS
     *      
     */
    private static $FEBUARY_29s = [
        array("start" => 5097600, "end" => 5097600 + 86400),
        array("start" => 13132800, "end" => 13132800 + 86400),
        array("start" => 254558400, "end" => 254558400 + 86400),
        array("start" => 383788800, "end" => 383788800 + 86400),
        array("start" => 510019200, "end" => 510019200 + 86400),
        array("start" => 636249600, "end" => 636249600 + 86400),
    ];


    /**
     * Is the year a leap year
     *
     * @param int $year
     * @return boolean
     */
    private static function isLeapYear($year)
    {
        return (($year % 4 == 0 && $year % 100 != 0)) || ($year % 400 == 0);
    }


    /**
     * Define the number of days of a month, including 
     *
     * @param int $year
     * @return int[]
     */
    private static function daysInMonth($year)
    {
        return [
            31, // Jan
            Teno::isLeapYear($year) ? 29 : 28, // Feb
            31, // Mar
            30, // Apr
            31, // Mai
            30, // Jun
            31, // Jui
            31, // Aug
            30, // Sep
            31, // Oct
            30, // Nov
            31, // Dec
        ];
    }


    /**
     * Get the month number and days of month on a specific year
     *
     * @param [type] $year
     * @param [type] $daysCounter
     * @return array("mmmm" => int, "dddd" => int)
     */
    private static function getMonthNumberAndDayOfMonth($year, $daysCounter)
    {
        $remainingDays = $daysCounter;
        $monthCounter = 1;
        $daysInMonth = Teno::daysInMonth($year);

        for ($i = 0; $i < count($daysInMonth); $i++) {
            $daysOfMonth = $daysInMonth[$i];
            if ($remainingDays > $daysOfMonth) {
                $monthCounter++;
                $remainingDays -= $daysOfMonth;
            } else
                break;
        }
        return array("mmmm" => $monthCounter, "dddd" => $remainingDays + 1);
    }


    /**
     * Get the amount of leap seconds at the specified Teno time.
     * Starting from January 1st 2000
     *
     * @param int $teno
     * @return int
     */
    private static function getNumberOfLeaps($teno)
    {
        foreach (Teno::$LEAP_SECONDS as  $leap) {
            if ($leap["leap"] <= $teno) return $leap["number"];
        }
        return 0;
    }

    /**
     * Get the amount of leap years at the specified Teno time.
     * Starting from January 1st 2000
     * 
     * @param int $teno
     * @return int
     */
    private static function getNumberOfLeapYears($teno)
    {
        $count = 0;
        foreach (Teno::$FEBUARY_29s as $leapYear) {
            if ($teno >= $leapYear["end"]) $count++;
        }
        return $count;
    }

    /**
     * Count the number of days of year until the specified date. 
     * Febuary 29 included 
     *
     * @param int $dddd
     * @param int $mmmm
     * @param int $yyyy
     * @return int
     */
    private static function countDaysUntil($dddd, $mmmm, $yyyy)
    {
        $total = 0;
        for ($i = 2000; $i <= $yyyy; $i++) {
            $dim = Teno::daysInMonth($i);
            for ($j = 0; $j <= ($i == $yyyy ? $mmmm - 2 : 11); $j++) {
                $total += $dim[$j];
            }
        }
        return $total + $dddd - 1;
    }

    /**
     * Is the Teno time is exactly a leap second
     *
     * @param int $teno
     * @return boolean
     */
    private static function isLeap($teno)
    {
        foreach (Teno::$LEAP_SECONDS as $leap) {
            if ($leap["leap"] == $teno) return true;
        }
        return false;
    }

    /**
     * Count the number of leap seconds until the specified Teno time
     *
     * @param int $teno
     * @return int
     */
    private static function leapCount($teno)
    {
        foreach (Teno::$LEAP_SECONDS as $leap) {
            if ($leap["leap"] == $teno) return $leap["count"];
        }
        return 0;
    }


    /**
     * Converts the specified Teno time to a Teno YYYY MM DD HH MM SS Object
     * 
     * @param int $teno
     * @return Teno
     */
    public static function toUTC($teno)
    {
        if ($teno < 0) return new Teno(0, 2000, 1, 1, 0, 0, 0);

        // $nld = Teno::getNumberOfLeaps($teno);
        $nld = 0;
        $s = ($teno - $nld) % 86400;

        $hh = floor($s / 3600);
        $mm = floor(($s - 3600 * $hh) / 60);
        $ss = $s - 60 * $mm - 3600 * $hh;

        $nd = floor(($teno - $nld) / 86400);
        $nb = Teno::getNumberOfLeapYears($teno);
        $ndb = $nd - $nb;

        $doyb = $ndb % 365;
        $yyyy = 2000 + ($ndb - $doyb) / 365;
        $_md = Teno::getMonthNumberAndDayOfMonth($yyyy, $doyb);
        $mmmm = $_md["mmmm"];
        $dddd = $_md["dddd"];
        if (Teno::isLeapYear($yyyy) && $mmmm >= 3) $dddd++;

        if (Teno::isLeap($teno)) {

            $leapCount = Teno::leapCount($teno);
            if ($leapCount === 1) $ss = 60;
            else $ss = 59;
        }

        return new Teno($teno, $yyyy, $mmmm, $dddd, $hh, $mm, $ss);
    }

    /**
     * Get Teno object from YYYY MM DD HH MM SS
     * @param int $yyyy
     * @param int $mmmm
     * @param int $dddd
     * @param int $hh
     * @param int $mm
     * @param int $ss
     * @return Teno
     */
    public static function fromYYYYDDMMHHMMSS($yyyy, $mmmm, $dddd, $hh, $mm, $ss)
    {
        $total = $ss;
        $total += $mm * 60;
        $total += $hh * 3600;
        $total += Teno::countDaysUntil($dddd, $mmmm, $yyyy) * 86400;
        // $total += Teno::getNumberOfLeaps($total);
        return new Teno($total, $yyyy, $mmmm, $dddd, $hh, $mm, $ss);
    }

    /**
     * Get Teno object from Timestamp (including milliseconds)
     * @param int $timestamp
     * @return Teno
     */
    public static function fromTimestamp($timestamp)
    {
        $date = (new DateTime("@" . $timestamp))->setTimezone(new DateTimeZone("UTC"));
        return Teno::fromYYYYDDMMHHMMSS(
            intval($date->format("Y")),
            intval($date->format("m")),
            intval($date->format("d")),
            intval($date->format("H")),
            intval($date->format("i")),
            intval($date->format("s"))
        );
    }
}
