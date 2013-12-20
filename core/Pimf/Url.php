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
use Pimf\Util\String as Str;

/**
 * URL
 *
 * <code>
 *    // create a URL to a location within the application
 *    $url = Url::to('user/profile');
 *
 *    // create a HTTPS URL to a location within the application
 *    $url = Url::to('user/profile', true);
 * </code>
 *
 * @package Pimf
 * @author Gjero Krsteski <gjero@krsteski.de>
 */
class Url
{
  /**
   * The cached base URL.
   * @var string
   */
  public static $base;

  /**
   * Get the full URI including the query string.
   * @return string
   */
  public static function full()
  {
    return static::to(Uri::full());
  }

  /**
   * Get the full URL for the current request.
   * @return string
   */
  public static function current()
  {
    return static::to(Uri::current(), null, false, false);
  }

  /**
   * Get the URL for the application root.
   *
   * @param  bool $https
   *
   * @return string
   */
  public static function home($https = null)
  {
    return static::to('/', $https);
  }

  /**
   * Get the base URL of the application.
   *
   * @return string
   */
  public static function base()
  {
    if (isset(static::$base)) {
      return static::$base;
    }

    $conf = Registry::get('conf');
    $url = $conf['app']['url'];

    if ($url !== '') {
      $base = $url;
    } else {
      $base = Registry::get('env')->getUrl();
    }

    return static::$base = $base;
  }

  /**
   * Generate an application URL.
   *
   * @param string $url
   * @param bool $https
   * @param bool $asset
   *
   * @return string
   */
  public static function to($url = '', $https = null, $asset = false)
  {
    $url = trim($url, '/');

    if (static::valid($url) or Str::startsWith($url, '#')) {
      return $url;
    }

    $root = static::base();
    $conf = Registry::get('conf');

    if (!$asset) {
      $root .= '/' . $conf['app']['index'];
    }

    // Unless $https is specified we set https for all secure links.
    if (is_null($https)) {
      $https = Registry::get('env')->isHttps();
    }

    // disable SSL on all framework generated links to make it more
    // convenient to work with the site while developing locally.
    if ($https and $conf['ssl']) {
      $root = preg_replace('~http://~', 'https://', $root, 1);
    } else {
      $root = preg_replace('~https://~', 'http://', $root, 1);
    }

    return rtrim($root, '/') . '/' . ltrim($url, '/');
  }

  /**
   * Generate an application URL with HTTPS.
   *
   * @param string $url
   *
   * @return string
   */
  public static function as_https($url = '')
  {
    return static::to($url, true);
  }

  /**
   * Generate an application URL to an asset.
   *
   * @param  string $url
   * @param  bool   $https
   *
   * @return string
   */
  public static function to_asset($url, $https = null)
  {
    if (static::valid($url) or static::valid('http:' . $url)) {
      return $url;
    }

    $conf = Registry::get('conf');
    $root = ($conf['app']['asset_url'] != '') ? $conf['app']['asset_url'] : false;

    // shoot us through a different server or third-party content delivery network.
    if ($root){
      return rtrim($root, '/') . '/' . ltrim($url, '/');
    }

    $url = static::to($url, $https, true);

    // we do not need to come through the front controller.
    if ($conf['app']['index'] !== '') {
      $url = str_replace($conf['app']['index'] . '/', '', $url);
    }

    return $url;
  }

  /**
   * Determine if the given URL is valid.
   *
   * @param  string $url
   *
   * @return bool
   */
  public static function valid($url)
  {
    if (Str::startsWith($url, '//')) {
      return true;
    }

    return filter_var($url, FILTER_VALIDATE_URL) !== false;
  }
}
