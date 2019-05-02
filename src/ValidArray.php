<?php
declare(strict_types=1);

namespace Vertilia\ValidArray;

class ValidArray implements \ArrayAccess, \Countable, \IteratorAggregate
{
    /** @var array */
    protected $filters = [];
    /** @var array */
    protected $args_valid = [];

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
     * @param array $args_raw
     * @see https://php.net/filter_var_array
     */
    public function __construct(array $filters, array $args_raw = [])
    {
        // set filters
        $this->filters = $filters;

        // if raw arguments provided, filter them
        if (!empty($args_raw)) {
            $validated = \filter_var_array((array)$args_raw, $this->filters);
            if (\is_array($validated)) {
                foreach ($validated as $k => &$v) {
                    if (!isset($v) and isset($this->filters[$k]['options']['default'])) {
                        $v = $this->filters[$k]['options']['default'];
                    }
                }

                // store validated
                $this->args_valid = $validated;
            }
        }
    }

    // ArrayAccess interface

    /**
     * Sets argument by filtering it if corresponding filter is set. Unsets if filtering breaks.
     * Ignores if filter not set.
     *
     * @param string $index
     * @param mixed $value
     * @throws \UnexpectedValueException
     */
    public function offsetSet($index, $value)
    {
        // if filter for $index is defined, filter the argument
        if (isset($this->filters[$index])) {
            $validated = \filter_var_array(
                [$index => $value],
                [$index => $this->filters[$index]]
            );
            if (\is_array($validated) and \array_key_exists($index, $validated)) {
                $this->args_valid[$index] = $validated[$index];
            } else {
                unset($this->args_valid[$index]);
            }
        }
    }

    public function offsetGet($index)
    {
        return $this->args_valid[$index] ?? null;
    }

    public function offsetExists($index): bool
    {
        return isset($this->args_valid[$index]);
    }

    public function offsetUnset($index)
    {
        unset($this->args_valid[$index]);
    }

    // Countable interface

    public function count(): int
    {
        return \count($this->args_valid);
    }

    // IteratorAggregate interface

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->args_valid);
    }
}