<?php

namespace App\Services;

/**
 * MFA Service for TOTP-based two-factor authentication.
 * Uses Google Authenticator compatible TOTP algorithm.
 */
class MFAService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a new TOTP secret for MFA setup.
     *
     * @return string Base32-encoded secret
     */
    public function generateSecret(): string
    {
        // Generate 20 bytes of random data and encode as base32
        $randomBytes = random_bytes(20);
        return $this->base32Encode($randomBytes);
    }

    /**
     * Verify a TOTP code against the user's secret.
     *
     * @param string $secret Base32-encoded secret
     * @param string $code 6-digit TOTP code
     * @return bool True if code is valid
     */
    public function verify(string $secret, string $code): bool
    {
        // Validate input format
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        if (empty($secret)) {
            return false;
        }

        $secret = str_replace(' ', '', strtoupper($secret));

        // Check current time window and adjacent windows for clock drift tolerance
        $currentTime = time();
        for ($offset = -1; $offset <= 1; $offset++) {
            $expectedCode = $this->generateTOTP($secret, $currentTime + ($offset * 30));
            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get QR code URL for Google Authenticator setup.
     * Returns the otpauth:// URI for QR code generation.
     *
     * @param string $secret Base32-encoded secret
     * @param string $email User's email address
     * @param string|null $issuer Application name
     * @return string otpauth:// URI
     */
    public function getQRCodeUrl(string $secret, string $email, ?string $issuer = null): string
    {
        return $this->getProvisioningUri($secret, $email, $issuer ?? 'Attendance System');
    }

    /**
     * Get the provisioning URI for QR code generation.
     * Used by authenticator apps to set up MFA.
     *
     * @param string $secret Base32-encoded secret
     * @param string $email User's email address
     * @param string $issuer Application name
     * @return string otpauth:// URI
     */
    public function getProvisioningUri(string $secret, string $email, string $issuer = 'Attendance System'): string
    {
        $secret = str_replace(' ', '', strtoupper($secret));
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            rawurlencode($issuer),
            rawurlencode($email),
            $secret,
            rawurlencode($issuer)
        );
    }

    /**
     * Get the current TOTP code for a secret (for testing purposes).
     *
     * @param string $secret Base32-encoded secret
     * @return string Current 6-digit code
     */
    public function getCurrentCode(string $secret): string
    {
        return $this->generateTOTP($secret);
    }

    /**
     * Generate TOTP code for a given time.
     *
     * @param string $secret Base32-encoded secret
     * @param int|null $time Unix timestamp
     * @return string 6-digit code
     */
    public function generateTOTP(string $secret, ?int $time = null): string
    {
        $time = $time ?? time();

        // Convert time to 8-byte counter
        $timeHex = str_pad(dechex((int)($time / 30)), 16, '0', STR_PAD_LEFT);
        $timeBinary = hex2bin($timeHex);

        // Generate HMAC-SHA1
        $hmac = hash_hmac('sha1', $timeBinary, $this->base32Decode($secret), true);

        // Dynamic truncation
        $offset = ord(substr($hmac, -1)) & 0x0F;
        $binary = substr($hmac, $offset, 4);

        // Extract 31 bits and convert to 6-digit number
        $numeric = unpack('N', $binary)[1];
        $numeric = $numeric & 0x7FFFFFFF;

        return str_pad((string)($numeric % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate QR code as SVG data URL.
     *
     * @param string $secret Base32-encoded secret
     * @param string $email User's email address
     * @param string|null $issuer Application name
     * @return string SVG data URL
     */
    public function generateQRCodeSvg(string $secret, string $email, ?string $issuer = null): string
    {
        // This is a simple implementation - in production you might use a library
        // For now, we'll return the provisioning URI encoded
        $uri = $this->getProvisioningUri($secret, $email, $issuer ?? 'Attendance System');
        
        // Return the URI as a data URL (client can convert to QR)
        return 'data:otpauth/' . rawurlencode($uri);
    }

    /**
     * Check if MFA is properly configured.
     *
     * @return bool True if the system can generate QR codes
     */
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * Base32 encode helper.
     *
     * @param string $data Binary data
     * @return string Base32-encoded string
     */
    private function base32Encode(string $data): string
    {
        $output = '';
        $buffer = 0;
        $bitsLeft = 0;

        for ($i = 0; $i < strlen($data); $i++) {
            $buffer = ($buffer << 8) | ord($data[$i]);
            $bitsLeft += 8;

            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $output .= self::BASE32_ALPHABET[($buffer >> $bitsLeft) & 0x1F];
            }
        }

        if ($bitsLeft > 0) {
            $output .= self::BASE32_ALPHABET[($buffer << (5 - $bitsLeft)) & 0x1F];
        }

        return $output;
    }

    /**
     * Base32 decode helper.
     *
     * @param string $encoded Base32-encoded string
     * @return string Binary data
     */
    private function base32Decode(string $encoded): string
    {
        $encoded = str_replace(' ', '', strtoupper($encoded));
        
        $output = '';
        $buffer = 0;
        $bitsLeft = 0;

        for ($i = 0; $i < strlen($encoded); $i++) {
            $value = strpos(self::BASE32_ALPHABET, $encoded[$i]);
            if ($value === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $value;
            $bitsLeft += 5;

            while ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }
}