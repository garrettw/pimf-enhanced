<?php
/**
 * Pimf_View
 *
 * PHP Version 5
 *
 * A comprehensive collection of PHP utility classes and functions
 * that developers find themselves using regularly when writing web applications.
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
 * @copyright Copyright (c) 2010-2011 Gjero Krsteski (http://krsteski.de)
 * @license http://krsteski.de/new-bsd-license New BSD License
 */

/**
 * A view for HAANGA template engine that uses Django syntax - fast and secure template engine for PHP.
 *
 * For use please add the following code to the end of the config.php file:
 *
 * <code>
 *
 * 'view' => array(
 *
 *   'haanga' => array(
 *     'cache'       => true,  // if compilation caching should be used
 *     'debug'       => false, // if set to true, you can display the generated nodes
 *     'auto_reload' => true,  // useful to recompile the template whenever the source code changes
 *  ),
 *
 * ),
 *
 * </code>
 *
 * @link http://haanga.org/documentation
 * @package Pimf_View
 * @author Gjero Krsteski <gjero@krsteski.de>
 */
class Pimf_View_Haanga extends Pimf_View implements Pimf_View_Reunitable
{
  /**
   * The template file extension.
   * @var string
   */
  protected $extension = '.haanga';

  public function __construct()
  {
    parent::__construct();

    $conf = Pimf_Registry::get('conf');

    $options = array(
      'debug'        => Pimf_Util_Value::ensureBoolean($conf['view']['haanga']['debug']),
      'template_dir' => $this->path,
      'autoload'     => Pimf_Util_Value::ensureBoolean($conf['view']['haanga']['auto_reload']),
    );

    if($conf['view']['haanga']['cache'] === true){
      $options['cache_dir'] = $this->path.'/haanga_cache';
    }

    Haanga::configure($options);
  }

  /**
   * Puts the template an the variables together.
   * @return NULL|string|void
   */
  public function reunite()
  {
    return Haanga::Load(
      $this->template . $this->extension,
      $this->data->getArrayCopy()
    );
  }
}