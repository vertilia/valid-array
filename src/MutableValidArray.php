<?php
declare(strict_types=1);

namespace Vertilia\ValidArray;

/**
 * Mutable version of ValidArray where filters may be reset or updated. Existing values are then revalidated.
 *
 * @see https://php.net/filter_var_array
 */
class MutableValidArray extends ValidArray implements MutableFiltersInterface
{
    /**
     * Sets new filters for data. Revalidates current values. Current values not present in $filters will be unset.
     *
     * @param array $filters filtering structure as defined for filter_var_array() php function
     * @param bool $add_empty whether to add missing values as NULL
     * @return MutableValidArray $this
     * @see https://php.net/filter_var_array
     */
    public function setFilters(array $filters, bool $add_empty = true): MutableValidArray
    {
        parent::__construct($filters, (array)$this, $add_empty);

        return $this;
    }

    /**
     * Adds / replaces filters with new values. Revalidates current values (for new filters only).
     *
     * @param array $filters filters descriptions to add to existing structure
     * @return MutableValidArray $this
     */
    public function addFilters(array $filters): MutableValidArray
    {
        $this->filters = array_replace($this->filters, $filters);

        foreach ($filters as $k => $v) {
            if (array_key_exists($k, $this)) {
                // revalidate existing value;
                $this[$k] = $this[$k];
            }
        }

        return $this;
    }
}
