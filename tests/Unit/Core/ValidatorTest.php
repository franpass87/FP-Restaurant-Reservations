<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use FP\Resv\Core\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testSanitizePreservesPrimitiveTypes(): void
    {
        $input = [
            'string'  => '  Hello <strong>World</strong>  ',
            'multiline' => "Line 1\nLine 2",
            'int'     => 42,
            'float'   => 9.99,
            'bool'    => true,
            'null'    => null,
            'array'   => [
                'nested_string' => ' Foo ',
                'nested_bool'   => false,
                'nested_array'  => [
                    'deep' => "Deep\nValue",
                ],
            ],
        ];

        $result = Validator::sanitize($input);

        self::assertSame('Hello World', $result['string']);
        self::assertSame("Line 1\nLine 2", $result['multiline']);
        self::assertSame(42, $result['int']);
        self::assertSame(9.99, $result['float']);
        self::assertTrue($result['bool']);
        self::assertNull($result['null']);
        self::assertSame('Foo', $result['array']['nested_string']);
        self::assertFalse($result['array']['nested_bool']);
        self::assertSame('Deep' . PHP_EOL . 'Value', $result['array']['nested_array']['deep']);
    }
}
