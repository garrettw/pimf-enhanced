<?php
/**
 * Pimf
 *
 * @copyright Copyright (c)  Gjero Krsteski (http://krsteski.de)
 * @license   http://opensource.org/licenses/MIT MIT License
 */

namespace Pimf\Session\Storages;

use Pimf\Util\Character;

/**
 * @package Session_Storages
 * @author  Gjero Krsteski <gjero@krsteski.de>
 */
abstract class Storage
{
    /**
     * Load a session from storage by a given ID.
     * If no session is found for the id, null will be returned.
     *
     * @param string $key
     *
     * @return array|null
     */
    abstract public function load($key);

    /**
     * Save a given session to storage.
     *
     * @param array $session
     * @param array $config
     * @param bool  $exists
     *
     * @return void
     */
    abstract public function save($session, $config, $exists);

    /**
     * Delete a session from storage by a given ID.
     *
     * @param string $key
     *
     * @return void
     */
    abstract public function delete($key);

    /**
     * Create a fresh session array with a unique ID.
     *
     * @return array
     */
    public function fresh()
    {
        return ['id' => $this->newId(), 'data' => [':new:' => [], ':old:' => []]];
    }

    /**
     * Get a new session ID that isn't assigned to any current session.
     *
     * @return string
     */
    public function newId()
    {
        // just return any string since the Cookie storage has no idea.
        if ($this instanceof \Pimf\Session\Storages\Cookie) {
            return Character::random(40);
        }

        // we'll find an random ID here.
        do {
            $session = $this->load($key = Character::random(40));
        } while ($session !== null);

        return $key;
    }
}
