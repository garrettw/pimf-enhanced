<?php
/**
 * Util
 *
 * @copyright Copyright (c)  Gjero Krsteski (http://krsteski.de)
 * @license   http://opensource.org/licenses/MIT MIT License
 */

namespace Pimf\Util;

/**
 * Validator
 *
 * @package Util
 * @author  Gjero Krsteski <gjero@krsteski.de>
 */
class Validator
{
    /**
     * @var array
     */
    protected $errors = [];

    /**
     * @var \Pimf\Param
     */
    protected $attributes;

    /**
     * @param \Pimf\Param $attributes
     */
    public function __construct(\Pimf\Param $attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * @return array
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        return empty($this->errors);
    }

    /**
     * check to see if valid email address
     *
     * @param string $field
     *
     * @return bool
     */
    public function email($field)
    {
        return (filter_var(trim($this->get($field)), FILTER_VALIDATE_EMAIL) !== false) ?: $this->addError($field,
            __FUNCTION__);
    }

    /**
     * Check is a valid IP.
     *
     * @param $field
     *
     * @return bool
     */
    public function ip($field)
    {
        return (filter_var(trim($this->get($field)), FILTER_VALIDATE_IP) !== false) ?: $this->addError($field,
            __FUNCTION__);
    }

    /**
     * Check is a valid URL.
     *
     * @param $field
     *
     * @return bool
     */
    public function url($field)
    {
        return (filter_var(trim($this->get($field)), FILTER_VALIDATE_URL) !== false) ?: $this->addError($field,
            __FUNCTION__);
    }

    /**
     * Check to see if two fields are equal.
     *
     * @param string $field1
     * @param string $field2
     * @param bool   $caseInsensitive
     *
     * @return bool
     */
    public function compare($field1, $field2, $caseInsensitive = false)
    {
        $field1value = $this->get($field1);
        $field2value = $this->get($field2);

        $valid = (strcmp($field1value, $field2value) == 0);

        if ($caseInsensitive) {
            $valid = (strcmp(strtolower($field1value), strtolower($field2value)) == 0);
        }

        return ($valid === true) ?: $this->addError($field1 . "|" . $field2, __FUNCTION__);
    }

    /**
     * Check to see if the length of a field is between two numbers
     *
     * @param string $field
     * @param int    $min
     * @param int    $max
     * @param bool   $inclusive
     *
     * @return bool
     */
    public function lengthBetween($field, $min, $max, $inclusive = false)
    {
        $valid = static::between(strlen(trim($this->get($field))), $min, $max, $inclusive);

        return ($valid === true) ?: $this->addError($field, __FUNCTION__);
    }

    /**
     * Check to see if there is punctuation
     *
     * @param string $field
     *
     * @return bool
     */
    public function punctuation($field)
    {
        return (preg_match("/[^\w\s\p{P}]/", '' . $this->get($field)) > 0) ? $this->addError($field, __FUNCTION__) : true;
    }

    /**
     * length functions on a field takes <, >, ==, <=, and >= as operators.
     *
     * @param string $field
     * @param string $operator
     * @param int    $length
     *
     * @return bool
     */
    public function length($field, $operator, $length)
    {
        return $this->middleware($field, strlen(trim($this->get($field))), $operator, $length);
    }

    /**
     * Number value functions takes <, >, ==, <=, and >= as operators.
     *
     * @param string     $field
     * @param string     $operator
     * @param string|int $value
     *
     * @return bool
     */
    public function value($field, $operator, $value)
    {
        return $this->middleware($field, $this->get($field), $operator, $value);
    }

    /**
     * Check if a number value is between $max and $min
     *
     * @param string $field
     * @param int    $min
     * @param int    $max
     * @param bool   $inclusive
     *
     * @return bool
     */
    public function valueBetween($field, $min, $max, $inclusive = false)
    {
        $valid = static::between($this->get($field), $min, $max, $inclusive);

        return ($valid === true) ?: $this->addError($field, __FUNCTION__);
    }

    /**
     * Check if a field contains only decimal digit
     *
     * @param string $field
     *
     * @return bool
     */
    public function digit($field)
    {
        return (ctype_digit((string)$this->get($field)) === true) ?: $this->addError($field, __FUNCTION__);
    }

    /**
     * Check if a field contains only alphabetic characters
     *
     * @param string $field
     *
     * @return bool
     */
    public function alpha($field)
    {
        return (ctype_alpha((string)$this->get($field)) === true) ?: $this->addError($field, __FUNCTION__);
    }

    /**
     * Check if a field contains only alphanumeric characters
     *
     * @param string $field
     *
     * @return bool
     */
    public function alphaNumeric($field)
    {
        return (ctype_alnum((string)$this->get($field)) === true) ?: $this->addError($field, __FUNCTION__);
    }

    /**
     * Check if field is a date by specified format.
     *
     * @param string $field
     * @param string $format Find formats here http://www.php.net/manual/en/function.date.php
     *
     * @return boolean
     */
    public function date($field, $format)
    {
        $fieldValue = $this->get($field);

        try {

            $date = new \DateTime($fieldValue);

            return $fieldValue === $date->format($format);
        } catch (\Exception $exception) {
            return $this->addError($field, __FUNCTION__);
        }
    }

    /**
     * @param string $field
     * @param int    $error
     *
     * @return boolean
     */
    protected function addError($field, $error)
    {
        $this->errors = array_merge_recursive($this->errors, array($field => $error));

        return false;
    }

    /**
     * @param string $attribute
     *
     * @return string
     * @throws \OutOfBoundsException If attribute not at range
     */
    protected function get($attribute)
    {
        if (!$value = $this->attributes->get($attribute, null, false)) {
            throw new \OutOfBoundsException('no attribute with name "' . $attribute . '" set');
        }

        return $value;
    }

    /**
     * @param string         $fieldName
     * @param string         $comparing
     * @param string         $operator
     * @param string|integer $expecting
     *
     * @return bool
     */
    protected function middleware($fieldName, $comparing, $operator, $expecting)
    {
        if (in_array($operator, array("<", ">", "==", "<=", ">="), true)) {
            $func = function($a, $b) use ($operator) {
                switch ($operator){
                    case "<":
                        return ($a < $b);
                    case ">":
                        return ($a > $b);
                    case "==":
                        return ($a == $b);
                    case ">=":
                        return ($a >= $b);
                    case "<=":
                        return ($a <= $b);
                }
            };

            return ($func($comparing, $expecting) === true) ?: $this->addError($fieldName, $operator);
        }

        return false;
    }

    protected static function between($fieldValue, $min, $max, $inclusive)
    {
        if ($inclusive) {
            return ($fieldValue <= $max && $fieldValue >= $min);
        }

        return ($fieldValue < $max && $fieldValue > $min);
    }
}
