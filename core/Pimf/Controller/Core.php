<?php
/**
 * Controller
 *
 * @copyright Copyright (c)  Gjero Krsteski (http://krsteski.de)
 * @license   http://opensource.org/licenses/MIT MIT License
 */

namespace Pimf\Controller;

use Pimf\Config, Pimf\Cli\Std, Pimf\Pdo\Factory, \Pimf\Controller\Exception as Bomb, Pimf\Util\File;

/**
 * @package Controller
 * @author  Gjero Krsteski <gjero@krsteski.de>
 * @codeCoverageIgnore
 */
class Core extends Base
{
    /**
     * Because it is a PIMF restriction!
     */
    public function indexAction()
    {
        return;
    }

    /**
     * Checks the applications architecture and creates some security and safety measures.
     */
    public function initCliAction()
    {
        clearstatcache();

        $app = 'app/' . Config::get('app.name') . '/';

        $assets = [
            BASE_PATH . $app . '_session/',
            BASE_PATH . $app . '_cache/',
            BASE_PATH . $app . '_database/',
            BASE_PATH . $app . '_templates/',
        ];

        echo 'Checking app assets ...' . PHP_EOL;

        foreach ($assets as $asset) {

            if (!is_dir($asset)) {
                echo "[ Please create '$asset' directory! ]" . PHP_EOL;
            }

            if (!is_writable($asset)) {
                echo "[ Please make '$asset' writable! ]" . PHP_EOL;
            }
        }

        echo 'Securing root directory ...' . PHP_EOL;
        chmod(BASE_PATH, 0755);

        echo 'Securing .htaccess ...' . PHP_EOL;
        chmod(BASE_PATH . '.htaccess', 0644);

        echo 'Securing index.php ...' . PHP_EOL;
        chmod(BASE_PATH . 'index.php', 0644);

        echo 'Securing pimf-framework/autoload.core.php ...' . PHP_EOL;
        chmod(BASE_PATH . 'pimf-framework/autoload.core.php', 0644);

        echo 'Creating log files ...' . PHP_EOL;
        array_walk(
            ['logs', 'warnings', 'errors'],
            function($value, $key, $directory) {
                $file = $directory . "pimf-$value.txt";
                fclose(fopen($file, "at+"));
                chmod($file, 0777);
            },
            Config::get('bootstrap.local_temp_directory')
        );

        clearstatcache();
    }

    /**
     * Creates database table for session data.
     */
    public function createSessionTableCliAction()
    {
        $std = new Std();
        $type = $std->read('database type [mysql|sqlite]', '(mysql|sqlite)');

        if ($this->createTable($type, 'session')) {
            echo 'Session table successfully created.' . PHP_EOL;
            return;
        }
        echo 'Problems creating session table!' . PHP_EOL;
    }

    /**
     * Creates database table for cache data.
     */
    public function createCacheTableCliAction()
    {
        $std = new Std();
        $type = $std->read('database type [mysql|sqlite]', '(mysql|sqlite)');

        if ($this->createTable($type, 'cache')) {
            echo 'Cache table successfully created.' . PHP_EOL;
            return;
        }
        echo 'Problems creating cache table!' . PHP_EOL;
    }

    /**
     * @param string $type
     * @param string $for
     *
     * @return bool
     * @throws \DomainException
     */
    protected function createTable($type, $for)
    {
        $type = trim($type);

        try {
            $pdo = $file = null;

            switch ($for) {
                case 'cache':
                    $pdo = Factory::get(Config::get('cache.database'));
                    $file = 'create-cache-table-' . $type . '.sql';
                    break;
                case 'session':
                    $pdo = Factory::get(Config::get('session.database'));
                    $file = 'create-session-table-' . $type . '.sql';
                    break;
            }

            $file = str_replace('/', DS, BASE_PATH . 'pimf-framework/core/Pimf/_database/' . $file);

            return $pdo->exec(file_get_contents(new File($file))) || print_r($pdo->errorInfo(), true);

        } catch (\PDOException $pdoe) {
            throw new Bomb($pdoe->getMessage());
        }
    }
}
