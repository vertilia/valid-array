<?php
declare(strict_types=1);

namespace Vertilia\ValidArray;

/**
 * Array object with predefined filters that filter data on insertion. Only keys with defined filters may be set.
 * Filters are defined on object instantiation and cannot be modified.
 * Filtering capacities are using standard php extention ext_filter.
 *
 * @see https://php.net/filter_var_array
 */
class ValidArray extends \ArrayObject
{
    /** @var array */
    protected $filters = [];

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
     * @param bool $add_empty whether to add missing values as NULL
     * @see https://php.net/filter_var_array
     */
    public function __construct(array $filters, array $args_raw = [], bool $add_empty = true)
    {
        // set filters
        $this->filters = $filters;

        // if raw arguments provided, filter them
        if (!empty($args_raw)) {
            $validated = \filter_var_array((array)$args_raw, $this->filters, $add_empty);
            if (\is_array($validated)) {
                foreach ($validated as $k => &$v) {
                    if (!isset($v) and isset($this->filters[$k]['options']['default'])) {
                        $v = $this->filters[$k]['options']['default'];
                    }
                }

                // store validated
                parent::__construct($validated);
            }
        }
    }

    /**
     * Sets argument by filtering it if corresponding filter is set. Ignores if filter not set.
     *
     * @param string $index
     * @param mixed $value
     */
    public function offsetSet($index, $value)
    {
        // if filter for $index is defined, filter the argument
        if (isset($this->filters[$index])) {
            $validated = \filter_var(
                $value,
                isset($this->filters[$index]['filter']) ? $this->filters[$index]['filter'] : $this->filters[$index],
                is_array($this->filters[$index]) ? $this->filters[$index] : null
            );
            parent::offsetSet($index, $validated);
        }
    }
}
