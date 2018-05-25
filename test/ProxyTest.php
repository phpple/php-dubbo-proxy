<?php
/**
 * test file
 * @author: Ronnie Deng <comdeng@live.com>
 * @since: 2018/5/24 23:21
 * @copyright: 2018@phpple.com
 * @filesource: ProxyTest.php
 */
use \phpple\php_dubbo_proxy\Proxy;
require_once dirname(__DIR__) . '/vendor/autoload.php';

$service = Proxy::getService('com.phpple.service.FooService', array(
    'registry' => '127.0.0.1:2181',
    'version' => '1.0.0'
));
$ret = $service->bar('hello,world');
var_dump($ret);
