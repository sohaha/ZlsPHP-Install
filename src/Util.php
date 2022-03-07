<?php


namespace Zls\Install;

use Zls\Action\Http;
use Z;

class Util
{
    public static $chinaCDN = ['https://github.73zls.com/'];

    public static function getGithubReleases($repos)
    {
        $http = new Http();
        return self::proxy('https://api.github.com/repos/' . $repos . '/releases/latest', function ($url) use ($http) {
            $http->get($v . 'https://api.github.com/repos/' . $repos . '/releases/latest');
            $data = $http->data(true);
            return Z::arrayGet($data, 'zipball_url');
        });
    }

    private static function canProxy()
    {
        $http = new Http();
        $http->setUserAgent('curl/7.58.0');
        $data = $http->get('https://www.cip.cc');
        return !(!$data || strpos($data, '中国') === false);
    }

    private static function proxy($url, $fn)
    {
        static $proxy;
        if (is_null($proxy)) {
            $canProxy = self::canProxy();
            $proxy =  $canProxy ? array_merge(self::$chinaCDN, ['']) : array_merge([''], self::$chinaCDN);

            if($canProxy) {
                var_dump('can proxy',$proxy);

            }
        }
        $result = null;
        foreach ($proxy as $v) {
            $result = $fn($v . $url);
            if (!is_null($result)) {
                break;
            }
        }
        return $result;
    }

    public static function getGithubReleasesVersion($repos)
    {
        $http = new Http();
        $version = self::proxy('https://api.github.com/repos/' . $repos . '/releases/latest', function ($url) use ($http) {
            $http->get($url);
            $data = $http->data(true);
            $version = Z::arrayGet($data, 'tag_name');
            if ($version) {
                if (Z::strBeginsWith($version, 'v')) {
                    $version = substr($version, 1);
                }
                return $version;
            }
            return null;
        });

        return $version;
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

    public static function goOS()
    {
        $os = PHP_OS;
        if (strpos($os, 'WIN') !== false) {
            return 'Windows';
        } elseif (strpos($os, 'LINUX') !== false) {
            return 'Linux';
        } elseif (strpos($os, 'Darwin') !== false) {
            return 'Darwin';
        } else {
            return '';
        }
    }

    public static function getArch()
    {
        $arch = php_uname('m');
        if (!$arch) {
            return '';
        }
        $arch = strtolower($arch);
        if (stristr($arch, 'x86_64') || stristr($arch, 'amd64')) {
            return 'x86_64';
        }
        if (stristr($arch, 'arm64')) {
            return 'arm64';
        } else {
            return 'i386';
        }
    }
}
