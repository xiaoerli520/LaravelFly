<?php


/**
 * first:
 * cd laravel_project_root
 *
 *
 * Mode Map
 * vendor/bin/phpunit  --stop-on-failure -c vendor/scil/laravel-fly/phpunit.xml.dist --testsuit LaravelFly_Map_Unit
 * vendor/bin/phpunit  --stop-on-failure -c vendor/scil/laravel-fly/phpunit.xml.dist --testsuit LaravelFly_Map_Unit2
 * vendor/bin/phpunit  --stop-on-failure -c vendor/scil/laravel-fly/phpunit.xml.dist --testsuit LaravelFly_Map_Unit3
 * vendor/bin/phpunit  --stop-on-failure -c vendor/scil/laravel-fly/phpunit.xml.dist --testsuit LaravelFly_Map_Unit4
 *
 * vendor/bin/phpunit  --stop-on-failure -c vendor/scil/laravel-fly/phpunit.xml.dist --testsuit LaravelFly_Map_Feature
 * vendor/bin/phpunit  --stop-on-failure -c vendor/scil/laravel-fly/phpunit.xml.dist --testsuit LaravelFly_Map_Feature2
 * vendor/bin/phpunit  --stop-on-failure -c vendor/scil/laravel-fly/phpunit.xml.dist --testsuit LaravelFly_Map_LaravelTests
 *
 * Mode simple
 * vendor/bin/phpunit  --stop-on-failure -c vendor/scil/laravel-fly/phpunit.xml.dist --testsuit LaravelFly_Simple_Unit
 */

namespace LaravelFly\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Class Base
 * @package LaravelFly\Tests
 *
 * why abstract? stop phpunit to use this testcase
 */
abstract class BaseTestCase extends TestCase
{
    /**
     * @var \Illuminate\Foundation\Application
     */
    static private $laravelApp;

    /**
     * @var EventDispatcher
     */
    static protected $dispatcher;

    /**
     * @var \LaravelFly\Server\ServerInterface
     */
    static protected $flyServer;

    /**
     * @var \LaravelFly\Server\Common;
     */
    static $commonServer;

    // get default server options
    static $default = [];

    /**
     * @var string
     */
    static protected $workingRoot;
    static protected $laravelAppRoot;

    static function setUpBeforeClass()
    {

        if (!AS_ROOT) {
            static::$laravelAppRoot = static::$workingRoot = realpath(__DIR__ . '/../../../..');
            return;
        }

        static::$workingRoot = realpath(__DIR__ . '/..');
        $r = static::$laravelAppRoot = realpath(static::$workingRoot . '/../../..');

        if (!is_dir($r . '/app')) {
            exit("[NOTE] FORCE setting \$laravelAppRoot= $r,please make sure laravelfly code or its soft link is in laravel_app_root/vendor/scil/\n");
        }
    }

    /**
     * Get laravel official App instance, but instance of any of Laravelfly Applications
     *
     * @return \Illuminate\Foundation\Application
     */
    static protected function getLaravelApp()
    {
        if (!self::$laravelApp)
            self::$laravelApp = require static::$laravelAppRoot . '/bootstrap/app.php';

        return self::$laravelApp;
    }

    /**
     * @return \LaravelFly\Server\Common
     */
    public static function getCommonServer(): \LaravelFly\Server\Common
    {
        return self::$commonServer;
    }

    static protected function makeCommonServer()
    {
        if (static::$commonServer) return static::$commonServer;

        static::$commonServer = new \LaravelFly\Server\Common();

        // get default server options
        $d = new \ReflectionProperty(static::$commonServer, 'defaultOptions');
        $d->setAccessible(true);
        $options = $d->getValue(static::$commonServer);
        $options['pre_include'] = false;
        $options['colorize'] = false;
        static::$default = $options;

        return static::$commonServer;
    }

    static protected function makeNewFlyServer($constances = [], $options = [], $config_file = __DIR__ . '/../config/laravelfly-server-config.example.php')
    {
        foreach ($constances as $name => $val) {
            if (!defined($name))
                define($name, $val);
        }

        $options['colorize'] = false;

        if (!isset($options['pre_include']))
            $options['pre_include'] = false;

        $file_options = require $config_file;

        $options = array_merge($file_options, $options);

        $fly = \LaravelFly\Fly::init($options);

        static::$dispatcher = $fly->getDispatcher();

        return static::$flyServer = $fly->getServer();
    }

    /**
     * @return \LaravelFly\Server\ServerInterface
     */
    public static function getFlyServer(): \LaravelFly\Server\ServerInterface
    {
        return self::$flyServer;
    }

    function resetServerConfigAndDispatcher($server = null)
    {
        $server = $server ?: static::$commonServer;
        $c = new \ReflectionProperty($server, 'options');
        $c->setAccessible(true);
        $c->setValue($server, []);

        $d = new \ReflectionProperty($server, 'dispatcher');
        $d->setAccessible(true);
        $d->setValue($server, new EventDispatcher());

    }

    /**
     * to create swoole server in phpunit, use this instead of server::createSwooleServer
     *
     * @param $options
     * @param $server
     * @return \swoole_http_server
     * @throws \ReflectionException
     *
     * server::setServerPropSwoole may produce error:
     *  Fatal error: Swoole\Server::__construct(): eventLoop has already been created. unable to create swoole_server.
     */
    function setServerPropSwoole($options, $server = null): \swoole_http_server
    {
        $server = $server ?: static::$commonServer;

        $options = array_merge(self::$default, $options);

        $s = new \ReflectionProperty($server, 'swoole');
        $s->setAccessible(true);
        $new_swoole = new \swoole_http_server($options['listen_ip'], $options['listen_port']);
        $new_swoole->set($options);

        $s->setValue($server, $new_swoole);


        $new_swoole->fly = $server;
        $server->setListeners();

        return $new_swoole;
    }
}

