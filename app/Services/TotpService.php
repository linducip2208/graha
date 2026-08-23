<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * TOTP (RFC 6238) implementation — tanpa dependency eksternal.
 * Secret disimpan encrypted di DB, tidak pernah plaintext.
 */
class TotpService
{
    public function generateSecret(int $bits = 160): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // base32
        $secret = '';
        $bytes = random_bytes((int) ceil($bits / 8));

        foreach (str_split($bytes) as $byte) {
            $secret .= $alphabet[$byte & 31];
        }

        return substr($secret, 0, 32);
    }

    /** otpauth:// URI untuk QR code authenticator app. */
    public function otpauthUri(string $secret, string $email, string $issuer = 'Graha Pondasi ERP'): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer).':'.rawurlencode($email)
            .'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';
    }

    /**
     * Verifikasi kode 6-digit dengan window ±1 periode (toleransi drift).
     *
     * @param  string[]|null  $recoveryCodes  hashed recovery codes alternatif
     * @return bool|string true = TOTP valid; string recovery code = valid via recovery; false = invalid
     */
    public function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code);

        if (strlen($code) !== 6 || $secret === '') {
            return false;
        }

        $timeSlice = intdiv(time(), 30);

        for ($i = -1; $i <= 1; $i++) {
            if ($this->hotp($secret, $timeSlice + $i) === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cek recovery code (hashed bcrypt di DB).
     *
     * @param  array<int,string>  $hashedCodes
     */
    public function verifyRecovery(string $code, array $hashedCodes): ?array
    {
        foreach ($hashedCodes as $index => $hash) {
            if (Hash::check(trim($code), $hash)) {
                // single-use: hapus dari daftar
                unset($hashedCodes[$index]);

                return array_values($hashedCodes);
            }
        }

        return null;
    }

    /** @return string[] raw codes (ditampilkan sekali); simpan versi hash. */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(5)).'-'.strtoupper(Str::random(5));
        }

        return $codes;
    }

    public function hashRecoveryCodes(array $rawCodes): array
    {
        return array_map(fn ($c) => Hash::make($c), $rawCodes);
    }

    /** HOTP RFC 4226 dengan base32 secret. */
    protected function hotp(string $base32Secret, int $counter): string
    {
        $key = $this->base32Decode($base32Secret);
        $binaryCounter = pack('N*', 0).pack('N*', $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);

        $offset = ord(substr($hash, -1)) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    protected function base32Decode(string $b32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $out = '';
        $bits = '';

        foreach (str_split(strtoupper($b32)) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
            if (strlen($bits) >= 8) {
                $out .= chr(bindec(substr($bits, 0, 8)));
                $bits = substr($bits, 8);
            }
        }

        return $out;
    }
}
