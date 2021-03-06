<?php
/**
 * Pimf
 *
 * @copyright Copyright (c)  Gjero Krsteski (http://krsteski.de)
 * @license   http://opensource.org/licenses/MIT MIT
 */
namespace Pimf;

use Pimf\Util\Character as Str;

/**
 * URL
 *
 * <code>
 *    // create a URL to a location within the application
 *    $url = Url::make('user/profile');
 *
 *    // create a HTTPS URL to a location within the application
 *    $url = Url::make('user/profile', true);
 * </code>
 *
 * @package Pimf
 * @author  Gjero Krsteski <gjero@krsteski.de>
 */
class Url
{
    /**
     * The cached base URL.
     *
     * @var string
     */
    public static $base;

    /**
     * Current page URL
     *
     * @var string
     */
    private static $url;

    /**
     * Is current application running under HTTPS protocol?
     *
     * @var boolean
     */
    private static $isHttps;

    /**
     * @param string  $url
     * @param boolean $isHttps
     */
    public static function setup($url, $isHttps)
    {
        self::$url = $url;
        self::$isHttps = $isHttps;
    }

    /**
     * Get the full URI including the query string.
     *
     * @return string
     */
    public static function full()
    {
        return static::make(Uri::full());
    }

    /**
     * Get the full URL for the current request.
     *
     * @return string
     */
    public static function current()
    {
        return static::make(Uri::current(), null, false);
    }

    /**
     * Get the URL for the application root.
     *
     * @param null|bool $https
     *
     * @return string
     */
    public static function home($https = null)
    {
        return static::make('/', $https);
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
        $base = self::$url;
        $url = Config::get('app.url');

        if (!empty($url)) {
            $base = $url;
        }

        return (static::$base = $base);
    }

    /**
     * Generate an application URL.
     *
     * @param string    $url
     * @param null|bool $https
     * @param bool      $asset
     *
     * @return string
     */
    public static function make($url = '', $https = null, $asset = false)
    {
        $url = trim($url, '/');

        if (static::valid($url)) {
            return $url;
        }

        $root = self::format($https, $asset);

        return rtrim($root, '/') . '/' . ltrim($url, '/');
    }

    /**
     * Computes the URl method
     *
     * @param null|bool $https
     * @param bool      $asset
     *
     * @return string
     */
    private static function format($https = null, $asset = false)
    {
        $root = static::base();

        if ($asset === false) {
            $root .= '/' . Config::get('app.index');
        }

        // Unless $https is specified we set https for all secure links.
        if (is_null($https)) {
            $https = self::$isHttps;
        }

        // disable SSL on all framework generated links to make it more
        // convenient to work with the site while developing locally.
        if ($https && Config::get('ssl')) {
            return preg_replace('~http://~', 'https://', $root, 1);
        }

        return preg_replace('~https://~', 'http://', $root, 1);
    }

    /**
     * Generate an application URL with HTTPS.
     *
     * @param string $url
     *
     * @return string
     */
    public static function asHttps($url = '')
    {
        return static::make($url, true);
    }

    /**
     * Generate an application URL to an asset.
     *
     * @param  string $url
     * @param  bool   $https
     *
     * @return string
     */
    public static function makeForAsset($url, $https = null)
    {
        if (static::valid($url) || static::valid('http:' . $url)) {
            return $url;
        }

        $app = Config::get('app');
        $root = (!empty($app['asset_url'])) ? $app['asset_url'] : false;

        // shoot us through a different server or third-party content delivery network.
        if (is_string($root)) {
            return rtrim($root, '/') . '/' . ltrim($url, '/');
        }

        $url = static::make($url, $https, true);

        // we do not need to come through the front controller.
        if (!empty($app['index'])) {
            $url = str_replace($app['index'] . '/', '', $url);
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

        return (bool) filter_var($url, FILTER_VALIDATE_URL);
    }

    /**
     * Get cleaner URLs or old-fashioned » RFC 3986 URL-query string.
     *
     * @param string $route controller/action
     * @param array  $params
     * @param null   $https
     * @param bool   $asset
     *
     * @return string
     */
    public static function compute($route = '', array $params = array(), $https = null, $asset = false)
    {
        // if your application should work with RFC 3986 URL-query strings
        if (Config::get('app.routeable') === false) {

            list($controller, $action) = explode('/', $route);

            $params = array_merge(compact('controller', 'action'), $params);

            return Str::ensureTrailing('/', self::format($https, $asset)) . '?' . http_build_query($params, null, '&');
        }

        // otherwise PIMF will serve you cleaner URLs
        $slug = implode('/', $params);

        if (!empty($slug)) {
            $slug = '/' . $slug;
        }

        return self::make($route, $https, $asset) . $slug;
    }
}
