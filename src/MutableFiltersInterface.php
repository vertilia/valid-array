<?php
declare(strict_types=1);

namespace Vertilia\ValidArray;

interface MutableFiltersInterface
{
    /**
     * Reset ValidArray filters and revalidate data
     *
     * @param array $filters
     * @return ValidArray
     */
    public function setFilters(array $filters): ValidArray;

    /**
     * Add / replace filters to ValidArray, revalidate data for replaced filters, set defaults for new elements
     *
     * @param array $filters
     * @return ValidArray
     */
    public function addFilters(array $filters): ValidArray;
}
