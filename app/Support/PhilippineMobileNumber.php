<?php

namespace App\Support;

final class PhilippineMobileNumber
{
    public const PATTERN = '/\A09[0-9]{9}\z/';

    public const VALIDATION_MESSAGE = 'Contact number must contain exactly 11 digits and start with 09 (e.g. 09214027871).';

    public const DUPLICATE_MESSAGE = 'This mobile number is already registered to another customer.';

    public static function trim(string $number): string
    {
        return preg_replace('/\A[\s\p{Z}\x{FEFF}]+|[\s\p{Z}\x{FEFF}]+\z/u', '', $number) ?? $number;
    }

    public static function normalize(string $number): ?string
    {
        $number = self::trim($number);

        // Recognize legacy international numbers when checking for duplicates.
        if (preg_match('/\A(?:09|\+639)[0-9]{9}\z/', $number) !== 1) {
            return null;
        }

        return str_starts_with($number, '+63') ? '0'.substr($number, 3) : $number;
    }
}
