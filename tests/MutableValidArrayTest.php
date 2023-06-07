<?php

namespace Vertilia\ValidArray;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass MutableValidArray
 */
class MutableValidArrayTest extends TestCase
{
    /**
     * @covers ::setFilter()
     */
    public function testSetFilter()
    {
        $valid = new MutableValidArray(
            ['name' => FILTER_DEFAULT],
            ['name' => 'value']
        );
        $this->assertInstanceOf(ValidArray::class, $valid);
        $this->assertInstanceOf(MutableFiltersInterface::class, $valid);
        $this->assertEquals('value', $valid['name']);

        // set new filter, revalidate value
        $valid->setFilters([
            'name' => FILTER_VALIDATE_INT,
            'new' => FILTER_VALIDATE_INT,
        ]);
        $this->assertCount(2, $valid);
        $this->assertArrayHasKey('name', $valid);
        $this->assertFalse($valid['name'], 'value did not pass revalidation, set to false');
        $this->assertArrayHasKey('new', $valid);
        $this->assertNull($valid['new']);

        // old values removed, new added
        $valid->setFilters([
            'name' => FILTER_VALIDATE_INT,
            'new2' => FILTER_DEFAULT,
        ]);
        $this->assertCount(2, $valid);
        $this->assertArrayHasKey('name', $valid);
        $this->assertFalse($valid['name']);
        $this->assertArrayNotHasKey('new', $valid);
        $this->assertArrayHasKey('new2', $valid);
        $this->assertNull($valid['new2']);
    }

    /**
     * @covers ::addFilter()
     */
    public function testAddFilter()
    {
        $valid = new MutableValidArray(
            ['name' => FILTER_DEFAULT, 'new' => FILTER_DEFAULT],
            ['name' => 'value']
        );
        $this->assertCount(2, $valid);

        $valid->addFilters(['new' => FILTER_DEFAULT, 'new2' => FILTER_VALIDATE_INT]);
        $this->assertCount(3, $valid);
        $this->assertArrayHasKey('name', $valid);
        $this->assertArrayHasKey('new', $valid);
        $this->assertArrayHasKey('new2', $valid);

        $valid['new'] = 'new_value';
        $valid['new2'] = '5';
        $this->assertCount(3, $valid);
        $this->assertArrayHasKey('new', $valid);
        $this->assertEquals('new_value', $valid['new']);
        $this->assertArrayHasKey('new2', $valid);
        $this->assertEquals(5, $valid['new2']);
    }
}
