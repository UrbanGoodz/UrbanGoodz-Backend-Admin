<?php

namespace App\Services;

use Illuminate\Support\Str;

class TotpService
{
    private const DIGITS = 6;
    private const PERIOD = 30;
    private const ALGORITHM = 'sha1';
    private const RECOVERY_CODE_COUNT = 8;
    private const RECOVERY_CODE_LENGTH = 10;

    public static function generateSecret(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    public static function verify(string $secret, string $code): bool
    {
        $time = floor(time() / self::PERIOD);

        for ($i = -1; $i <= 1; $i++) {
            $calculated = self::generateCode($secret, $time + $i);
            if (hash_equals($calculated, str_pad($code, self::DIGITS, '0', STR_PAD_LEFT))) {
                return true;
            }
        }

        return false;
    }

    public static function generateCode(string $secret, int $time): string
    {
        $secretBytes = self::base32Decode($secret);
        $timeBytes = pack('N*', 0) . pack('N*', $time);

        $hmac = hash_hmac(self::ALGORITHM, $timeBytes, $secretBytes, true);

        $offset = ord($hmac[strlen($hmac) - 1]) & 0x0F;
        $hashPart = substr($hmac, $offset, 4);

        $value = unpack('N', $hashPart)[1];
        $value = $value & 0x7FFFFFFF;

        $code = $value % pow(10, self::DIGITS);

        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function getUri(string $secret, string $email, string $issuer = 'Urban Goodz'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=%s&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($email),
            $secret,
            rawurlencode($issuer),
            strtoupper(self::ALGORITHM),
            self::DIGITS,
            self::PERIOD
        );
    }

    public static function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $code = strtoupper(Str::random(self::RECOVERY_CODE_LENGTH));
            $codes[] = substr($code, 0, 5) . '-' . substr($code, 5);
        }
        return $codes;
    }

    public static function hashRecoveryCodes(array $codes): string
    {
        $hashed = array_map(fn($code) => password_hash($code, PASSWORD_BCRYPT), $codes);
        return json_encode($hashed);
    }

    public static function verifyRecoveryCode(string $hashedCodesJson, string $code): ?string
    {
        $hashedCodes = json_decode($hashedCodesJson, true);
        if (!is_array($hashedCodes)) {
            return null;
        }

        $codeUpper = strtoupper(str_replace('-', '', $code));

        foreach ($hashedCodes as $index => $hashed) {
            if (password_verify($codeUpper, $hashed)) {
                unset($hashedCodes[$index]);
                return json_encode(array_values($hashedCodes));
            }
        }

        return null;
    }

    private static function base32Decode(string $input): string
    {
        $map = [
            'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5,
            'G' => 6, 'H' => 7, 'I' => 8, 'J' => 9, 'K' => 10, 'L' => 11,
            'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15, 'Q' => 16, 'R' => 17,
            'S' => 18, 'T' => 19, 'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23,
            'Y' => 24, 'Z' => 25, '2' => 26, '3' => 27, '4' => 28, '5' => 29,
            '6' => 30, '7' => 31,
        ];

        $input = strtoupper(rtrim($input, '='));
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        for ($i = 0; $i < strlen($input); $i++) {
            $val = $map[$input[$i]] ?? 0;
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }
}
