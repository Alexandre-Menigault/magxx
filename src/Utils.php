<?php

require_once __DIR__ . "/Teno.php";

class Utils
{

    public static function HDZF_TO_CSV($hdzfContent)
    {
        // $hdzfContent = preg_replace('/\s+/', ' ', $hdzfContent)
        $resjson = array();
        foreach (explode(PHP_EOL, $hdzfContent) as $hdzfLine) {
            $sanitizedLine = preg_replace('/\s+/', ' ', trim($hdzfLine));
            if ($hdzfLine == "") continue;
            $splited_line = explode(' ', $sanitizedLine);

            $h = floatval($splited_line[2]);
            $d = floatval($splited_line[3]);
            $z = floatval($splited_line[4]);
            $F = floatval($splited_line[5]);
            $Fv = 0;
            if ($F >= 99999 || $h >= 99999 || $h >= 99999 || $z >= 99999) $Fv = 99999;
            else $Fv = sqrt($h * $h + $d * $d + $z * $z);

            $dateTimeHumanReadable = $splited_line[0] . " " . $splited_line[1];
            array_push($resjson, array(
                "time_human_readable" => $dateTimeHumanReadable,
                "teno" => Teno::fromHumanReadable($dateTimeHumanReadable),
                "H" => $splited_line[2],
                "D" => $splited_line[3],
                "Z" => $splited_line[4],
                "F" => $splited_line[5],
                "dF" => $Fv >= 99999 ? "99999" : "" . ($F - $Fv) . "",
                "mjd" => $splited_line[6],
            ));
        }
        return json_encode($resjson);
    }
}
