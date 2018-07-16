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
     * @var EventDispatcher
     */
    static protected $dispatcher;

    /**
     * @var \LaravelFly\Server\ServerInterface
     */
    static protected $flyServer;


    // get default server options
    static $default = [];

    /**
     * @var string
     */
    static protected $workingRoot;
    static protected $laravelAppRoot;

    protected $flyDir = __DIR__ . '/../src/fly/';

    protected $backOfficalDir = __DIR__ . '/offcial_files/';

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

        $flyServer = \LaravelFly\Fly::init($options);

        static::$dispatcher = $flyServer->getDispatcher();

        return static::$flyServer = $flyServer;
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
        $server = $server ?: static::$flyServer;
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
     * server::recreateSwooleServer may produce error:
     *  Fatal error: Swoole\Server::__construct(): eventLoop has already been created. unable to create swoole_server.
     */
    function recreateSwooleServer($options, $server = null): \swoole_http_server
    {
        /**
         * \LaravelFly\Server\Common
         */
        $server = $server ?: static::$flyServer;

        $options = array_merge(self::$default, $options);


        $s = new \ReflectionProperty($server, 'swoole');
        $s->setAccessible(true);

        //todo
//        $old_swoole=$s->getValue($server);
//        if($old_swoole){
//            $old_swoole->exit();
//        }

        $new_swoole = new \swoole_http_server($options['listen_ip'], $options['listen_port']);
        $new_swoole->set($options);

        $s->setValue($server, $new_swoole);


        $new_swoole->fly = $server;
        $server->setListeners();

        return $new_swoole;
    }

    function compareFilesContent($map)
    {

        $diffOPtions = '--ignore-all-space --ignore-blank-lines';

        $same = true;

        foreach ($map as $back => $offcial) {
            $back = $this->backOfficalDir . $back;
            $offcial = static::$laravelAppRoot . $offcial;
            $cmdArguments = "$diffOPtions $back $offcial ";

            unset($a);
            exec("diff --brief $cmdArguments > /dev/null", $a, $r);
//            echo "\n\n[CMD] diff $cmdArguments\n\n";
//            print_r($a);
            if ($r !== 0) {
                $same = false;
                echo "\n\n[CMD] diff $cmdArguments\n\n";
                system("diff  $cmdArguments");
            }
        }

        self::assertEquals(true, $same);

    }
}

