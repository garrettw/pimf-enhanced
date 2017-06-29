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
    public function __construct(Config $conf, Environment $server)
    {
        $this->env = $server;

        $problems = [];
        // nothing exceptionable should be happening in __construct()
        try {

            // this belongs in run() somehow, but $conf isn't there
            \date_default_timezone_set($conf['timezone']);

            // this one is a mess
            $this->setupUtils($conf['bootstrap.local_temp_directory'], $conf->get('logging.storage', 'file'));

            $this->logger->checkInit();

            // this needs to be done before much user app code is run
            $this->setupErrorHandling($conf['environment']);

            $appPath = BASE_PATH . 'app/' . $conf['app.name'];

            // $this->loadListeners();
            $events = $appPath . '/events.php';
            if (file_exists($events)) {
                include_once $events;
            }

            // $this->loadPdoDriver();
            $dbConf = $conf[$conf['environment'] . '.db'];
            if (is_array($dbConf) && $conf['environment'] === 'production') {
                $this->em = new EntityManager(Pdo\Factory::get($dbConf), $conf['app.name']);
            }

            // $this->loadRoutes();
            $routes = $appPath . '/routes.php';
            if ($conf['app.routeable'] === true && file_exists($routes)) {
                $this->router = new Router();

                foreach ((array)(include $routes) as $route) {
                    $this->router->map($route);
                }
            }


        } catch (\Throwable $throwable) {
            $problems[] = $throwable->getMessage();
        } catch (\Exception $exception) {
            $problems[] = $exception->getMessage();
        }

        // $this->reportIf();
        if (version_compare(PHP_VERSION, 5.6) === -1) {
            $problems[] = 'You have PHP ' . PHP_VERSION . ' and you need 5.6 or higher!';
        }

        if (!empty($problems)) {
            throw new \RuntimeException(implode(PHP_EOL . PHP_EOL, $problems));
        }
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
                return;
            }
        }

        $prefix = Str::ensureTrailing('\\', Config::get('app.name'));
        $repository = BASE_PATH . 'app/' . Config::get('app.name') . '/Controller';

        if (isset($cli['controller']) && $cli['controller'] === 'core') {
            $prefix = 'Pimf\\';
            $repository = BASE_PATH . 'pimf-framework/core/Pimf/Controller';
        }

        $request = new Request($get, $post, $cookie, $cli, $files, self::$env);
        $resolver = new Resolver($request, $repository, $prefix, self::$router);
        $sessionized = (Sapi::isWeb() && !empty(Config::get('session.storage')));

        if ($sessionized === true) {
            Session::load();
        }

        $pimf = $resolver->process(self::$env, self::$em, self::$logger);

        if ($sessionized === true) {
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
        if ($environment === 'testing') {
            error_reporting(E_ALL | E_STRICT);
            return;
        }

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

    /**
     * @param array $server
     * @param $tmpPath
     * @param string $logging
     */
    private function setupUtils($tmpPath, $logging = 'file')
    {
        $envData = $this->env->data();

        ResponseStatus::setup($envData->get('SERVER_PROTOCOL', 'HTTP/1.0'));

        Header::setup(
            self::$env->getUserAgent()
        );

        Url::setup(self::$env->getUrl(), self::$env->isHttps());
        Uri::setup(self::$env->PATH_INFO, self::$env->REQUEST_URI);
        Uuid::setup(self::$env->getIp(), self::$env->getHost());

        $remoteIp = $this->env->getIp();
        $script = $envData->get('PHP_SELF', $envData->get('SCRIPT_NAME'));

        if ($logging === 'file') {
            $this->logger = new Logger(
                $remoteIp,
                $script,
                new Adapter\File($tmpPath, "pimf-logs.txt"),
                new Adapter\File($tmpPath, "pimf-warnings.txt"),
                new Adapter\File($tmpPath, "pimf-errors.txt")
            );
            return;
        }
        $this->logger = new Logger(
            $remoteIp,
            $script,
            new Adapter\Std(Adapter\Std::OUT),
            new Adapter\Std(Adapter\Std::OUT),
            new Adapter\Std(Adapter\Std::ERR)
        );
    }

    /**
     * PIMF Application can not be cloned.
     */
    private function __clone()
    {
    }
}
