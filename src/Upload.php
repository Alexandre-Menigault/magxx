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
        $return = array();
        foreach ($_FILES as $fileId => $f) {
            $filename = $f["name"];
            try {
                $string = explode("-", $filename);
                $type = explode(".", $string[1])[0];

                list($code, $date) = Upload::parseFile($string[0]);

                $file = new File($code, $type, $date->getTimestamp());
                $path = $file->getFilepath(true);
                $dirname = pathinfo($path)["dirname"];

                // If directory doesn't exists, create it and all directories before it as well
                if (!file_exists($dirname))
                    mkdir($dirname, 0777, true);

                $md5 = md5_file($f['tmp_name']); // Create hash from it
                $imported = count(file($f['tmp_name'])) - 1; // Remove header in the line count
                $is_uploaded = move_uploaded_file($f['tmp_name'], $path); // Move the uploaded file to its final location

                if ($is_uploaded) {
                    $return[$fileId] = array(
                        "saved" => true,
                        "md5" => $md5,
                        "size" => $f['size'],
                        "imported" => $imported
                    );
                } else {

                    header(http_response_code(409));
                    $return[$fileId] = array(
                        "saved" => false,
                    );
                }
            } catch (Error $e) {
                $error = new Error("Cannot parse filename " . $filename);
                $errJson = array(
                    "message" => $error->getMessage(),
                    "trace" => $error->getTrace(),
                );
                error_log(json_encode($errJson));

                header(http_response_code(400));
                $return[$fileId] = array(
                    "saved" => false,
                    "error" => $errJson
                );
            }
        }

        header(http_response_code(200));
        return $return;
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