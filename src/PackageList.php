<?php
/**********************************************************
 * File Name: test.php
 * Author: Chao Hong <teddy.hongchao@gmail.com>
 * Program:
 * History: 五  3/ 2 11:39:04 2018
 **********************************************************/
require __DIR__.'/../vendor/autoload.php';

use \GuzzleHttp\Exception\RequestException;

class PackageList
{
    private $pdo;
    private $client;

    public function __construct()
    {
        $this->pdo = $this->_getMysqlConn();
        $this->client = new \GuzzleHttp\Client();
    }

    public function checkNewPackages()
    {
        $newList = $this->_getAllPackageList();
        $existed = $this->_getExistPackages();
        $newPackages = $this->_getDiff($newList, $existed);
        if ($newPackages) {
            $this->_addNewPackages($newPackages);
        }
    }

    public function _getAllPackageList()
    {
        try {
            $listJson = $this->client->request('GET', 'https://packagist.org/packages/list.json');
            $res = json_decode($listJson->getBody(), true);
        } catch (RequestException $e) {
            return [];
        }

        return $res['packageNames'];
    }

    private function _getExistPackages()
    {
        $sql="select name from packages";
        $res = $this->pdo->query($sql);

        $packages = [];
        foreach($res as $row) {
            $packages[] = $row['name'];
        }

        return $packages;
    }

    public function _getDiff($newList, $existed, $limit = 500)
    {
        $inexistence = array_diff($newList, $existed);
        if (count($inexistence) > $limit) {
            return array_slice($inexistence, 0, $limit);
        }
        return $inexistence;
    }

    private function _addNewPackages($packages)
    {
        foreach ($packages as $package) {
            $this->_insertPackage($package);
        }
    }

    private function _getPackageInfos($packageName)
    {
        $packagesInfo = $this->_getPackageInfosByName($packageName);

        // 名字有问题或者特殊符号，需要额外处理
        if (!isset($packagesInfo[$packageName])) {
            $packagesInfo = $this->_getExtraPackageInfos($packageName);
        }

        // clear not found package
        if (!isset($packagesInfo[$packageName])) {
            $this->_clearPackage($packageName);
        }

        return $packagesInfo;
    }

    private function _getPackageInfosByName($name)
    {
        $baseUrl = 'https://packagist.org/search.json?q=';

        return $this->_getPackageInfoByUrl($baseUrl.$name);
    }

    private function _getPackageInfoByUrl($url)
    {
        $res = $this->client->request('GET', $url);
        $infos = json_decode($res->getBody(), true);

        $packageInfos = [];
        foreach ($infos['results'] as $info) {
            // 有些virtual package 没有download，不需要记录
            if (isset($info['downloads']) && isset($info['favers'])) {
                $this->_setVendor($info);
                $packageInfos[$info['name']] = $info;
            }
        }

        // 递归完所有页的数据
        if (isset($infos['next'])) {
            $infos = $this->_getPackageInfoByUrl($infos['next']);
            $packageInfos = array_merge($packageInfos, $infos);
        }

        return $packageInfos;
    }

    private function _getExtraPackageInfos($packageName)
    {
        $extraInfos = [];

        $pos = strpos($packageName, '/');
        // vendor
        $vendor = substr($packageName, 0, $pos);
        $extraInfos = array_merge($extraInfos, $this->_getPackageInfosByName($vendor));
        // package
        $package = substr($packageName, $pos);
        $extraInfos = array_merge($extraInfos, $this->_getPackageInfosByName($package));
        //special name
        $name = str_replace('.', '\\.', $vendor).$package;
        $extraInfos = array_merge($extraInfos, $this->_getPackageInfosByName($name));

        return $extraInfos;
    }

    private function _clearPackage($packageName)
    {
        $sql = 'UPDATE packages SET isDeleted = 1 where name="'.$packageName.'"';
        $this->pdo->query($sql);
    }

    private function _setVendor(&$info)
    {
        $name = $info['name'];
        $info['vendor'] = substr($name, 0, strpos($name, '/'));
    }

    private function _insertPackage($packageName)
    {
        $sql = 'insert into packages (name) values ("'.$packageName.'")';
        $this->pdo->exec($sql);
    }

    private function _record($info)
    {
        $sql = 'UPDATE packages SET
            downloads = '.$info['downloads'].',
            favers = '.$info['favers'].',
            url = "'.$info['url'].'",
            vendor = "'.$info['vendor'].'",
            description = :description,
            repository = "'.$info['repository'].'",
            updated = NOW(),
            isDeleted = false
            where name="'.$info['name'].'"';

        // 中文和emoji乱码问题
        $this->pdo->query("SET NAMES utf8mb4");
        $result = $this->pdo->prepare($sql);
        $result->bindParam(':description', $info['description']);
        $res = $result->execute();

        return $res;
    }

    private function _getMysqlConn()
    {
        $dbType = 'mysql';     //数据库类型
        $host = '127.0.0.1'; //数据库主机名
        $dbName='packagist';    //使用的数据库
        $user='teddy';      //数据库连接用户名
        $pass='hc837883767';          //对应的密码
        $dsn = $dbType.':host='.$host.';dbname='.$dbName;

        return new PDO($dsn, $user, $pass); //初始化一个PDO对象
    }
}

(new PackageList())->checkNewPackages();
