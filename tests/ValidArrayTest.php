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
        $valid3 = new ValidArray($filter);
        $valid3[$name] = $value;
        $this->assertTrue(isset($valid3[$name]));
        $this->assertTrue($expected === $valid3[$name]);
        $this->assertCount(1, $valid3);

        // IteratorAggregate
        foreach ($valid3 as $k => $v) {
            $this->assertEquals($k, $name);
            $this->assertEquals($v, $expected);
        }

        // json_encode
        $json = \json_encode($valid3);
        $this->assertEquals(\json_encode([$name => $expected]), $json);

        // ArrayAccess, Countable
        unset($valid3[$name]);
        $this->assertCount(0, $valid3);
    }

    /** data provider */
    public function validArrayProvider()
    {
        $tel_filter = [
            'filter' => \FILTER_VALIDATE_REGEXP,
            'options' => [
                'default' => '+00 (0)0 00 00 00 00',
                'regexp' => '/^\+?\d+(?:[. ()-]{1,2}\d+)*$/',
            ],
        ];

        $callback_filter = [
            'filter' => \FILTER_CALLBACK,
            'options' => function ($v) {
                if (is_string($v) and $v[0] == '_') {
                    return $v;
                } else {
                    return false;
                }
            },
        ];

        $php_net_example_filters = [
            'product_id' => \FILTER_SANITIZE_ENCODED,
            'component' => ['filter' => \FILTER_VALIDATE_INT,
                'flags' => \FILTER_FORCE_ARRAY,
                'options' => ['min_range' => 1, 'max_range' => 10],
            ],
            'versions' => \FILTER_SANITIZE_ENCODED,
            'doesnotexist' => \FILTER_VALIDATE_INT,
            'testscalar' => ['filter' => \FILTER_VALIDATE_INT, 'flags' => \FILTER_REQUIRE_SCALAR],
            'testarray' => ['filter' => \FILTER_VALIDATE_INT, 'flags' => \FILTER_FORCE_ARRAY],
        ];

        return [
            [['name' => \FILTER_SANITIZE_STRING], 'name', 'value', 'value'],
            [['id' => \FILTER_SANITIZE_NUMBER_INT], 'id', 123, '123'],
            [['id' => \FILTER_SANITIZE_NUMBER_INT], 'id', 'string', ''],
            [['url' => \FILTER_VALIDATE_URL], 'url', 'http://a.b.c/d/e.f', 'http://a.b.c/d/e.f'],
            [['url' => \FILTER_VALIDATE_URL], 'url', 'a.b.c/d/e.f', false],
            [['ip' => ['filter'=>\FILTER_VALIDATE_IP, 'flags'=>\FILTER_FLAG_IPV4]], 'ip', '1.2.3.4', '1.2.3.4'],
            [['ip' => ['filter'=>\FILTER_VALIDATE_IP, 'flags'=>\FILTER_FLAG_IPV6]], 'ip', '1.2.3.4', false],
            [['email' => ['filter'=>\FILTER_VALIDATE_EMAIL, 'flags'=>\FILTER_FORCE_ARRAY]], 'email', 'a@b.c', ['a@b.c']],
            [['names' => ['filter'=>\FILTER_SANITIZE_STRING, 'flags'=>\FILTER_REQUIRE_ARRAY]], 'names', ['value1', 'value2'], ['value1', 'value2']],
            [['tel' => $tel_filter], 'tel', '123-02-03', '123-02-03'],
            [['tel' => $tel_filter], 'tel', 'unknown', '+00 (0)0 00 00 00 00'],
            [['val' => $callback_filter], 'val', '_unknown_', '_unknown_'],
            [['val' => $callback_filter], 'val', 'other', false],
            [$php_net_example_filters, 'product_id', 'libgd<script>', 'libgd%3Cscript%3E'],
            [$php_net_example_filters, 'component', '10', [10]],
            [$php_net_example_filters, 'versions', '2.0.33', '2.0.33'],
            [$php_net_example_filters, 'testscalar', ['2', '23', '10', '12'], false],
            [$php_net_example_filters, 'testarray', '2', [2]],
        ];
    }

    public function testValidArrayAddEmpty()
    {
        $valid1 = new ValidArray(['name' => 'value', 'doesnotexist' => true], ['name' => 'value'], false);
        $this->assertCount(1, $valid1);
        $this->assertFalse(\array_key_exists('doesnotexist', $valid1));

        $valid2 = new ValidArray(['name' => 'value', 'doesnotexist' => true], ['name' => 'value']);
        $this->assertCount(2, $valid2);
        $this->assertTrue(\array_key_exists('doesnotexist', $valid2));
        $this->assertNull($valid2['doesnotexist']);
    }
}
