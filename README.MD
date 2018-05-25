php-dubbo-proxy
================

This project is designed for call java service based on dubbo.The transport protocol is telnet.


Process
--------

1. Find the provider from zookeeper.

2. Build socket connect to the provider's host and port.

3. Call the telnet command invoke, such as `invoke com.phpple.service.FooService.bar('hello,world')\n`.
   
   You can find the telnet commands at [English](http://dubbo.apache.org/books/dubbo-user-book-en/references/telnet.html) [中文](http://dubbo.apache.org/books/dubbo-user-book/references/telnet.html)

4. Read from the socket's response, and parse it. 

Requirement
-----------

* php zookeeper extension


Example
--------

```php
use \phpple\php_dubbo_proxy\Proxy;

$service = Proxy::getService('com.phpple.service.FooService', array(
    'registry' => '127.0.0.1:2181',
    'version' => '1.0.0'
));
$ret = $service->bar('hello,world');
var_dump($ret);
```
