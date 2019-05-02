<?php

namespace Vertilia\ValidArray;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Vertilia\ValidArray\ValidArray
 */
class ValidArrayTest extends TestCase
{
    /**
     * @dataProvider validArrayProvider
     * @covers ::__construct
     * @covers ::offsetSet
     * @covers ::offsetGet
     * @covers ::offsetExists
     * @covers ::offsetUnset
     * @covers ::count()
     * @covers ::getIterator()
     * @param array $filter
     * @param string $name
     * @param mixed $value
     * @param mixed $expected
     */
    public function testValidArray($filter, $name, $value, $expected)
    {
        $valid1 = new ValidArray($filter, [$name => $value]);
        $this->assertInstanceOf(ValidArray::class, $valid1);

        $this->assertInstanceOf(\ArrayAccess::class, $valid1);
        $this->assertInstanceOf(\Countable::class, $valid1);
        $this->assertInstanceOf(\IteratorAggregate::class, $valid1);
        $this->assertEquals($expected, $valid1[$name]);

        // ArrayAccess, Countable
        $valid = new ValidArray($filter);
        $valid[$name] = $value;
        $this->assertTrue(isset($valid[$name]));
        $this->assertTrue($expected === $valid[$name]);
        $this->assertCount(1, $valid);

        // IteratorAggregate
        foreach ($valid as $k => $v) {
            $this->assertEquals($k, $name);
            $this->assertEquals($v, $expected);
        }

        // ArrayAccess, Countable
        unset($valid[$name]);
        $this->assertCount(0, $valid);
    }

    /** data provider */
    public function validArrayProvider()
    {
        return [
            [['name' => \FILTER_SANITIZE_STRING], 'name', 'value', 'value'],
            [['name' => \FILTER_SANITIZE_NUMBER_INT], 'name', 'string', ''],
            [['name' => \FILTER_VALIDATE_URL], 'name', 'http://a.b.c/d/e.f', 'http://a.b.c/d/e.f'],
            [['name' => \FILTER_VALIDATE_URL], 'name', 'a.b.c/d/e.f', false],
            [['name' => ['filter'=>\FILTER_VALIDATE_IP, 'flags'=>\FILTER_FLAG_IPV4]], 'name', '1.2.3.4', '1.2.3.4'],
            [['name' => ['filter'=>\FILTER_VALIDATE_IP, 'flags'=>\FILTER_FLAG_IPV6]], 'name', '1.2.3.4', false],
            [['name' => ['filter'=>\FILTER_VALIDATE_EMAIL, 'flags'=>\FILTER_FORCE_ARRAY]], 'name', 'abc@def.ghi', ['abc@def.ghi']],
        ];
    }
}
