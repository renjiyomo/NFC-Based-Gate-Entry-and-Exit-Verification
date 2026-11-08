<?php

namespace Tests\Unit;

use App\Rules\NotCommonPassword;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PasswordPolicyTest extends TestCase
{
    public static function passwords(): array
    {
        return [
            ['passwordpassword', false], [' PasswordPassword ', false], ['123456789012345', false],
            [str_repeat('a', 20), false], [str_repeat('ñ', 20), false],
            ['orchard mist lantern canoe 47', true], ['café river quiet dawn 938', true],
        ];
    }

    #[DataProvider('passwords')]
    public function test_obvious_passwords_are_rejected_while_unique_passphrases_are_allowed(string $password, bool $allowed): void
    {
        $errors = [];
        (new NotCommonPassword)->validate('password', $password, function (string $message) use (&$errors): void {
            $errors[] = $message;
        });
        $this->assertSame($allowed, $errors === []);
    }
}
