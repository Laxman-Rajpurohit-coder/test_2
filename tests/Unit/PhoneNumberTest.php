<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public function test_normalizes_local_10_digit_indian_number()
    {
        $this->assertEquals('917850865122', PhoneNumber::normalize('7850865122'));
    }

    public function test_preserves_already_formatted_12_digit_number()
    {
        $this->assertEquals('917850865122', PhoneNumber::normalize('917850865122'));
    }

    public function test_strips_plus_spaces_and_formatting_characters()
    {
        $this->assertEquals('917850865122', PhoneNumber::normalize('+91 78508 65122'));
    }

    public function test_strips_leading_zeros()
    {
        $this->assertEquals('917850865122', PhoneNumber::normalize('0917850865122'));
    }

    public function test_handles_parentheses_and_dashes()
    {
        $this->assertEquals('917850865122', PhoneNumber::normalize('(78508)65122'));
        $this->assertEquals('917850865122', PhoneNumber::normalize('78508-65122'));
    }

    public function test_handles_empty_and_null_inputs()
    {
        $this->assertEquals('', PhoneNumber::normalize(''));
        $this->assertEquals('', PhoneNumber::normalize(null));
    }
}
