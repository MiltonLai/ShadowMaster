<?php

class Autoloader
{
    public static function register()
    {
        spl_autoload_register(function ($class) {
            $file = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
            if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . $file)) {
                require $file;
                return true;
            }
            return false;
        });
    }
}
Autoloader::register();