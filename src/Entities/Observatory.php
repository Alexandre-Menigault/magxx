<?php

require_once __DIR__ . "/../Path.php";

class Observatory
{
    /**
     * The content of the config file
     *
     * @var mixed[]
     */
    public $config;
    /**
     * Create a Observatory Object from its config file
     * @static
     * @param string $obs_name - The short code of the observatory
     * @return Observatory
     * @throws FileNotFoundException
     * @throws JsonException
     */
    static function CreateFromConfig($obs_name)
    {
        try {
            $obs = new Observatory();
            $obs->config =  json_decode(file_get_contents(Path::join(OBS_CONFIG_PATH, $obs_name . ".json")));
            return $obs;
        } catch (FileNotFoundException $file_exception) {
            error_log("[Observatory.php] Cannot create observatory " . $obs . ": file not found");
            error_log($file_exception->getTraceAsString());
        } catch (JsonException $json_exception) {
            error_log("[Observatory.php] Cannot create observatory " . $obs . ": Json decode failed");
            error_log($json_exception->getTraceAsString());
        }
    }

    /**
     * List all the available observatories
     *
     * @return string[]
     */
    static function ListAllObs()
    {
        $files = scandir(OBS_CONFIG_PATH);
        $res = array();
        for ($i = 0; $i < count($files); $i++) {
            if (in_array($files[$i], array('..', '.'))) continue; // Ignore .. and . directories
            $split = explode(".", $files[$i]);
            if (count($split) == 2 && $split[1] == "json") {
                array_push($res, $split[0]);
            }
        }
        return $res;
    }
}
