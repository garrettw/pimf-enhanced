<?php
/**
 * Util
 *
 * @copyright Copyright (c)  Gjero Krsteski (http://krsteski.de)
 * @license   http://opensource.org/licenses/MIT MIT License
 */
namespace Pimf\Util\Character;

/**
 * String
 *
 * @package Util_String
 * @author  Gjero Krsteski <gjero@krsteski.de>
 */
class Clean
{
    /**
     * An aggressive cleaning - all tags and stuff inside will be removed.
     *
     * @param string $string The string.
     *
     * @return string
     */
    public static function aggressive($string)
    {
        return (string)preg_replace("/<.*?>/", "", (string)$string);
    }

    /**
     * Cleans against XSS.
     *
     * @param string $string  String to check
     * @param string $charset Character set (default ISO-8859-1)
     *
     * @return string $value Sanitized string
     */
    public static function xss($string, $charset = 'ISO-8859-1')
    {
        $string = Sanitize::removeNullCharacters($string);
        $string = Sanitize::validateStandardCharacterEntities($string);
        $string = Sanitize::validateUTF16TwoByteEncoding($string);
        $string = Sanitize::strangeThingsAreSubmitted($string);
        $string = Sanitize::convertCharacterEntitiesToASCII($string, $charset);
        $string = Sanitize::convertAllTabsToSpaces($string);
        $string = Sanitize::makesPhpTagsSafe($string);
        $string = Sanitize::compactAnyExplodedWords($string);
        $string = Sanitize::removeDisallowedJavaScriptInLinksOrImgTags($string);
        $string = Sanitize::removeJavaScriptEventHandlers($string);
        $string = Sanitize::healNaughtyHTMLElements($string);
        $string = Sanitize::healNaughtyScriptingElements($string);
        $string = Sanitize::removeJavaScriptHardRedirects($string);

        return $string;
    }
}
