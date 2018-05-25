<?php
/**
 * Dubbo's Proxy
 * @author: Ronnie deng <comdeng@live.com>
 * @since: 2018/5/24 22:45
 * @copyright: 2018@phpple.com
 * @filesource: Provider.php
 */

namespace phpple\php_dubbo_proxy;

class Proxy
{
    const DUBBO_NO_SUCH_METHOD_FLAG = 'No such method listAllWebSites in ';
    const DUBBO_NO_SUCH_SERVICE_FLAG = 'No such service ';
    const DUBBO_NORMAL_END_FLAG = 'elapsed: ';
    const DUBBO_NULL_RESULT_FLAG = "null\r\n";
    const DUBBO_HINT_FLAG = 'dubbo>';

    /**
     * Zookeeper's instance
     * @var \Zookeeper
     */
    private $zoo;
    /**
     * Registry's address
     * @var string
     */
    private $registry = '127.0.0.1:2181';
    /**
     * Provider's version
     * @var string
     */
    private $version = "1.0.0";

    /**
     * Service's name
     * @var string
     */
    private $service;

    private function __construct($service, $registry = null, $version = null)
    {
        $this->service = $service;
        if ($registry !== null) {
            $this->registry = $registry;
        }
        if ($version !== null) {
            $this->version = $version;
        }
    }

    /**
     * Get the proxy for custom service
     * @param $service service's name
     * @param array $conf configure,such as registry, version
     * @return Proxy
     */
    public static function getService($service, $conf = array())
    {
        $registry = null;
        if (isset($conf['registry'])) {
            $registry = $conf['registry'];
        }
        $version = null;
        if (isset($conf['version'])) {
            $version = $conf['version'];
        }
        return new self($service, $registry, $version);
    }

    /**
     * Invoke service's method
     * @param $name method's name
     * @param $arguments arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        $provider = $this->getProvider($this->service, $name);
        if (empty($provider)) {
            throw new \Exception('dubbo.providerNotFound');
        }
        $ret = $this->invoke($provider, $name, $arguments);
        return json_decode($ret, true);
    }

    /**
     * Get the provider for the custom service and method
     * @param $service
     * @param $method
     * @return bool|array
     */
    private function getProvider($service, $method)
    {
        if (!$this->zoo) {
            $this->zoo = new \Zookeeper($this->registry);
        }
        $path = sprintf('/dubbo/%s/providers', $service);
        $rows = $this->zoo->getChildren($path);
        $providers = [];
        foreach ($rows as $row) {
            $info = parse_url(rawurldecode($row));

            $options = [];
            if (isset($info['query'])) {
                parse_str($info['query'], $options);
            }
            unset($info['query']);
            $info['options'] = $options;
            if ($this->version && $info['options']['version'] != $this->version) {
                continue;
            }
            $info['options']['methods'] = explode(',', $info['options']['methods']);
            if (!in_array($method, $info['options']['methods'])) {
                continue;
            }

            $providers[] = $info;
        }
        $num = count($providers);
        if ($num == 0) {
            return false;
        }
        if ($num > 1) {
            $index = mt_rand(0, $num - 1);
            $provider = $providers[$index];
        } else {
            $provider = $providers[0];
        }
        return $provider;
    }

    /**
     * Call dubbo's remoting method
     * @param $provider
     * @param $method
     * @param $args
     * @return string
     * @throws \Exception
     */
    private function invoke($provider, $method, $args)
    {
        $fh = fsockopen($provider['host'], $provider['port']);
        $timeout = isset($provider['options']['timeout']) ? $provider['options']['timeout']/1000 : 5;
        stream_set_blocking($fh, 0);
        stream_set_write_buffer($fh, 0);
        stream_set_timeout($fh, $timeout);
        $args = json_encode($args);
        $args = substr($args, 1, -1);
        $cmd = sprintf("invoke %s.%s(%s)", $provider['options']['interface'], $method, $args);
        fwrite($fh, $cmd.PHP_EOL);

        $output = [];
        $num = 0;

        while (!feof($fh)) {
            $buffer = fgets($fh);
            if ($buffer === self::DUBBO_NULL_RESULT_FLAG) {
                $output[] = 'null';
                break;
            }
            if (strncmp($buffer, self::DUBBO_NORMAL_END_FLAG, strlen(self::DUBBO_NORMAL_END_FLAG)) === 0) {
                break;
            }
            if (strncmp($buffer, self::DUBBO_NO_SUCH_METHOD_FLAG, strlen(self::DUBBO_NO_SUCH_METHOD_FLAG)) === 0) {
                throw new \Exception("dubbo.noMethod");
            }
            if (strncmp($buffer, self::DUBBO_NO_SUCH_SERVICE_FLAG, strlen(self::DUBBO_NO_SUCH_SERVICE_FLAG)) === 0) {
                throw new \Exception('dubbo.noService');
            }
            if ($buffer !== false) {
                $output[] = rtrim($buffer);
            } else {
                $num++;
                if ($num > 1000000) {
                    trigger_error('dubbo.noResponse', E_USER_ERROR);
                    break;
                }
            }
        }
        $result = implode('', $output);
        if (strncmp($result, self::DUBBO_HINT_FLAG, strlen(self::DUBBO_HINT_FLAG)) === 0) {
            $line = substr($result, strlen(self::DUBBO_HINT_FLAG));
        }
        // if charset is defined in configure, convert is not required
        if (isset($provider['options']['charset']) && strtolower($provider['options']['charset']) == 'utf-8') {
            return $result;
        }
        return iconv('gbk', 'utf-8', $result);
    }
}
