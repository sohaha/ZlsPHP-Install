<?php


namespace Zls\Install;

use Zls\Action\Http;
use Z;

class Util
{
     public static function getGithubReleases($repos)
    {
        $http = new Http();
        foreach (['', 'https://github.73zls.com/'] as $v) {
            $http->get($v . 'https://api.github.com/repos/'.$repos.'/releases/latest');
            $data = $http->data(true);
            $zipUrl = Z::arrayGet($data, 'zipball_url');
            if ($zipUrl) {
                break;
            }
        }
        return $zipUrl;
    }

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
