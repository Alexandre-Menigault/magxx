<?php

require_once __DIR__ . '/src/Path.php';

DEFINE("DATABANK_PATH", Path::join("C:", "Users", "user", "Dev", "Databank"));
DEFINE("OBS_CONFIG_PATH", Path::join(__DIR__, "obs-config/"));
DEFINE("ABS_BINARY_PATH", Path::join(__DIR__, "bin", "fortran", "abs_data_acq.exe"));
DEFINE("LEAPS_FILE_PATH", Path::join(DATABANK_PATH, "cfgstore", "leap_second_table.lst"));
DEFINE("USERS_PATH", Path::join(__DIR__, "users.json"));
DEFINE("DEFAULT_VALUE", "99999.00");
DEFINE("START_TENO", 59616005); // This is the date when we start using teno (useful to parse the files)
