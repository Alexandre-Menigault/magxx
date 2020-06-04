<?php
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
            array_push($resjson, array(
                "time_human_readable" => $splited_line[0] . " " . $splited_line[1],
                "H" => $splited_line[2],
                "D" => $splited_line[3],
                "Z" => $splited_line[4],
                "F" => $splited_line[5],
                "mjd" => $splited_line[6]
            ));
        }
        return json_encode($resjson);
    }
}
