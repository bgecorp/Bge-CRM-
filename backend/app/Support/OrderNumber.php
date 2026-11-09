<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Marketplace-style order numbers, like Shopee's: the order date (YYMMDD) and
 * eight random characters, e.g. 261108K7Q2MX4P. One number names exactly one
 * order across the whole system (store orders and web orders), so people can
 * read it out on the phone and other records can refer to it.
 */
class OrderNumber
{
    /** No 0/O or 1/I, so a number read aloud or copied by hand stays unambiguous. */
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const RANDOM_LENGTH = 8;

    /** Tables whose order_number column shares the one numbering. */
    public const TABLES = ['orders', 'web_orders'];

    public const PATTERN = '/^\d{6}[2-9A-HJ-NP-Z]{8}$/';

    public static function generate(?CarbonInterface $date = null): string
    {
        $prefix = ($date ?? Carbon::now())->format('ymd');

        // 32^8 combinations per day; a clash is practically impossible, but check anyway.
        do {
            $number = $prefix.self::random();
        } while (self::exists($number));

        return $number;
    }

    public static function exists(string $number): bool
    {
        foreach (self::TABLES as $table) {
            if (DB::table($table)->where('order_number', $number)->exists()) {
                return true;
            }
        }

        return false;
    }

    private static function random(): string
    {
        $out = '';
        for ($i = 0; $i < self::RANDOM_LENGTH; $i++) {
            $out .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $out;
    }
}
