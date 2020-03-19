<?php

include_once __DIR__ . "/Path.php";

function _isUserValid($user, $pass, $users)
{
    foreach ($users as $u) {
        if ($u["role"] == "uploader" && $u["login"] == $user && $u["passwd"] == $pass) {
            return true;
        }
    }
    return false;
}

function authenticate()
{

    $user = $_SERVER['PHP_AUTH_USER'];
    $pass = $_SERVER['PHP_AUTH_PW'];



    if (!isset($user) || !isset($pass)) {

        header('WWW-Authenticate: Basic realm="Magproc"');
        header('HTTP/1.0 401 Unauthorized');
        die("Not Authorized");
    }

    $users = json_decode(file_get_contents("../users.json"), true);
    if (!_isUserValid($user, $pass, $users)) {

        header('WWW-Authenticate: Basic realm="Magproc"');
        header('HTTP/1.0 401 Unauthorized');
        die("Not Authorized");
    }
    // If here can continue execution of the script
}
