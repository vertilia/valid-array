<?php

namespace Vertilia\ValidArray;

use ArrayAccess;
use ArrayObject;
use Countable;
use IteratorAggregate;
use PHPUnit\Framework\TestCase;
use RangeException;
use RuntimeException;
use SplDoublyLinkedList;
use SplStack;
use stdClass;

/**
 * @coversDefaultClass ValidArray
 */
class ValidArrayTest extends TestCase
{
    /**
     * @dataProvider providerValidArray
     * @covers ValidArray::__construct
     * @covers ValidArray::offsetSet
     * @covers ValidArray::offsetGet
     * @covers ValidArray::offsetExists
     * @covers ValidArray::offsetUnset
     * @covers ValidArray::count
     * @covers ValidArray::getIterator
     * @covers ValidArray::getFilters
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
        $this->assertSame($filter, $valid1->getFilters());
        $this->assertSame($expected, $valid1[$name] ?? null);

        $valid2 = new ValidArray($filter);
        // ArrayAccess
        $valid2[$name] = $value;
        // Countable
        $this->assertCount(count($filter), $valid2);
        if (array_key_exists($name, $filter)) {
            // ArrayAccess
            $this->assertTrue(isset($valid2[$name]));
            $this->assertSame($expected, $valid2[$name]);
            // IteratorAggregate
            foreach ($valid2 as $k => $v) {
                if ($k === $name) {
                    $this->assertSame($expected, $v, "element: $k");
                } else {
                    $this->assertNull($v, "element missing: $k");
                }
            }
        } else {
            // ArrayAccess
            $this->assertFalse(isset($valid2[$name]));
            // IteratorAggregate
            foreach ($valid2 as $v) {
                $this->assertNull($v, "element missing: " . var_export($v, true));
            }
        }
        $this->assertSame($expected, $valid2[$name] ?? null);

        // ArrayAccess
        unset($valid2[$first_key]);
        // Countable
        $this->assertCount(count($filter), $valid2);
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

    /**
     * @covers ValidArray::count
     * @covers ValidArray::offsetGet
     */
    public function testCount()
    {
        $valid = new ValidArray(
            ['name' => ['filter' => FILTER_DEFAULT, 'options' => ['default' => 'DEFAULT']], 'unset' => FILTER_DEFAULT],
            ['name' => 'value']
        );
        $this->assertCount(2, $valid);
        $this->assertSame('value', $valid['name']);
        $this->assertArrayHasKey('unset', $valid);
        $this->assertNull($valid['unset']);
        $valid[] = 'test';
        $this->assertCount(2, $valid);
        unset($valid['name']);
        $this->assertCount(2, $valid);
        $this->assertSame('DEFAULT', $valid['name']);
    }

    public function testOptionsDefault()
    {
        // default values will be used for unset vars
        $valid = new ValidArray(
            [
                'name1' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 42]],
                'name2' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 42]],
                'empty1' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 42]],
                'empty2' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY],
            ],
            [
                'name1' => [null, 'non-numeric string', '0'],
                'name2' => '0',
            ],
        );
        $this->assertCount(4, $valid, 'set all filters');
        $this->assertSame([42, 42, 0], $valid['name1'], 'existing param: is array');
        $this->assertSame([0], $valid['name2'], 'existing param: from scalar to array, use flags');
        $this->assertSame(42, $valid['empty1'], 'missing param: use default, ignore flags (VA-addition)');
        $this->assertSame(null, $valid['empty2'], 'missing param: default null, ignore flags');
        $this->assertSame(null, $valid['missing'] ?? null, 'undeclared param: set null');
    }

    /**
     * @covers ValidArray
     */
    public function testFilterCallback()
    {
        $filter = [
            'cb' => [
                'filter' => FILTER_CALLBACK,
                'flags' => FILTER_FORCE_ARRAY, // ignored
                'options' => fn($v) => 42,
            ],
        ];

        $valid1 = new ValidArray($filter, ['cb' => 1, 'X' => 1, 'Y' => 1]);
        $this->assertSame(42, $valid1['cb'], 'existing param: run callback, ignore flags');
        unset($valid1['cb']);
        $this->assertNull($valid1['cb'], 'unset param: null');
        $valid1['X'] = 12;
        $this->assertNull($valid1['X'] ?? null, 'ignore undefined param');
        $valid1['cb'] = 12;
        $this->assertSame(42, $valid1['cb'], 'set existing param: run callback, ignore flags');

        $valid2 = new ValidArray($filter, ['X' => 1, 'Y' => 1]);
        $this->assertSame(null, $valid2['cb'], 'undeclared param: set null, ignore callback, ignore flags');
    }

    /**
     * @covers ValidArray::FILTER_EXTENDED_CALLBACK
     * @dataProvider providerFilterExtendedCallback
     */
    public function testFilterExtendedCallback($default, $flags, $value, $expected)
    {
        $filters = [
            'cbk' => [
                'filter' => ValidArray::FILTER_EXTENDED_CALLBACK,
                'flags' => $flags,
                'options' => ['callback' => fn ($v) => is_numeric($v) ? $v + 1 : false],
            ],
        ];
        $values = ['X' => 1];
        if ('UNDEFINED' !== $default) {
            $filters['cbk']['options']['default'] = $default;
        }
        if ('UNDEFINED' !== $value) {
            $values['cbk'] = $value;
        }

        $valid = new ValidArray($filters, $values);
        $this->assertSame($expected, $valid['cbk'], 'expected value after init');

        if ('UNDEFINED' !== $default) {
            unset($valid['cbk']);
            $this->assertSame($default, $valid['cbk'], 'default or null on unset (VA-addition)');
        }

        if ('UNDEFINED' !== $value) {
            $valid['cbk'] = $value;
            $this->assertSame($expected, $valid['cbk'], 'expected value after set');
        }
    }

    public static function providerFilterExtendedCallback(): array
    {
        $stdClass = (object)[];

        return [
            // FILTER_EXTENDED_CALLBACK

            'undefined param: use default or set null' =>
                ['X', FILTER_REQUIRE_SCALAR, 'UNDEFINED', 'X'],
                ['UNDEFINED', FILTER_REQUIRE_SCALAR, 'UNDEFINED', null],
            'null: use default or set null' =>
                ['X', FILTER_REQUIRE_SCALAR, null, 'X'],
                ['UNDEFINED', FILTER_REQUIRE_SCALAR, null, false],
                ['UNDEFINED', FILTER_REQUIRE_SCALAR|FILTER_NULL_ON_FAILURE, null, null],
            'false: use default or set false' =>
                ['X', FILTER_REQUIRE_SCALAR, false, 'X'],
                ['UNDEFINED', FILTER_REQUIRE_SCALAR, false, false],
                ['UNDEFINED', FILTER_REQUIRE_SCALAR|FILTER_NULL_ON_FAILURE, false, null],
            'string: use default or set false' =>
                ['X', FILTER_REQUIRE_SCALAR, '?', 'X'],
                ['UNDEFINED', FILTER_REQUIRE_SCALAR, '?', false],
                ['UNDEFINED', FILTER_REQUIRE_SCALAR|FILTER_NULL_ON_FAILURE, '?', null],
            'int: run callback' =>
                ['X', FILTER_REQUIRE_SCALAR, 42, 43],
                ['UNDEFINED', FILTER_REQUIRE_SCALAR, 42, 43],
                ['UNDEFINED', FILTER_REQUIRE_SCALAR|FILTER_NULL_ON_FAILURE, 42, 43],
            'objects: keep object or replace with default' =>
                ['X', FILTER_REQUIRE_SCALAR, $stdClass, 'X'],
                ['UNDEFINED', FILTER_REQUIRE_SCALAR, $stdClass, false],
                ['UNDEFINED', FILTER_REQUIRE_SCALAR|FILTER_NULL_ON_FAILURE, $stdClass, null],

            'array (empty): keep empty array' =>
                ['X', FILTER_REQUIRE_ARRAY, [], []],
                ['UNDEFINED', FILTER_REQUIRE_ARRAY, [], []],
            'array (objects or scalars): only keep matching elements, replace others with default' =>
                ['X', FILTER_REQUIRE_ARRAY, [$stdClass, ['test', null], [42, false]], ['X', ['X', 'X'], [43, 'X']]],
                ['UNDEFINED', FILTER_REQUIRE_ARRAY, [$stdClass, ['test', null], [42, false]], [false, [false, false], [43, false]]],
        ];
    }

    /**
     * @covers ValidArray::FILTER_INSTANCE_OF
     * @dataProvider providerFilterInstanceOf
     */
    public function testFilterInstanceOf($default, $flags, $value, $expected)
    {
        $filters = [
            'cls' => [
                'filter' => ValidArray::FILTER_INSTANCE_OF,
                'flags' => $flags,
                'options' => ['class_name' => SplDoublyLinkedList::class],
            ],
        ];
        $values = ['X' => 1];
        if ('UNDEFINED' !== $default) {
            $filters['cls']['options']['default'] = $default;
        }
        if ('UNDEFINED' !== $value) {
            $values['cls'] = $value;
        }

        $valid = new ValidArray($filters, $values);
        $this->assertSame($expected, $valid['cls'], 'expected value after init');

        if ('UNDEFINED' !== $default) {
            unset($valid['cls']);
            $this->assertSame($default, $valid['cls'], 'default or null on unset (VA-addition)');
        }

        if ('UNDEFINED' !== $value) {
            $valid['cls'] = $value;
            $this->assertSame($expected, $valid['cls'], 'expected value after set');
        }
    }

    public static function providerFilterInstanceOf(): array
    {
        $stdClass = (object)[];
        $parent = new SplDoublyLinkedList();
        $child = new SplStack();

        return [
            // FILTER_CLASS

            'undefined param: use default or set null' =>
                ['X', FILTER_REQUIRE_SCALAR, 'UNDEFINED', 'X'],
                ['UNDEFINED', FILTER_REQUIRE_SCALAR, 'UNDEFINED', null],
            'null: use default or set null' =>
                ['X', FILTER_REQUIRE_SCALAR, null, 'X'],
                ['UNDEFINED', FILTER_REQUIRE_SCALAR, null, false],
                ['UNDEFINED', FILTER_REQUIRE_SCALAR|FILTER_NULL_ON_FAILURE, null, null],
            'false: use default or set false' =>
                ['X', FILTER_REQUIRE_SCALAR, false, 'X'],
                ['UNDEFINED', FILTER_REQUIRE_SCALAR, false, false],
                ['UNDEFINED', FILTER_REQUIRE_SCALAR|FILTER_NULL_ON_FAILURE, false, null],
            'string: use default or set false' =>
                ['X', FILTER_REQUIRE_SCALAR, '?', 'X'],
                ['UNDEFINED', FILTER_REQUIRE_SCALAR, '?', false],
                ['UNDEFINED', FILTER_REQUIRE_SCALAR|FILTER_NULL_ON_FAILURE, '?', null],
            'objects: keep object or replace with default' =>
                ['X', FILTER_REQUIRE_SCALAR, $parent, $parent],
                ['X', FILTER_REQUIRE_SCALAR, $child, $child],
                ['X', FILTER_REQUIRE_SCALAR, $stdClass, 'X'],
                ['UNDEFINED', FILTER_REQUIRE_SCALAR, $stdClass, false],
                ['UNDEFINED', FILTER_REQUIRE_SCALAR|FILTER_NULL_ON_FAILURE, $stdClass, null],

            'array (empty): keep empty array' =>
                ['X', FILTER_REQUIRE_ARRAY, [], []],
                ['UNDEFINED', FILTER_REQUIRE_ARRAY, [], []],
            'array (objects or scalars): only keep matching objects, replace other elements with default' =>
                ['X', FILTER_REQUIRE_ARRAY, [$child, $parent, ['test', null], [$stdClass, false]], [$child, $parent, ['X', 'X'], ['X', 'X']]],
                ['UNDEFINED', FILTER_REQUIRE_ARRAY, [$child, $parent, ['test', null], [$stdClass, false]], [$child, $parent, [false, false], [false, false]]],
        ];
    }
}
