<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . './Entities/File.php';

/**
 * Undocumented class
 */
class Upload
{

    static function uploader()
    {
        $filename = $_FILES["fichier"]["name"];
        $string = explode("-", $filename);
        $type = explode(".", $string[1])[0];
        list($code, $date) = Upload::parseFile($string[0]);

        $file = new File($code, $type, $date->getTimestamp());
        $path = $file->getFilepath(true);
        $dirname = pathinfo($path)["dirname"];

        // If directory doesn't exists, create it and all directories before it as well
        if (!file_exists($dirname))
            mkdir($dirname, 0777, true);

        $md5 = md5_file($_FILES['fichier']['tmp_name']); // Create hash from it
        $imported = count(file($_FILES['fichier']['tmp_name'])) - 1; // Remove header in the line count
        $is_uploaded = move_uploaded_file($_FILES['fichier']['tmp_name'], $path); // Move the uploaded file to its final location

        if ($is_uploaded) {
            return array(
                "saved" => true,
                "md5" => $md5,
                "size" => $_FILES['fichier']['size'],
                "imported" => $imported
            );
        } else {
            return array(
                "saved" => false,
            );
        }
    }

    static function parseFile($string)
    {
        $dateFormat = 'YmdHis';
        $dateLength = 14;
        if (mb_strlen($string) > $dateLength) {
            $code = mb_strtoupper(mb_strcut($string, 0, -$dateLength));
            $date = mb_strcut($string, -$dateLength);
            if ($code) {
                if (false !== $date = DateTime::createFromFormat($dateFormat, $date)) {
                    return [$code, $date];
                }
            }
        }

        return null;
    }
}
