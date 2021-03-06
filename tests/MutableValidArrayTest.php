<?php

namespace Vertilia\ValidArray;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Vertilia\ValidArray\MutableValidArray
 */
class MutableValidArrayTest extends TestCase
{
    /**
     * @covers ::setFilter()
     */
    public function testSetFilter()
    {
        $valid = new MutableValidArray(
            ['name' => \FILTER_SANITIZE_STRING],
            ['name' => 'value']
        );
        $this->assertInstanceOf(ValidArray::class, $valid);
        $this->assertInstanceOf(MutableFiltersInterface::class, $valid);
        $this->assertEquals('value', $valid['name']);

        // set new filter, revalidate value (add_empty == true)
        $valid->setFilters(['name' => \FILTER_VALIDATE_INT, 'new' => \FILTER_DEFAULT]);
        $this->assertCount(2, $valid);
        $this->assertTrue(\array_key_exists('name', $valid));
        $this->assertFalse($valid['name']);

        // empty value added from filter
        $this->assertTrue(\array_key_exists('new', $valid));
        $this->assertNull($valid['new']);

        // empty value not added (add_empty == false)
        $valid->setFilters(['name' => \FILTER_VALIDATE_INT, 'new2' => \FILTER_DEFAULT], false);
        $this->assertCount(1, $valid);
        $this->assertTrue(\array_key_exists('name', $valid));
    }

    /**
     * @covers ::addFilter()
     */
    public function testAddFilter()
    {
        $valid = new MutableValidArray(
            ['name' => \FILTER_SANITIZE_STRING],
            ['name' => 'value']
        );
        $this->assertCount(1, $valid);

        // new value not added (no add_empty mechanism in place)
        $valid->addFilters(['new' => \FILTER_DEFAULT, 'new2' => \FILTER_VALIDATE_INT]);
        $this->assertCount(1, $valid);
        $this->assertFalse(\array_key_exists('new', $valid));
        $this->assertFalse(\array_key_exists('new2', $valid));

        $valid['new'] = 'new_value';
        $valid['new2'] = '5';
        $this->assertCount(3, $valid);
        $this->assertTrue(\array_key_exists('new', $valid));
        $this->assertEquals('new_value', $valid['new']);
        $this->assertTrue(\array_key_exists('new2', $valid));
        $this->assertEquals(5, $valid['new2']);
    }
}
