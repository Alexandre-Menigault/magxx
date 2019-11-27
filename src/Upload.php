<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Entities/File.php';

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
                $file = new File($code, $type, intval($date));
                $path = $file->computeFilePath();
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
                    "message" => $e->getMessage(),
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
        $parts = explode("-", $string);
        $code = $parts[0];
        $date = Teno::toUTC($parts[1]);
        return [$code, $date->teno];
    }
}
