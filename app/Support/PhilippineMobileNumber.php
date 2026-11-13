<?php

namespace App\Support;

final class PhilippineMobileNumber
{
    public const PATTERN = '/^(?:09|\+639)[0-9]{9}$/';

    public const VALIDATION_MESSAGE = 'Please enter a valid Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX).';

    public const DUPLICATE_MESSAGE = 'This mobile number is already registered to another customer.';

    public static function trim(string $number): string
    {
        return preg_replace('/\A[\s\p{Z}\x{FEFF}]+|[\s\p{Z}\x{FEFF}]+\z/u', '', $number) ?? $number;
    }

    public static function normalize(string $number): ?string
    {
        $number = self::trim($number);

        if (preg_match(self::PATTERN, $number) !== 1) {
            return null;
        }

        return str_starts_with($number, '+63') ? '0'.substr($number, 3) : $number;
    }
}
