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

    public $lastBaselineConfig;

    /**
     * Create a Observatory Object from its config file
     * @static
     * @param string $obs_name - The short code of the observatory
     * @param bool $includeBaselineConfig - Do include last baseline config
     * @return Observatory
     * @throws FileNotFoundException
     * @throws JsonException
     */
    static function CreateFromConfig($obs_name, $includeBaselineConfig = false)
    {
        try {
            $obs = new Observatory();
            $obs->config =  json_decode(file_get_contents(Path::join(OBS_CONFIG_PATH, $obs_name, "obs_config.json")));

            if ($includeBaselineConfig) {
                $jsonBslConfig = json_decode(file_get_contents(Path::join(OBS_CONFIG_PATH, strtoupper($obs_name), "baseline_base.json")));
                $obs->lastBaselineConfig = $jsonBslConfig[count($jsonBslConfig) - 1];
            }


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
        $directories = scandir(OBS_CONFIG_PATH);
        $res = array();
        for ($i = 0; $i < count($directories); $i++) {
            if (in_array($directories[$i], array('..', '.'))) continue; // Ignore .. and . directories
            if (is_dir(Path::join(OBS_CONFIG_PATH, $directories[$i]))) array_push($res, $directories[$i]);
            // $split = explode(".", $files[$i]);

            // if (count($split) == 2 && $split[1] == "json") {
            //     array_push($res, $split[0]);
            // }
        }
        return $res;
    }
}
