<?php
/**
 * Pimf
 *
 * @copyright Copyright (c) Gjero Krsteski (http://krsteski.de)
 * @license   http://opensource.org/licenses/MIT MIT License
 */

namespace Pimf;

use Pimf\Util\Character as Str;
use Pimf\Util\Header;
use Pimf\Util\Header\ResponseStatus;
use Pimf\Util\Uuid;

/**
 * Provides a facility for applications which provides reusable resources,
 * common-based bootstrapping and dependency checking.
 *
 * @package Pimf
 * @author  Gjero Krsteski <gjero@krsteski.de>
 *
 */
final class Application
{
    const VERSION = '1.10.0';

    /**
     * @var Environment
     */
    protected $env;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var Router
     */
    protected $router;

    /**
     * Mechanism used to do initial setup and edging before a application runs.
     *
     * @param \Pimf\Config $conf The object containing configuration options.
     * @param array $server Array of information such as headers, paths, and script locations.
     *
     * @return boolean|null
     */
    public function __construct(Config $conf, array $server = [])
    {
        $problems = [];

        try {

            $environment = $conf['environment'];
            $appname = $conf['app.name'];

            date_default_timezone_set($conf['timezone']);

            $this->setupUtils($server, $conf['bootstrap.local_temp_directory'], $conf->get('logging.storage', 'file'));
            $this->loadListeners(BASE_PATH . 'app/' . $appname . '/events.php');
            $this->setupErrorHandling($environment);
            $this->loadPdoDriver($environment, $conf[$environment . '.db'], $appname);
            $this->loadRoutes(
                $conf['app.routeable'],
                BASE_PATH . 'app/' . $appname . '/routes.php'
            );

        } catch (\Throwable $throwable) {
            $problems[] = $throwable->getMessage();
        } catch (\Exception $exception) {
            $problems[] = $exception->getMessage();
        }

        $this->reportIf($problems, PHP_VERSION);
    }

    /**
     * Please bootstrap first, than run the application!
     * Run a application, let application accept a request, route the request,
     * dispatch to controller/action, render response and return response to client finally.
     *
     * @param array $get Array of variables passed to the current script via the URL parameters.
     * @param array $post Array of variables passed to the current script via the HTTP POST method.
     * @param array $cookie Array of variables passed to the current script via HTTP Cookies.
     * @param array $files An associative array FILES of items uploaded to the current script via the HTTP POST method.
     *
     * @return void
     */
    public function run(array $get, array $post, array $cookie, array $files)
    {
        $cli = array();
        if (Sapi::isCli()) {
            $cli = Cli::parse((array)self::$env->argv);
            if (count($cli) < 1 || isset($cli['list'])) {
                Cli::absorb();
                exit(0);
            }
        }

        $prefix = Str::ensureTrailing('\\', Config::get('app.name'));
        $repository = BASE_PATH . 'app/' . Config::get('app.name') . '/Controller';

        if (isset($cli['controller']) && $cli['controller'] == 'core') {
            $prefix = 'Pimf\\';
            $repository = BASE_PATH . 'pimf-framework/core/Pimf/Controller';
        }

        $request = new Request($get, $post, $cookie, $cli, $files, self::$env);
        $resolver = new Resolver($request, $repository, $prefix, self::$router);
        $sessionized = (Sapi::isWeb() && Config::get('session.storage') !== '');

        if ($sessionized) {
            Session::load();
        }

        $pimf = $resolver->process(self::$env, self::$em, self::$logger);

        if ($sessionized) {
            Session::save();
            Cookie::send();
        }

        $pimf->render();

        return $this;
    }

    /**
     * @param string $environment
     */
    private function setupErrorHandling($environment)
    {
        if ($environment == 'testing') {
            error_reporting(E_ALL | E_STRICT);
        } else {

            $logger = self::$logger;

            set_exception_handler(
                function ($exception) use ($logger) {
                    Error::exception($exception, $logger);
                }
            );

            set_error_handler(
                function ($code, $error, $file, $line) use ($logger) {
                    Error::native($code, $error, $file, $line, $logger, error_reporting());
                }
            );

            register_shutdown_function(
                function () use ($logger) {
                    Error::shutdown($logger, error_get_last());
                }
            );

            error_reporting(-1);
        }
    }

    /**
     * @param array $server
     * @param $tmpPath
     * @param string $logging
     */
    private function setupUtils(array $server, $tmpPath, $logging = 'file')
    {
        self::$env = new Environment($server);
        $envData = self::$env->data();

        Logger::setup(
            self::$env->getIp(),
            $envData->get('PHP_SELF', $envData->get('SCRIPT_NAME'))
        );

        ResponseStatus::setup($envData->get('SERVER_PROTOCOL', 'HTTP/1.0'));

        Header::setup(
            self::$env->getUserAgent()
        );

        Url::setup(self::$env->getUrl(), self::$env->isHttps());
        Uri::setup(self::$env->PATH_INFO, self::$env->REQUEST_URI);
        Uuid::setup(self::$env->getIp(), self::$env->getHost());

        if ($logging === 'file') {
            self::$logger = new Logger(
                new Adapter\File($tmpPath, "pimf-logs.txt"),
                new Adapter\File($tmpPath, "pimf-warnings.txt"),
                new Adapter\File($tmpPath, "pimf-errors.txt")
            );
        } else {
            self::$logger = new Logger(
                new Adapter\Std(Adapter\Std::OUT),
                new Adapter\Std(Adapter\Std::OUT),
                new Adapter\Std(Adapter\Std::ERR)
            );
        }

        self::$logger->init();
    }

    /**
     * @param string $environment
     * @param array $dbConf
     * @param string $appName
     */
    private function loadPdoDriver($environment, $dbConf, $appName)
    {
        if (is_array($dbConf) && $environment != 'testing') {
            self::$em = new EntityManager(Pdo\Factory::get($dbConf), $appName);
        }
    }

    /**
     * @param boolean $routeable
     * @param string $routes Path to routes definition file.
     */
    private function loadRoutes($routeable, $routes)
    {
        if ($routeable === true && file_exists($routes)) {

            self::$router = new Router();

            foreach ((array)(include $routes) as $route) {

                self::$router->map($route);

            }
        }
    }

    /**
     * @param string $events Path to event listeners
     */
    private function loadListeners($events)
    {
        if (file_exists($events)) {
            include_once $events;
        }
    }

    /**
     * @param array $problems
     * @param float $version
     * @param bool $die
     *
     * @return array|void
     */
    private function reportIf(array $problems, $version, $die = true)
    {
        if (version_compare($version, 5.3) == -1) {
            $problems[] = 'You have PHP ' . $version . ' and you need 5.3 or higher!';
        }

        if (!empty($problems)) {
            return ($die === true) ? die(implode(PHP_EOL . PHP_EOL, $problems)) : $problems;
        }
    }

    /**
     * PIMF Application can not be cloned.
     */
    private function __clone()
    {
    }
}
