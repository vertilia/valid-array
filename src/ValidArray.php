<?php

declare(strict_types=1);

namespace Vertilia\ValidArray;

use ArrayObject;
use TypeError;

/**
 * Array object with predefined filters that filter data on insertion. Only keys with defined filters may be set.
 * Filters are defined on object instantiation and cannot be modified.
 * Filtering capacities are using standard php extension ext_filter.
 *
 * @see https://php.net/filter_var_array
 */
class ValidArray extends ArrayObject
{
    const FILTER_EXTENDED_CALLBACK = -1;
    const FILTER_INSTANCE_OF = -2;

    protected array $filters = [];

    /**
     * @param array $filters filtering structure as defined for filter_var_array() php function, ex: {
     *  "email": FILTER_VALIDATE_EMAIL,
     *  "id": {
     *      "filter": FILTER_VALIDATE_INT,
     *      "flags": FILTER_REQUIRE_SCALAR,
     *      "options": {"min_range": 1}
     *  },
     *  "addr": {
     *      "filter": FILTER_VALIDATE_IP,
     *      "flags": FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6,
     *      "options": {"default": "0.0.0.0"}
     *  },
     *  ...
     * }
     * @param array $args_raw raw data to validate on array initialization
     * @see https://php.net/filter_var_array
     */
    public function __construct(array $filters, array $args_raw = [])
    {
        // set filters
        $this->filters = $filters;

        // if filters provided, filter the arguments
        if (!empty($this->filters)) {
            $validated = filter_var_array($args_raw, $this->filters) ?: [];

            foreach ($validated as $key => &$valid) {
                $filter = $this->filters[$key];

                if (!array_key_exists($key, $args_raw)) {
                    // VA-addition: use default value for missing arguments
                    $valid = $this->getDefault($key);
                    continue;
                }

                switch ($filter['filter'] ?? $filter) {
                    case self::FILTER_EXTENDED_CALLBACK:
                        $valid = $this->filterExtendedCallback($key, $args_raw[$key], (array)$filter, $valid);
                        break;

                    case self::FILTER_INSTANCE_OF:
                        $valid = $this->filterInstanceOf($key, $args_raw[$key], (array)$filter, $valid);
                        break;
                }
            }

            parent::__construct($validated);
        } else {
            parent::__construct();
        }
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param array $options
     * @param mixed $pre_valid
     * @return mixed
     * @throws TypeError
     */
    protected function filterExtendedCallback(string $key, $value, array $options, $pre_valid)
    {
        // parameters mismatch
        if (!is_array($options['options']) or !isset($options['options']['callback'])) {
            throw new TypeError('"callback" option must be set for FILTER_EXTENDED_CALLBACK filter');
        }

        // flags mismatch
        if (false === $pre_valid or null === $pre_valid) {
            return $pre_valid;
        }

        $valid = (is_array($pre_valid) && !is_array($value)) ? [$value] : $value;

        $callback = $options['options']['callback'];
        if (is_array($valid)) {
            $default = $this->getDefault($key, false);
            array_walk_recursive($valid, function (&$valid_element) use ($callback, $default) {
                $cb = $callback($valid_element);
                $valid_element = false === $cb ? $default : $cb;
            });
        } else {
            $cb = ($callback)($valid);
            $valid = false === $cb ? $this->getDefault($key, false) : $cb;
        }

        return $valid;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param array $options
     * @param mixed $pre_valid
     * @return mixed
     * @throws TypeError
     */
    protected function filterInstanceOf(string $key, $value, array $options, $pre_valid)
    {
        // parameters mismatch
        if (!isset($options['options']['class_name'])) {
            throw new TypeError('"class_name" option must be set for FILTER_CLASS filter');
        }

        // flags mismatch
        if (false === $pre_valid or null === $pre_valid) {
            return $pre_valid;
        }

        $valid = (is_array($pre_valid) && !is_array($value)) ? [$value] : $value;

        $class_name = $options['options']['class_name'];
        if (is_array($valid)) {
            $default = $this->getDefault($key, false);
            array_walk_recursive($valid, function (&$valid_element) use ($class_name, $default) {
                if (!is_object($valid_element) or !is_a($valid_element, $class_name)) {
                    $valid_element = $default;
                }
            });
        } elseif (!is_object($valid) or !is_a($valid, $class_name)) {
            $valid = $this->getDefault($key, false);
        }

        return $valid;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Default value in order of preference:
     * - defined as $filter['options']['default'] value, or
     * - null if missing value or error context with FILTER_NULL_ON_FAILURE flag, or
     * - false if error context
     *
     * @param string $key element key name
     * @param bool $as_missing called for missing value or in error context
     * @return false|mixed|null
     */
    public function getDefault(string $key, bool $as_missing = true)
    {
        $filter = $this->filters[$key] ?? null;
        $options = $filter['options'] ?? null;
        if (is_array($options) and array_key_exists('default', $options)) {
            return $filter['options']['default'];
        } elseif ($as_missing || (($filter['flags'] ?? FILTER_FLAG_NONE) & FILTER_NULL_ON_FAILURE)) {
            return null;
        } else {
            return false;
        }
    }

    /**
     * Set argument by filtering it if corresponding filter is set. Ignore $value if filter for $key is not defined.
     * Treat null value as a missing argument.
     *
     * @param string $key
     * @param mixed $value
     */
    public function offsetSet($key, $value): void
    {
        // exit if filter for $index is not defined
        if (!isset($this->filters[$key])) {
            return;
        }

        // prepare local variables
        if (is_array($this->filters[$key])) {
            $filter = $this->filters[$key]['filter'] ?? FILTER_DEFAULT;
            $options = $this->filters[$key];
        } else {
            $filter = $this->filters[$key];
            $options = 0;
        }

        $valid = filter_var($value, $filter < 0 ? FILTER_DEFAULT : $filter, $options);

        // filter the argument
        switch ($filter) {
            case self::FILTER_EXTENDED_CALLBACK:
                $valid = $this->filterExtendedCallback($key, $value, (array)$options, $valid);
                break;

            case self::FILTER_INSTANCE_OF:
                $valid = $this->filterInstanceOf($key, $value, (array)$options, $valid);
                break;
        }

        parent::offsetSet($key, $valid);
    }

    public function offsetUnset($key): void
    {
        if (array_key_exists($key, $this->filters)) {
            parent::offsetSet($key, $this->getDefault($key));
        } else {
            parent::offsetUnset($key);
        }
    }
}
