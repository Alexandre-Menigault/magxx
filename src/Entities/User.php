<?php


require_once __DIR__ . "/../Path.php";

class User
{
    /**
     * The content of the config file
     *
     * @var mixed[]
     */
    public $config;

    /**
     * Creates an User Object from its config file
     *
     * @static
     * @param string $user_login
     * @return User
     * 
     * @throws FileNotFoundException
     * @throws JsonException
     */
    static function CreateFromConfig($user_login)
    {
        try {
            $user = new User();
            $users =  json_decode(file_get_contents(USERS_PATH));
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

    /**
     * List all users
     *
     * @return array List  of users
     *      array["name"] string
     *           ["login"] string
     *           ["role"] string
     *  
     */
    static function ListAllUsers()
    {
        $users = json_decode(file_get_contents(USERS_PATH));
        $res = array();
        for ($i = 0; $i < count($users); $i++) {
            $u = $users[$i];
            // Remove the uploader role from the list of users
            if ($u->role != "uploader") {
                $user = array(
                    "name" => $u->name,
                    "login" => $u->login,
                    "role" => $u->role,
                );
                array_push($res, $user);
            }
        }
        return $res;
    }
}
