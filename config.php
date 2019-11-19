<?php

require_once __DIR__ . '/src/Path.php';

$GLOBALS["DATABANK_PATH"] = "C:\\Users\\user\\Dev\\Databank";
$GLOBALS["OBS_CONFIG_PATH"] = Path::join(__DIR__, "obs-config/");

$GLOBALS["USERS_PATH"] = Path::join(__DIR__, "users.json");
$GLOBALS["DEFAULT_VALUE"] = "999.999";
$GLOBALS["START_TENO"] = 59616005; // This is the date when we start using teno (useful to parse the files)
