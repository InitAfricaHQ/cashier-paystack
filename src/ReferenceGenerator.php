<?php

namespace InitAfricaHQ\Cashier;

class ReferenceGenerator
{
    /**
     * Get the pool to use based on the type of prefix hash
     *
     * @param  string  $type
     * @return string
     */
    private static function getPool($type = 'alnum')
    {
        return match ($type) {
            'alnum' => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'alpha' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'hexdec' => '0123456789abcdef',
            'numeric' => '0123456789',
            'nozero' => '123456789',
            'distinct' => '2345679ACDEFHJKLMNPRSTUVWXYZ',
            default => (string) $type,
        };
    }

    /**
     * Generate a random secure crypt figure
     *
     * @param  int  $min
     * @param  int  $max
     * @return int
     */
    private static function secureCrypt($min, $max)
    {
        $range = $max - $min;

        if ($range < 0) {
            return $min; // not so random...
        }

        $log = log($range, 2);
        $bytes = (int) ($log / 8) + 1; // length in bytes
        $bits = (int) $log + 1; // length in bits
        $filter = (int) (1 << $bits) - 1; // set all lower bits to 1

        do {
            $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
            $rnd = $rnd & $filter; // discard irrelevant bits
        } while ($rnd >= $range);

        return $min + $rnd;
    }

    /**
     * Finally, generate a hashed token
     *
     * @param  int  $length
     * @return string
     */
    public static function generate($length = 25)
    {
        $token = '';
        $max = strlen(static::getPool());

        for ($i = 0; $i < $length; $i++) {
            $token .= static::getPool()[static::secureCrypt(0, $max)];
        }

        return $token;
    }
}
