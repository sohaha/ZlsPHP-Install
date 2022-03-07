<?php

namespace Zls\Install;

use Z;
use Zls\Action\Http;
use Zls\Action\Zip;
use Zls\Command\Utils;

class Install
{
    use Utils;

    public $http;
    private $url;
    private $md5;
    private $dir;
    private $tmpDir;
    private $packFilePath;
    private $path;
    private $filterRule = [];
    private $moveRule = [];
    private $keepOldFile;
    private $zip;
    /**
     * @var \Closure
     */
    private $processTip;

    public function __construct($url)
    {
        $this->http = new Http();
        $this->zip = new Zip();
        $this->url = $url;
    }

    /**
     * 压缩包 MD5
     * @param $md5
     */
    public function setMd5($md5)
    {
        $this->md5 = $md5;
    }

    /**
     * 解压路径
     * @param $path
     * @param false $entr
     */
    public function setDeCompressPath($path, $entr = false)
    {
        $this->path = Z::realPathMkdir(ltrim(Z::safePath($path, ''), '/'), true, false, $entr);
    }

    /**
     * 忽略文件列表
     * @param array $rule ['zip 压缩包内路径']
     */
    public function setFilterRule(array $rule)
    {
        $this->filterRule = $rule;
    }

    public function setProcessTip(\Closure $p)
    {
        $this->processTip = $p;
    }

    private function runProcessTip($v)
    {
        $f = $this->processTip;
        if (is_callable($f)) $f ($v);
    }

    public function run()
    {
        if (!$this->path) {
            return "文件路径不能为空";
        }
        if (!$this->url) {
            return "源文件链接不能为空";
        }
        $this->runProcessTip("开始下载文件...");
        if (!$this->download()) {
            return "下载失败";
        }
        if (!$this->save()) {
            return "文件储存失败";
        }
        Z::defer(function () {
            $this->delete();
        });
        if (!$this->verification()) {
            return "文件验证失败";
        }
        $this->runProcessTip("文件解压中...");
        if ($err = $this->zipDeCompress()) {
            return Z::arrayGet($err, 'msg', '文件解压失败');
        }
        $this->runProcessTip("文件处理中...");
        $this->majorizationDir();
        $res = $this->moveFile();
        if (is_string($res)) {
            return $res;
        }
        $res['path'] = $this->path;
        return $res;
    }

    private function verification()
    {
        return $this->md5 ? $this->md5 === md5_file($this->packFilePath) : true;
    }

    private function download()
    {
        return Util::GithubProcess($this->url, function ($url) {
            $this->http->get($url, [], [], 1);
            $code = $this->http->code();
            return $code === 200;
        });
    }

    private function suffix()
    {
        $u = explode('/', $this->url);
        return Z::arrayGet($u, count($u) - 1, pathinfo($this->url, PATHINFO_EXTENSION));
    }

    private function save()
    {
        $this->dir = Z::realPathMkdir(Z::tempPath() . '/' . md5(ZLS_PATH)) . '/';
        $this->packFilePath = Z::realPathMkdir($this->dir . md5($this->url), true) . $this->suffix();
        $this->tmpDir = Z::realPathMkdir($this->dir . 'tmp', true);
        @file_put_contents($this->packFilePath, $this->http->data());
        return file_exists($this->packFilePath);
    }

    private function zipDeCompress()
    {
        $err = null;
        if (Z::strEndsWith($this->packFilePath, 'tar.gz')) {
            $phar = new \PharData($this->packFilePath);
            if (!$phar->extractTo($this->tmpDir, null, true)) {
                $err = '解压失败';
            }
        } elseif (!$this->zip->unzip($this->packFilePath, $this->tmpDir)) {
            $err = $this->zip->getError();
        }
        return $err;
    }

    /**
     * 保留旧文件
     * @param bool $keep
     */
    public function setKeepOldFile($keep = true)
    {
        $this->keepOldFile = is_bool($keep) ? 'old' : $keep;
    }

    private function delete()
    {
        Z::rmdir($this->dir);
    }

    /**
     * 文件移动对应规则
     * @param array $rule ['zip 压缩包内路径'=>'解压后路径']
     */
    public function setMoveRule(array $rule)
    {
        $this->moveRule = $rule;
    }

    private function getCofing()
    {
        if (file_exists($this->tmpDir . 'config.json')) {
            /** @noinspection PhpComposerExtensionStubsInspection */
            return @json_decode(@file_get_contents($this->tmpDir . 'config.json') ?: '', true);
        }
        return [];
    }

    private function majorizationDir()
    {
        $filename = scandir($this->tmpDir);
        $i = 0;
        $dir = '';
        foreach ($filename as $k => $v) {
            if ($v == "." || $v == "..") {
                continue;
            }
            $dir = $this->tmpDir . $v;
            $i++;
        }
        if ($i === 1 && is_dir($dir)) {
            $this->tmpDir = $dir;
        }
    }

    private function moveFile()
    {
        $res = [
            'command' => [],
            'file' => [],
            'oldfile' => []
        ];
        $arr = [];
        $this->listDir($this->tmpDir, $arr);
        $config = $this->getCofing();
        $ignore = array_merge($this->filterRule, Z::arrayGet($config, 'ignore', [
            '.*/\.git',
            '.*/\.vscode',
        ]));
        $movePath = array_merge($this->moveRule, Z::arrayGet($config, 'moveRule', []));
        $command = (array)Z::arrayGet($config, 'command', []);
        $copy = function ($file, $destPath) {
            $fileDir = substr($destPath, 0, strrpos($destPath, '/'));
            if (!is_dir($fileDir)) {
                @mkdir($fileDir, 0777, true);
            }
            if (!@copy($file, $destPath)) {
                return true;
            }
            return @touch($destPath, filemtime($file));
        };
        foreach ($arr as $file) {
            $absPath = str_replace($this->tmpDir, "", $file);
            if (in_array($absPath, ['config.json'], true)) {
                continue;
            }
            $destPath = $this->path . $absPath;
            $isIgnore = false;
            foreach ($ignore as $r) {
                if (Util::pathMatche($absPath, $r)) {
                    $isIgnore = true;
                    break;
                }
            }
            if ($isIgnore) {
                continue;
            }
            foreach ($movePath as $k => $rr) {
                if (Z::strBeginsWith($absPath, $k)) {
                    $destPath = str_replace($k, $rr, $destPath);
                    break;
                }
            }
            if (file_exists($destPath)) {
                $oldFileMd5 = md5_file($destPath);
                $fileMd5 = md5_file($file);
                if ($oldFileMd5 === $fileMd5) {
                    continue;
                }
                $res['oldfile'][] = Z::safePath($destPath);
                if ($this->keepOldFile) {
                    $copy($destPath, $destPath . '.' . $this->keepOldFile);
                }
            }
            $copy($file, $destPath);
            $res['file'][] = Z::safePath($destPath);
        }

        foreach ($command as $c) {
            $res['command'][] = Z::command($c);
        }
        return $res;
    }

    public function silentRun(array $data)
    {
        $this->setMd5(Z::arrayGet($data, 'md5', ''));
        $this->setDeCompressPath(Z::arrayGet($data, 'path', './public'));
        $this->setKeepOldFile(Z::arrayGet($data, 'KeepOldFile', true));
        $this->setFilterRule(Z::arrayGet($data, 'ignore', []));
        $this->setMoveRule(Z::arrayGet($data, 'moveRule', []));
    }
}
