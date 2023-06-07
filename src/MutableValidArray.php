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
     * Reset data filters. Revalidate current values. Current values not present in $filters will be removed.
     *
     * @param array $filters filtering structure as defined for filter_var_array() php function
     * @return MutableValidArray $this
     * @see https://php.net/filter_var_array
     */
    public function setFilters(array $filters): MutableValidArray
    {
        foreach (array_diff_key($this->filters, $filters) as $k => $_) {
            unset($this->filters[$k], $this->missing[$k], $this[$k]);
        }
        $this->addFilters($filters);

        return $this;
    }

    /**
     * Add / replace filters. Revalidate current data values for updated filters. Set default values for new values.
     *
     * @param array $filters filters descriptions to add to existing structure
     * @return MutableValidArray $this
     * @see https://php.net/filter_var_array
     */
    public function addFilters(array $filters): MutableValidArray
    {
        foreach ($filters as $k => $f) {
            if (!array_key_exists($k, $this->filters)) {
                $this->missing[$k] = true;
            }
            $this->filters[$k] = $f;
            $this->offsetSet(
                $k,
                empty($this->missing[$k])
                    ? $this[$k]
                    : $this->getDefault($k)
            );
        }

        return $this;
    }
}
