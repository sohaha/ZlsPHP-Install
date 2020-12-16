<?php


namespace Zls\Install;


use Z;

class Util
{
    public static function GithubProcess($url, $fn)
    {
        if (strpos($url, 'github.com') < 0) {
            return $fn($url);
        }
        $u = explode("/", $url);
        if (Z::arrayGet($u, 3) !== "repos") {
            return $fn($url);
        }
        return $fn($url);
    }

    public static function pathMatche($path, $rule)
    {
        $matches = str_replace('*', '(.*)', $rule);
        $matches = str_replace('/', '\/', $matches);
        return preg_match('/^' . $matches . '/', $path);
    }
}
