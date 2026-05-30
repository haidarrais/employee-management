<?php

namespace App\Services;

/**
 * Encryption Service for secure token handling.
 * Uses AES-256-GCM for authenticated encryption of QR tokens.
 */
class EncryptionService
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 16; // 128 bits for GCM
    private const TAG_LENGTH = 16; // 128 bits authentication tag

    private string $key;

    /**
     * Create a new EncryptionService instance.
     * Retrieves the encryption key from app configuration.
     */
    public function __construct()
    {
        $this->key = $this->getEncryptionKey();
    }

    /**
     * Get the encryption key from configuration.
     * Falls back to APP_KEY if encryption_key is not set.
     *
     * @return string 32-byte encryption key
     * @throws \RuntimeException If no valid key is configured
     */
    private function getEncryptionKey(): string
    {
        // Try to get from app config first
        $key = config('app.encryption_key') ?? config('app.key');

        if (empty($key)) {
            throw new \RuntimeException('Encryption key not configured');
        }

        // Extract key from base64 encoding if needed
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        // Ensure key is exactly 32 bytes for AES-256
        if (strlen($key) < 32) {
            // Pad or hash to get 32 bytes
            $key = str_pad($key, 32, "\0", STR_PAD_RIGHT);
        } elseif (strlen($key) > 32) {
            // Truncate to 32 bytes
            $key = substr($key, 0, 32);
        }

        return $key;
    }

    /**
     * Encrypt a QR token using AES-256-GCM.
     * Uses a random IV for each encryption and includes the authentication tag.
     *
     * @param string $token The plaintext token to encrypt
     * @return string Base64-encoded encrypted token (IV + tag + ciphertext)
     * @throws \RuntimeException If encryption fails
     */
    public function encryptQRToken(string $token): string
    {
        if (empty($token)) {
            throw new \InvalidArgumentException('Token cannot be empty');
        }

        // Generate random IV for each encryption
        $iv = random_bytes(self::IV_LENGTH);

        // Encrypt with AES-256-GCM
        $ciphertext = openssl_encrypt(
            $token,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed: ' . openssl_error_string());
        }

        // Combine: IV (16 bytes) + Tag (16 bytes) + Ciphertext
        $encrypted = $iv . $tag . $ciphertext;

        // Return as base64 for safe transport
        return base64_encode($encrypted);
    }

    /**
     * Decrypt an encrypted QR token.
     * Verifies the authentication tag to ensure data integrity.
     *
     * @param string $encrypted Base64-encoded encrypted token
     * @return string|null The decrypted token, or null if decryption fails
     */
    public function decryptQRToken(string $encrypted): ?string
    {
        if (empty($encrypted)) {
            return null;
        }

        // Decode from base64
        $data = base64_decode($encrypted, true);
        if ($data === false) {
            return null;
        }

        // Ensure we have enough data for IV + tag
        if (strlen($data) < self::IV_LENGTH + self::TAG_LENGTH) {
            return null;
        }

        // Extract components: IV (16 bytes) + Tag (16 bytes) + Ciphertext
        $iv = substr($data, 0, self::IV_LENGTH);
        $tag = substr($data, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($data, self::IV_LENGTH + self::TAG_LENGTH);

        // Decrypt and verify authentication tag
        $decrypted = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        // openssl_decrypt returns false on tag verification failure
        return $decrypted !== false ? $decrypted : null;
    }

    /**
     * Verify that the encryption service is properly configured.
     *
     * @return bool True if the service can encrypt/decrypt
     */
    public function isAvailable(): bool
    {
        try {
            $testToken = 'test-token-' . uniqid();
            $encrypted = $this->encryptQRToken($testToken);
            $decrypted = $this->decryptQRToken($encrypted);
            return $decrypted === $testToken;
        } catch (\Throwable $e) {
            return false;
        }
    }
}