<?php
/**
 * Pimf
 *
 * @copyright Copyright (c)  Gjero Krsteski (http://krsteski.de)
 * @license   http://opensource.org/licenses/MIT MIT License
 */

namespace Pimf;

/**
 * @package Pimf
 * @author  Gjero Krsteski <gjero@krsteski.de>
 */
class Param implements \ArrayAccess
{
    /**
     * @var \ArrayObject
     */
    protected $data;

    /**
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->data = new \ArrayObject(
            $data,
            \ArrayObject::STD_PROP_LIST & \ArrayObject::ARRAY_AS_PROPS
        );
    }

    /**
     * @return array
     */
    public function getAll()
    {
        return (array)$this->data->getArrayCopy();
    }

    /**
     * @param string      $index
     * @param null|string $defaultValue
     * @param bool        $filtered If you trust foreign input introduced to your PHP code - set to FALSE!
     *
     * @return mixed
     */
    public function get($index, $defaultValue = null, $filtered = true)
    {
        if (!$this->offsetExists($index)) {
            return $defaultValue;
        }

        $rawData = $this->offsetGet($index);

        if ($filtered === false) {
            return $rawData;
        }

        // pretty high-level filtering here...
        if (!\is_array($rawData)) {
            return \Pimf\Util\Character\Clean::xss($rawData);
        }

        return \array_map(
            function ($value) {
                return \Pimf\Util\Character\Clean::xss($value);
            }, $rawData
        );
    }

    /**
     * Responds to: isset($param['index'])
     *
     * @param string|integer $offset The index or identifier
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $this->data->offsetExists($offset);
    }

    /**
     * Responds to: $param['index']
     *
     * @param string|integer $offset The index or identifier
     *
     * @return mixed The value at the specified array index
     */
    public function offsetGet($offset)
    {
        return $this->data->offsetGet($offset);
    }

    /**
     * Responds to: $param['index'] = 'something';
     *
     * @param string|integer $offset The index or identifier
     * @param mixed $value The value at the specified array index
     *
     * @throws \LogicException Object is immutable
     */
    public function offsetSet($offset, $value)
    {
        throw new \LogicException('Param objects are immutable');
    }

    /**
     * Responds to: unset($param['index']);
     *
     * @param string|integer $offset The index or identifier
     *
     * @throws \LogicException Object is immutable
     */
    public function offsetUnset($offset)
    {
        throw new \LogicException('Param objects are immutable');
    }
}
