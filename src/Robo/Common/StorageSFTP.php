<?php

namespace PL\Robo\Common;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use PL\Robo\Contract\StorageInterface;
use PL\Robo\Contract\TestorConfigAwareInterface;

class StorageSFTP implements StorageInterface, TestorConfigAwareInterface
{
    use TestorDependencyInjectorTrait;
    use TestorConfigAwareTrait;

    protected SFTP $sftp;
    protected string $root;

    public function __construct()
    {
        $this->injectTestorDependencies();

        $host = $this->testorConfig->get('sftp.host');
        $user = $this->testorConfig->get('sftp.user');
        $key = $this->testorConfig->get('sftp.key');
        $password = $this->testorConfig->get('sftp.password');
        if ($key)
            $key = PublicKeyLoader::load(file_get_contents($key, $password));

//        TODO consider moving SFTP to container, to be able to test...
        $sftp = new SFTP($host);
        $ok = $sftp->login($user, $key ?: $password);
        if (!$ok) {
            throw new \Exception("Connect to $user@$host failed:\n" . $sftp->getLastError());
        }

        $this->sftp = $sftp;
        $this->root = $this->testorConfig->get('sftp.root');
    }

    function put(string $source, string $destination): void
    {
        $name = "$this->root/$destination";
        $dir = dirname($name);
        if (!$this->sftp->mkdir($dir, -1, true)) {
            throw new \Exception("mkdir($dir) failed!");
        }
        if (!$this->sftp->put($name, $source, SFTP::SOURCE_LOCAL_FILE)) {
            throw new \Exception("put($name) failed!");
        }
    }

    function list(string $prefix): array
    {
        $filelist = $this->sftp->rawlist("$this->root/$prefix", true);
        if (!$filelist) {
            throw new \Exception("rawlist($this->root/$prefix) failed!");
        }

        $table = $this->flatten($filelist, '');

        usort($table, fn($a, $b) => $b['Date']->getTimestamp() - $a['Date']->getTimestamp());
        return $table;
    }

    function get(string $source, string $destination): void
    {
        if (!$this->sftp->get("$this->root/$source", $destination)) {
            throw new \Exception("get($this->root/$source) failed!");
        }
    }

    /**
     * @param array $filelist recursive file array such as
     * array('subfolder' => array('file1' => obj1, 'file2' => obj2), 'file3' => obj3)
     * where each obj is an object returned by SFTP?
     * @param string $prefix prefix to add to each file name
     * @return array Target array which is just an array with
     * numeric indeces and each item is array containing Name, Date, Size
     */
    protected function flatten(array $filelist, string $prefix): array
    {
        $array = [];
        foreach ($filelist as $key => $value) {
            if (is_array($value)) {
                $array = array_merge($array, $this->flatten($value, "$prefix$key/"));
            } else if ($value->type != 2) {
                // filter out directories (normally '.' and '..')
                $array[] = array(
                    'Name' => "$prefix$value->filename",
                    'Date' => (new \DateTime())->setTimestamp($value->mtime),
                    'Size' => "{$value->size}"
                );
            }
        }

        return $array;
    }
}