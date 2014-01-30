<?php
/**
 * Pimf
 *
 * PHP Version 5
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * http://krsteski.de/new-bsd-license/
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to gjero@krsteski.de so we can send you a copy immediately.
 *
 * @copyright Copyright (c)  Gjero Krsteski (http://krsteski.de)
 * @license http://krsteski.de/new-bsd-license New BSD License
 */

namespace Pimf;
use Pimf\Registry, Pimf\Session\Payload, Pimf\Cookie, Pimf\Session\Storages as Storage;

/**
 * Using the session
 *
 * <code>
 *
 *    // Retrieve the session instance and get an item
 *    Session::instance()->get('name');
 *
 *    // Retrieve the session instance and place an item in the session
 *    Session::instance()->put('name', 'Robin');
 *
 *    // Retrieve a value from the session
 *    $value = Session::get('name');
 *
 *    // Write a value to the session storage
 *    $value = Session::put('name', 'Robin');
 *
 *    // Equivalent statement using the "instance" method
 *    $value = Session::instance()->put('name', 'Robin');
 *
 * </code>
 *
 * @package Pimf
 * @author Gjero Krsteski <gjero@krsteski.de>
 *
 * @method static save()
 */
class Session
{
  /**
   * The session singleton instance for the request.
   * @var Payload
   */
  public static $instance;

  /**
   * The third-party storage registrar.
   * @var array
   */
  public static $farm = array();

  /**
   * The string name of the CSRF token stored in the session.
   * @var string
   */
  const CSRF = 'csrf_token';

  /**
   * Create the session payload and load the session.
   * @return void
   */
  public static function load()
  {
    $conf = Registry::get('conf');

    static::start($conf['session']['storage']);

    static::$instance->load(Cookie::get($conf['session']['cookie']));
  }

  /**
   * Create the session payload instance for the request.
   * @param string $storage
   * @return void
   */
  public static function start($storage)
  {
    static::$instance = new Payload(static::factory($storage));
  }

  /**
   * Create a new session storage instance.
   *
   * @param $storage
   *
   * @return Storage\Apc|Storage\Cookie|Storage\Dba|Storage\File|Storage\Memcached|Storage\Memory|Storage\Pdo|Storage\Redis
   * @throws \RuntimeException
   */
  public static function factory($storage)
  {
    if (isset(static::$farm[$storage])) {
      $resolver = static::$farm[$storage];
      return $resolver();
    }

    $conf = Registry::get('conf');

    switch ($storage) {
      case 'apc':
        return new Storage\Apc(Cache::storage('apc'));

      case 'cookie':
        return new Storage\Cookie();

      case 'file':
        return new Storage\File($conf['session']['storage_path']);

      case 'pdo':
        return new Storage\Pdo(\Pimf\Pdo\Factory::get($conf['session']['database']));

      case 'memcached':
        return new Storage\Memcached(Cache::storage('memcached'));

      case 'memory':
        return new Storage\Memory();

      case 'redis':
        return new Storage\Redis(Cache::storage('redis'));

      case 'dba':
        return new Storage\Dba(Cache::storage('dba'));

      default:
        throw new \RuntimeException("Session storage [$storage] is not supported.");
    }
  }

  /**
   * Retrieve the active session payload instance for the request.
   * @return Payload
   * @throws \RuntimeException
   */
  public static function instance()
  {
    if (static::started()) {
      return static::$instance;
    }

    throw new \RuntimeException("A storage must be set before using the session.");
  }

  /**
   * Determine if session handling has been started for the request.
   *
   * @return bool
   */
  public static function started()
  {
    return (static::$instance !== null);
  }

  /**
   * Register a third-party cache storage.
   * @param $storage
   * @param callable $resolver
   */
  public static function extend($storage, \Closure $resolver)
  {
    static::$farm[$storage] = $resolver;
  }

  /**
   * Magic Method for calling the methods on the session singleton instance.
   * @param $method
   * @param $parameters
   *
   * @return mixed
   */
  public static function __callStatic($method, $parameters)
  {
    return call_user_func_array(
      array(
        static::instance(),
        $method
      ), $parameters
    );
  }
}