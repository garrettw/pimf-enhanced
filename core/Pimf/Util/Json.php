<?php
/**
 * Util
 *
 * @copyright Copyright (c)  Gjero Krsteski (http://krsteski.de)
 * @license   http://opensource.org/licenses/MIT MIT License
 */

namespace Pimf\Util;

/**
 * @package Util
 * @author  Gjero Krsteski <gjero@krsteski.de>
 */
class Json
{
    /**
     * Returns the JSON representation of a value.
     *
     * @param mixed $data
     *
     * @return string
     */
    public static function encode($data)
    {
        if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
            $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } else {
            $json = json_encode($data);
        }

        self::handleError(json_last_error());

        return $json;
    }

    /**
     * Decodes a JSON string.
     *
     * @param string  $jsonString
     * @param boolean $assoc If should be converted into associative array/s.
     *
     * @return mixed
     */
    public static function decode($jsonString, $assoc = false)
    {
        $json = json_decode($jsonString, $assoc);

        self::handleError(json_last_error());

        return $json;
    }

    /**
     * @param int $status
     *
     * @throws \RuntimeException
     */
    protected static function handleError($status)
    {
        $msg = [
            JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
            JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
            JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON',
            // alias for JSON_ERROR_UTF8 due to Availability PHP 5.3.3
            5 => 'Malformed UTF-8 characters, possibly incorrectly encoded',
        ];

        if (isset($msg[$status])) {
            throw new \RuntimeException($msg[$status]);
        }
    }
}
