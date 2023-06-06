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
        $first_key = key($filter);

        $valid1 = new ValidArray($filter, [$name => $value]);
        $this->assertInstanceOf(ValidArray::class, $valid1);

        $this->assertInstanceOf(ArrayAccess::class, $valid1);
        $this->assertInstanceOf(Countable::class, $valid1);
        $this->assertInstanceOf(IteratorAggregate::class, $valid1);
        $this->assertEquals($filter, $valid1->getFilters());
        $this->assertEquals($expected, $valid1[$name]);

        $valid2 = new ValidArray($filter);
        // ArrayAccess
        $valid2[$name] = $value;
        // Countable
        $this->assertCount(count($filter), $valid2);
        if (array_key_exists($name, $filter)) {
            // ArrayAccess
            $this->assertTrue(isset($valid2[$name]));
            $this->assertEquals(serialize([$name => $expected]), serialize(array_intersect_key((array)$valid2, [$name => null])));
            // IteratorAggregate
            foreach ($valid2 as $k => $v) {
                if ($k === $name) {
                    $this->assertEquals($expected, $v);
                } else {
                    $this->assertNull($v);
                }
            }
        } else {
            // ArrayAccess
            $this->assertFalse(isset($valid2[$name]));
            $this->assertEquals(serialize([$first_key => null]), serialize(array_intersect_key((array)$valid2, [$first_key => null])));
            // IteratorAggregate
            foreach ($valid2 as $v) {
                $this->assertNull($v);
            }
        }
        $this->assertTrue($expected === $valid2[$name]);

        // ArrayAccess
        unset($valid2[$first_key]);
        // Countable
        $this->assertCount(count($filter) - 1, $valid2);
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
            'options' => fn ($v) =>
                (is_string($v) and ($v[0] ?? '') == '_') ? $v : false
            ,
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
            'tel with empty and default (VA-addition)' =>
                [['tel' => $tel_filter], 'tel', '', '+00 (0)0 00 00 00 00'],

            'callback with valid' =>
                [['val' => $callback_filter], 'val', '_true_', '_true_'],
            'callback with invalid' =>
                [['val' => $callback_filter], 'val', 'other', false],
            'callback with array' =>
                [['val' => $callback_filter], 'val', ['_true0_', [null, '_true2_', true]], ['_true0_', [false, '_true2_', false]]],
            'callback with missing' =>
                [['val' => $callback_filter], 'x', 'other', null],

            'php.net example filters' =>
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
                'name1' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 42]],
                'name2' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 42]],
                'empty1' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 42]],
                'empty2' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY],
            ],
            [
                'name1' => [null, 'non-numeric string', '0'],
                'name2' => '0',
            ]
        );
        $this->assertCount(4, $valid1, 'set all filters');
        $this->assertEquals([42, 42, 0], $valid1['name1'], 'existing param: is array');
        $this->assertEquals([0], $valid1['name2'], 'existing param: from scalar to array, use flags');
        $this->assertEquals(42, $valid1['empty1'], 'missing param: use default, ignore flags (VA-addition)');
        $this->assertEquals(null, $valid1['empty2'], 'missing param: default null, ignore flags');
        $this->assertEquals(null, $valid1['missing'], 'undeclared param: set null');

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
        $this->assertEquals(null, $valid2['empty']);
        $this->assertEquals(null, $valid1['missing']);
    }

    public function testValidArrayCallback()
    {
        $filter = [
            'cb' => [
                'filter' => FILTER_CALLBACK,
                'flags' => FILTER_FORCE_ARRAY,
                'options' => fn($v) => 42,
            ]
        ];

        $valid1 = new ValidArray($filter, ['cb' => 1, 'X' => 1, 'Y' => 1]);
        $this->assertCount(1, $valid1);
        $this->assertEquals(42, $valid1['cb'], 'existing param: run callback, ignore flags');
        unset($valid1['cb']);
        $this->assertCount(0, $valid1);
        $valid1['cb'] = 12;
        $this->assertCount(1, $valid1);
        $this->assertEquals(42, $valid1['cb'], 'set existing param: run callback, ignore flags');

        $valid2 = new ValidArray($filter, ['X' => 1, 'Y' => 1]);
        $this->assertCount(1, $valid2);
        $this->assertEquals(null, $valid2['cb'], 'undeclared param: set null, ignore callback, ignore flags');

        $valid3 = new ValidArray($filter, ['X' => 1, 'Y' => 1], false);
        $this->assertCount(0, $valid3);
        $this->assertEquals(null, $valid3['cb'], 'undeclared param: return null');
    }

    public function testValidArrayExtendedCallback()
    {
        $filter = [
            'cb' => [
                'filter' => ValidArray::FILTER_EXTENDED_CALLBACK,
                'flags' => FILTER_FORCE_ARRAY,
                'options' => ['callback' => fn($v) => 42, 'default' => 35],
            ]
        ];

        $valid1 = new ValidArray($filter, ['cb' => [1, [2, 3], 4], 'X' => 1, 'Y' => 1]);
        $this->assertCount(1, $valid1);
        $this->assertEquals([42, [42, 42], 42], $valid1['cb'], 'existing param: run callback, use flags (VA-addition)');
        unset($valid1['cb']);
        $this->assertCount(0, $valid1);
        $valid1['cb'] = 12;
        $this->assertCount(1, $valid1);
        $this->assertEquals([42], $valid1['cb'], 'set existing param: run callback, use flags (VA-addition)');

        $valid2 = new ValidArray($filter, ['X' => 1, 'Y' => 1]);
        $this->assertCount(1, $valid2);
        $this->assertEquals(35, $valid2['cb'], 'undeclared param: use default, ignore flags (VA-addition)');

        $valid3 = new ValidArray($filter, ['X' => 1, 'Y' => 1], false);
        $this->assertCount(0, $valid3);
        $this->assertEquals(null, $valid3['cb'], 'undeclared param: return null');
    }
}
