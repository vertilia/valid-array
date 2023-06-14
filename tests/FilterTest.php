<?php

namespace Vertilia\ValidArray;

use PHPUnit\Framework\TestCase;
use TypeError;

class StringableObject
{
    public function __toString(): string
    {
        return '84';
    }
}

class FilterTest extends TestCase
{
    /**
     * @dataProvider providerFilter
     */
    public function testFilter($filter, $value, $expected)
    {

        $values = ['v' => $value];
        $filters = ['v' => $filter];

        if ('UNDEFINED' === $value) {
            unset($values['v']);
        }

        if ('ERROR' === $expected) {
            $this->expectError();
            $values = @filter_var_array($values, $filters);
        } else {
            $values = filter_var_array($values, $filters);
        }

        if ('ERROR' !== $expected) {
            $this->assertSame($expected, $values['v'], 'filter_var_array()');
        }

        if ('UNDEFINED' !== $value) {
            $filter_extracted = $filter['filter'] < 0 ? FILTER_DEFAULT : $filter['filter'];
            $this->assertSame($expected, filter_var($value, $filter_extracted, $filter), 'filter_var()');
        }
    }

    public static function providerFilter(): array
    {
        $str_obj = new StringableObject();

        return [
            // FILTER_DEFAULT (flags, defaults for non-stringable objects only)

            'FILTER_DEFAULT / undefined' =>
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], 'UNDEFINED', null],
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], 'UNDEFINED', null],
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], 'UNDEFINED', null],
            'FILTER_DEFAULT / null' =>
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], null, ''],
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], null, false],
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], null, ['']],
            'FILTER_DEFAULT / false' =>
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], false, ''],
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], false, false],
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], false, ['']],
            'FILTER_DEFAULT / scalar' =>
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], 42, '42'],
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], 42, false],
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], 42, ['42']],
            'FILTER_DEFAULT / object' =>
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], (object)[], 21],
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], (object)[], false],
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], (object)[], [21]],
            'FILTER_DEFAULT / stringable object' =>
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], $str_obj, '84'],
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], $str_obj, false],
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], $str_obj, ['84']],
            'FILTER_DEFAULT / array (empty)' =>
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], [], false],
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], [], []],
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], [], []],
            'FILTER_DEFAULT / array (static)' =>
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], [42, [42]], false],
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], [42, [42]], ['42', ['42']]],
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], [42, [42]], ['42', ['42']]],
            'FILTER_DEFAULT / array (object)' =>
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], [(object)[], [(object)[]]], false],
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], [(object)[], [(object)[]]], [21, [21]]],
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], [(object)[], [(object)[]]], [21, [21]]],
            'FILTER_DEFAULT / array (stringable object)' =>
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], [$str_obj, [$str_obj]], false],
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], [$str_obj, [$str_obj]], ['84', ['84']]],
                [['filter' => FILTER_DEFAULT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], [$str_obj, [$str_obj]], ['84', ['84']]],

            // FILTER_VALIDATE_INT (flags, defaults)

            'FILTER_VALIDATE_INT / undefined' =>
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], 'UNDEFINED', null],
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], 'UNDEFINED', null],
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], 'UNDEFINED', null],
            'FILTER_VALIDATE_INT / null' =>
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], null, 21],
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], null, false],
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], null, [21]],
            'FILTER_VALIDATE_INT / false' =>
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], false, 21],
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], false, false],
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], false, [21]],
            'FILTER_VALIDATE_INT / scalar correct' =>
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], 42, 42],
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], 42, false],
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], 42, [42]],
            'FILTER_VALIDATE_INT / scalar incorrect' =>
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], '?', 21],
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], '?', false],
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], '?', [21]],
            'FILTER_VALIDATE_INT / object' =>
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], (object)[], 21],
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], (object)[], false],
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], (object)[], [21]],
            'FILTER_VALIDATE_INT / stringable object' =>
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], $str_obj, 84],
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], $str_obj, false],
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], $str_obj, [84]],
            'FILTER_VALIDATE_INT / array (empty)' =>
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], [], false],
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], [], []],
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], [], []],
            'FILTER_VALIDATE_INT / array (static correct)' =>
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], [42, [42]], false],
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], [42, [42]], [42, [42]]],
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], [42, [42]], [42, [42]]],
            'FILTER_VALIDATE_INT / array (static incorrect)' =>
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], ['?', ['?']], false],
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], ['?', ['?']], [21, [21]]],
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], ['?', ['?']], [21, [21]]],
            'FILTER_VALIDATE_INT / array (object)' =>
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], [(object)[], [(object)[]]], false],
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], [(object)[], [(object)[]]], [21, [21]]],
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], [(object)[], [(object)[]]], [21, [21]]],
            'FILTER_VALIDATE_INT / array (stringable object)' =>
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], [$str_obj, [$str_obj]], false],
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], [$str_obj, [$str_obj]], [84, [84]]],
                [['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], [$str_obj, [$str_obj]], [84, [84]]],

            // unknown as FILTER_DEFAULT (flags with FILTER_NULL_ON_FAILURE, defaults for non-stringable objects only)

            'unknown / undefined' =>
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], 'UNDEFINED', null],
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], 'UNDEFINED', null],
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], 'UNDEFINED', null],
            'unknown / null' =>
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], null, ''],
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], null, null],
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], null, ['']],
            'unknown / false' =>
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], false, ''],
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], false, null],
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], false, ['']],
            'unknown / scalar' =>
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], 42, '42'],
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], 42, null],
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], 42, ['42']],
            'unknown / object' =>
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], (object)[], 21],
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], (object)[], null],
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], (object)[], [21]],
            'unknown / stringable object' =>
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], $str_obj, '84'],
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], $str_obj, null],
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], $str_obj, ['84']],
            'unknown / array (empty)' =>
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], [], null],
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], [], []],
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], [], []],
            'unknown / array (static)' =>
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], [42, [42]], null],
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], [42, [42]], ['42', ['42']]],
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], [42, [42]], ['42', ['42']]],
            'unknown / array (object)' =>
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], [(object)[], [(object)[]]], null],
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], [(object)[], [(object)[]]], [21, [21]]],
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], [(object)[], [(object)[]]], [21, [21]]],
            'unknown / array (stringable object)' =>
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_REQUIRE_SCALAR, 'options' => ['default' => 21]], [$str_obj, [$str_obj]], null],
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_REQUIRE_ARRAY, 'options' => ['default' => 21]], [$str_obj, [$str_obj]], ['84', ['84']]],
                [['filter' => -1, 'flags' => FILTER_NULL_ON_FAILURE | FILTER_FORCE_ARRAY, 'options' => ['default' => 21]], [$str_obj, [$str_obj]], ['84', ['84']]],

            // FILTER_CALLBACK (no flags, no defaults)

            'FILTER_CALLBACK / error' =>
                [['filter' => FILTER_CALLBACK], 'X', 'ERROR'],
            'FILTER_CALLBACK / undefined' =>
                [['filter' => FILTER_CALLBACK, 'options' => fn($v) => $v], 'UNDEFINED', null],
            'FILTER_CALLBACK / null' =>
                [['filter' => FILTER_CALLBACK, 'options' => fn($v) => $v], null, ''],
            'FILTER_CALLBACK / false' =>
                [['filter' => FILTER_CALLBACK, 'options' => fn($v) => $v], false, ''],
            'FILTER_CALLBACK / scalar' =>
                [['filter' => FILTER_CALLBACK, 'options' => fn($v) => $v], 42, '42'],
            'FILTER_CALLBACK / object' =>
                [['filter' => FILTER_CALLBACK, 'options' => fn($v) => $v], (object)[], false],
            'FILTER_CALLBACK / stringable object' =>
                [['filter' => FILTER_CALLBACK, 'options' => fn($v) => $v], $str_obj, '84'],
            'FILTER_CALLBACK / array (empty)' =>
                [['filter' => FILTER_CALLBACK, 'options' => fn($v) => $v], [], []],
            'FILTER_CALLBACK / array (static) as is' =>
                [['filter' => FILTER_CALLBACK, 'options' => fn($v) => $v], [42, [42]], ['42', ['42']]],
            'FILTER_CALLBACK / array (static) as int' =>
                [['filter' => FILTER_CALLBACK, 'options' => fn($v) => (int)$v], [42, [42]], [42, [42]]],
            'FILTER_CALLBACK / array (object)' =>
                [['filter' => FILTER_CALLBACK, 'options' => fn($v) => $v], [(object)[], [(object)[]]], [false, [false]]],
            'FILTER_CALLBACK / array (stringable object)' =>
                [['filter' => FILTER_CALLBACK, 'options' => fn($v) => $v], [$str_obj, [$str_obj]], ['84', ['84']]],
        ];
    }
}
