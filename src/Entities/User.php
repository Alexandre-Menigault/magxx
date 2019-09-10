<?php


require_once __DIR__ . "/../Path.php";

class User
{
    public $config;
    static function CreateFromConfig($user_login)
    {
        try {
            $user = new User();
            $users =  json_decode(file_get_contents($GLOBALS["USERS_PATH"]));
            foreach ($users as $u) {
                if ($u->login == $user_login) {
                    $user = $u;
                    break;
                }
            }
            $user->config = array(
                "name" => $u->name,
                "login" => $u->login,
                "role" => $u->role,
            );
            return $user;
        } catch (FileNotFoundException $file_exception) {
            error_log("[User.php] Cannot create user " . $user . ": file not found");
            error_log($file_exception->getTraceAsString());
        } catch (JsonException $json_exception) {
            error_log("[User.php] Cannot create user " . $user . ": Json decode failed");
            error_log($json_exception->getTraceAsString());
        }
    }

    static function ListAllUsers()
    {
        $users = json_decode(file_get_contents($GLOBALS["USERS_PATH"]));
        $res = array();
        for ($i = 0; $i < count($users); $i++) {
            $u = $users[$i];
            $user = array(
                "name" => $u->name,
                "login" => $u->login,
                "role" => $u->role,
            );
            array_push($res, $user);
        }
        return $res;
    }
}
