<?php

namespace Zls\Install\Command;

use Zls\Action\Http;
use Zls\Install\Install as In;
use Z;

class Install extends \Zls\Command\Command
{
    public function options()
    {
        return [];
    }

    public function description()
    {
        return '下载安装压缩包';
    }

    private function getReleases()
    {
        $http = new Http();
        foreach (['', 'https://github.73zls.com/'] as $v) {
            $http->get($v . 'https://api.github.com/repos/sohaha/zzz/releases/latest');
            $data = $http->data(true);
            $zipUrl = Z::arrayGet($data, 'zipball_url');
            if ($zipUrl) {
                break;
            }
        }
        return $zipUrl;
    }

    public function execute($args)
    {
        foreach ($args as $k => $v) {
            $args[ltrim($k, '-')] = $v;
        }
        $data = array_merge([
            'url' => '',
            'md5' => '',
            'path' => './public',
            'ignore' => [],
            'moveRule' => [],
            'KeepOldFile' => true,
        ], $args);
        $url = Z::arrayGet($data, 'url');
        if (!$url) {
            $this->printStrN('[ Process ]: 获取文件地址...');
            //$url = $this->getReleases();
        }
        $d = new In($url);
        $d->silentRun($data);
        $d->setProcessTip(function ($v) {
            $this->printStrN('[ Process ]: ' . $v);
        });
        $res = $d->run();
        if (is_string($res)) {
            $this->error($res);
            return;
        }
        $total = count($res['file']);
        $this->success("已经成功安装至(共更新{$total}个文件): {$res['path']}");
    }
}
