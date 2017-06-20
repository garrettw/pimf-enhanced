<?php
/**
 * Pimf
 *
 * @copyright Copyright (c)  Gjero Krsteski (http://krsteski.de)
 * @license   http://opensource.org/licenses/MIT MIT License
 */
namespace Pimf;

/**
 * A well-known object that other objects can use to find common objects and services.
 *
 * @package Pimf
 * @author  Gjero Krsteski <gjero@krsteski.de>
 */
class Config implements \ArrayAccess
{

    /**
     * The temporary storage for the accumulator.
     *
     * @var \ArrayObject
     */
    protected $battery;

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->battery = new \ArrayObject(
            $config,
            \ArrayObject::STD_PROP_LIST & \ArrayObject::ARRAY_AS_PROPS
        );
    }

    /**
     * Get an item from an array using "dot" notation.
     *
     * @param string|integer $index The index or identifier.
     * @param mixed          $default
     *
     * @return mixed|null
     */
    public function get($index, $default = null)
    {
        if (isset($this->battery[$index])) {
            return $this->battery[$index];
        }

        $array = $this->battery->getArrayCopy();

        foreach ((array)explode('.', $index) as $segment) {

            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }

            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * Responds to: isset($config['index'])
     *
     * @param string|integer $offset The index or identifier
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return ($this->get($offset, null) !== null);
    }

    /**
     * Responds to: $config['index']
     *
     * @param string|integer $offset The index or identifier
     *
     * @return mixed The value of the specified config field
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * Responds to: $config['index'] = 'something';
     *
     * @param string|integer $offset The index or identifier
     * @param mixed $value The value of the specified config field
     *
     * @throws \LogicException Object is immutable
     */
    public function offsetSet($offset, $value)
    {
        throw new \LogicException('Config objects are immutable');
    }

    /**
     * Responds to: unset($config['index']);
     *
     * @param string|integer $offset The index or identifier
     *
     * @throws \LogicException Object is immutable
     */
    public function offsetUnset($offset)
    {
        throw new \LogicException('Config objects are immutable');
    }
}
