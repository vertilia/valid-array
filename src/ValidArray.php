<?php

declare(strict_types=1);

namespace Vertilia\ValidArray;

use ArrayObject;

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

    protected array $filters = [];
    protected array $missing = [];

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
        $this->missing = [];

        // if filters provided, filter the arguments
        if (!empty($this->filters)) {
            $validated = filter_var_array($args_raw, $this->filters) ?: [];

            foreach ($validated as $k => &$v) {
                $filter = $this->filters[$k];

                // VA-addition: use default value for missing arguments
                if (!array_key_exists($k, $args_raw)) {
                    $this->missing[$k] = true;
                    $v = $this->getDefault($k);
                }

                // VA-addition: FILTER_EXTENDED_CALLBACK
                if (self::FILTER_EXTENDED_CALLBACK === ($filter['filter'] ?? FILTER_DEFAULT)
                    and array_key_exists($k, $args_raw)
                    and is_array($filter['options'] ?? null)
                    and isset($filter['options']['callback'])
                ) {
                    $v = filter_var($v, FILTER_CALLBACK, ['options' => $filter['options']['callback']]);
                }
            }

            parent::__construct($validated);
        } else {
            parent::__construct();
        }
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getDefault(string $key)
    {
        if (is_array($this->filters[$key] ?? null)
            and is_array($this->filters[$key]['options'] ?? null)
            and array_key_exists('default', $this->filters[$key]['options'])
        ) {
            return $this->filters[$key]['options']['default'];
        }

        return null;
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
        // treat null value as missing without default
        if (null === $value) {
            parent::offsetSet($key, null);
            $this->missing[$key] = true;
            return;
        }

        // if filter for $index is defined, filter the argument
        if (isset($this->filters[$key])) {
            if (is_array($this->filters[$key])) {
                $filter = $this->filters[$key]['filter'] ?? FILTER_DEFAULT;
                $options = $this->filters[$key];
            } else {
                $filter = $this->filters[$key];
                $options = 0;
            }

            if (self::FILTER_EXTENDED_CALLBACK === $filter
                and $options
                and is_array($options['options'] ?? null)
                and isset($options['options']['callback'])
            ) {
                // VA-addition: FILTER_EXTENDED_CALLBACK
                $pre_valid = filter_var($value, FILTER_DEFAULT, $options);
                $valid = filter_var($pre_valid, FILTER_CALLBACK, ['options' => $options['options']['callback']]);
            } else {
                $valid = filter_var($value, $filter, $options);
            }

            parent::offsetSet($key, $valid);
        }

        unset($this->missing[$key]);
    }

    public function offsetUnset($key): void
    {
        if (array_key_exists($key, $this->filters)) {
            $this->missing[$key] = true;
            parent::offsetSet($key, $this->getDefault($key));
        } else {
            parent::offsetUnset($key);
        }
    }
}
