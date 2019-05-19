<?php
declare(strict_types=1);

namespace Vertilia\ValidArray;

interface MutableFiltersInterface
{
    /**
     * Resets ValidArray filters and revalidates data
     *
     * @param array $filters
     * @param bool $add_empty
     * @return \Vertilia\ValidArray\ValidArray
     */
    public function setFilters(array $filters, bool $add_empty = true);

    /**
     * Adds / replaces filters to ValidArray, revalidates data for replaced filters
     *
     * @param array $filters
     * @return \Vertilia\ValidArray\ValidArray
     */
    public function addFilters(array $filters);
}
