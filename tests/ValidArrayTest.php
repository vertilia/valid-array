<?php

namespace Vertilia\ValidArray;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass ValidArray
 */
class ValidArrayTest extends TestCase
{
    /**
     * @dataProvider providerValidArray
     * @covers ::__construct
     * @covers ::offsetSet
     * @covers ::offsetGet
     * @covers ::offsetExists
     * @covers ::offsetUnset
     * @covers ::count()
     * @covers ::getIterator()
     * @covers ::getFilters()
     * @param array $filter
     * @param string $name
     * @param mixed $value
     * @param mixed $expected
     */
    public function testValidArray(array $filter, string $name, $value, $expected)
    {
        $valid1 = new ValidArray($filter, [$name => $value]);
        $this->assertInstanceOf(ValidArray::class, $valid1);

        $this->assertInstanceOf(ArrayAccess::class, $valid1);
        $this->assertInstanceOf(Countable::class, $valid1);
        $this->assertInstanceOf(IteratorAggregate::class, $valid1);
        $this->assertEquals($filter, $valid1->getFilters());
        $this->assertEquals($expected, $valid1[$name]);

        // ArrayAccess, Countable
        $valid2 = new ValidArray($filter);
        $valid2[$name] = $value;
        if (array_key_exists($name, $filter)) {
            $this->assertTrue(isset($valid2[$name]));
            $this->assertCount(1, $valid2);
            $this->assertEquals(serialize([$name => $expected]), serialize((array)$valid2));
        } else {
            $this->assertFalse(isset($valid2[$name]));
            $this->assertCount(0, $valid2);
        }
        $this->assertTrue($expected === $valid2[$name]);

        // IteratorAggregate
        foreach ($valid2 as $k => $v) {
            $this->assertEquals($k, $name);
            $this->assertEquals($v, $expected);
        }

        // ArrayAccess, Countable
        unset($valid2[$name]);
        $this->assertCount(0, $valid2);
    }

    /** data provider */
    public static function providerValidArray(): array
    {
        $tel_filter = [
            'filter' => FILTER_VALIDATE_REGEXP,
            'options' => [
                'default' => '+00 (0)0 00 00 00 00',
                'regexp' => '/^\+?\d+(?:[. ()-]{1,2}\d+)*$/',
            ],
        ];

        $callback_filter = [
            'filter' => FILTER_CALLBACK,
            'flags' => FILTER_REQUIRE_SCALAR, // ignored with FILTER_CALLBACK!
            'options' => function ($v) {
                if (is_string($v) and ($v[0] ?? '') == '_') {
                    return $v;
                } else {
                    return false;
                }
            },
        ];

        $php_net_example_filters = [
            'product_id' => FILTER_SANITIZE_ENCODED,
            'component' => ['filter' => FILTER_VALIDATE_INT,
                'flags' => FILTER_FORCE_ARRAY,
                'options' => ['min_range' => 1, 'max_range' => 10],
            ],
            'versions' => FILTER_SANITIZE_ENCODED,
            'doesnotexist' => FILTER_VALIDATE_INT,
            'testintscalar' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR],
            'testintarray' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY],
        ];

        return [
            [['name' => FILTER_DEFAULT], 'name', 'value', 'value'],
            [['id' => FILTER_SANITIZE_NUMBER_INT], 'id', 123, '123'],
            [['id' => FILTER_SANITIZE_NUMBER_INT], 'id', 'string', ''],
            [['url' => FILTER_VALIDATE_URL], 'url', 'http://a.b.c/d/e.f', 'http://a.b.c/d/e.f'],
            [['url' => FILTER_VALIDATE_URL], 'url', 'a.b.c/d/e.f', false],
            [['ip' => ['filter'=>FILTER_VALIDATE_IP, 'flags'=>FILTER_FLAG_IPV4]], 'ip', '1.2.3.4', '1.2.3.4'],
            [['ip' => ['filter'=>FILTER_VALIDATE_IP, 'flags'=>FILTER_FLAG_IPV6]], 'ip', '1.2.3.4', false],
            [['email' => ['filter'=>FILTER_VALIDATE_EMAIL, 'flags'=>FILTER_FORCE_ARRAY]], 'email', 'a@b.c', ['a@b.c']],
            [['email' => ['filter'=>FILTER_VALIDATE_EMAIL, 'flags'=>FILTER_FORCE_ARRAY]], 'email', ['a@b.c'], ['a@b.c']],
            [['names' => ['filter'=>FILTER_DEFAULT, 'flags'=>FILTER_REQUIRE_ARRAY]], 'names', 'value', false],
            [['names' => ['filter'=>FILTER_DEFAULT, 'flags'=>FILTER_REQUIRE_ARRAY]], 'names', ['value1', 'value2'], ['value1', 'value2']],

            'tel with valid' =>
                [['tel' => $tel_filter], 'tel', '123-02-03', '123-02-03'],
            'tel with invalid and default' =>
                [['tel' => $tel_filter], 'tel', 'unknown', '+00 (0)0 00 00 00 00'],

            'callback with valid' =>
                [['val' => $callback_filter], 'val', '_true_', '_true_'],
            'callback with invalid' =>
                [['val' => $callback_filter], 'val', 'other', false],
            'callback with array' =>
                [['val' => $callback_filter], 'val', ['_true0_', [null, '_true2_', true]], ['_true0_', [false, '_true2_', false]]],
            'callback with missing' =>
                [['val' => $callback_filter], 'x', 'other', null],

            [$php_net_example_filters, 'product_id', 'libgd<script>', 'libgd%3Cscript%3E'],
            [$php_net_example_filters, 'component', '10', [10]],
            [$php_net_example_filters, 'component', ['10', '100'], [10, false]],
            [$php_net_example_filters, 'versions', '2.0.33', '2.0.33'],
            [$php_net_example_filters, 'testintscalar', 2, 2],
            [$php_net_example_filters, 'testintscalar', ['2', '23', '10', '12'], false],
            [$php_net_example_filters, 'testintarray', '2', [2]],
        ];
    }

    public function testValidArrayAddEmpty()
    {
        // by default, unset variables in input will be added to final array
        $valid1 = new ValidArray(['name' => FILTER_DEFAULT, 'unset' => FILTER_DEFAULT], ['name' => 'value']);
        $this->assertCount(2, $valid1);
        $this->assertEquals('value', $valid1['name']);
        $this->assertArrayHasKey('unset', $valid1);
        $this->assertNull($valid1['unset']);

        // to exclude unset input variables from final array set $add_empty parameter to false
        $valid2 = new ValidArray(['name' => FILTER_DEFAULT, 'unset' => FILTER_DEFAULT], ['name' => 'value'], false);
        $this->assertCount(1, $valid2);
        $this->assertEquals('value', $valid1['name']);
        $this->assertArrayNotHasKey('unset', $valid2);
    }

    public function testValidArrayDefault()
    {
        // default values will be used for unset vars
        $valid1 = new ValidArray(
            [
                'name' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 42]],
                'empty' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['default' => 42]],
            ],
            [
                'name' => [null, 'string', 0],
            ]
        );
        $this->assertCount(2, $valid1);
        $this->assertEquals([42, 42, 0], $valid1['name']);
        $this->assertEquals(42, $valid1['empty']);

        // if $add_empty param is false, default values for unset vars will not be used
        $valid2 = new ValidArray(
            [
                'name' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['default' => 42]],
                'empty' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['default' => 42]],
            ],
            [
                'name' => null,
            ],
            false
        );
        $this->assertCount(1, $valid2);
        $this->assertEquals(42, $valid2['name']);
    }
}
