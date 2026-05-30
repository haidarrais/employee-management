<?php

namespace App\Services;

use App\Models\QRCode;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode as EndroidQrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Str;

/**
 * QRCode Service for generating and validating attendance QR codes.
 * Provides secure token generation with encryption and expiration handling.
 */
class QRCodeService
{
    private EncryptionService $encryptionService;

    /**
     * Create a new QRCodeService instance.
     */
    public function __construct(EncryptionService $encryptionService)
    {
        $this->encryptionService = $encryptionService;
    }

    /**
     * Generate a new QR code with encrypted token.
     * Creates a unique token, encrypts it, stores the hash for validation,
     * and sets expiration to 5 minutes.
     *
     * @param int $userId The ID of the user generating the QR code
     * @return QRCode The created QR code instance
     * @throws \RuntimeException If token generation or encryption fails
     */
    public function generate(int $userId): QRCode
    {
        // Generate a unique token with UUID and timestamp for uniqueness
        $token = $this->generateToken($userId);
        
        // Encrypt the token for secure storage/transmission
        $encryptedToken = $this->encryptionService->encryptQRToken($token);
        
        // Create hash of the raw token for validation (not the encrypted version)
        $tokenHash = $this->createTokenHash($token);
        
        // Calculate expiration time
        $generatedAt = now();
        $expiresAt = $generatedAt->copy()->addMinutes(QRCode::VALIDITY_MINUTES);
        // Create the QR code record
        $qrCode = QRCode::create([
            'generated_by' => $userId,
            'encrypted_token' => $encryptedToken,
            'token_hash' => $tokenHash,
            'status' => QRCode::STATUS_PENDING,
            'generated_at' => $generatedAt,
            'expires_at' => $expiresAt,
        ]);
        
        return $qrCode;
    }

    /**
     * Validate a QR code token.
     * Checks if the provided token matches the stored hash,
     * verifies the QR code is not expired and still pending.
     *
     * @param QRCode $qrCode The QR code record to validate against
     * @param string $providedToken The raw token provided by the scanner
     * @return array{valid: bool, message: string, qrCode?: QRCode}
     *         Validation result with success status and message
     */
    public function validate(QRCode $qrCode, string $providedToken): array
    {
        // Check if QR code has already been used
        if ($qrCode->isUsed()) {
            return [
                'valid' => false,
                'message' => 'QR code has already been used',
            ];
        }

        // Check if QR code has expired
        if ($qrCode->isExpired() || $qrCode->expires_at->isPast()) {
            $qrCode->markAsExpired();
            return [
                'valid' => false,
                'message' => 'QR code has expired',
            ];
        }

        // Validate the provided token against stored hash
        $providedHash = $this->createTokenHash($providedToken);

        if (!hash_equals($qrCode->token_hash, $providedHash)) {
            return [
                'valid' => false,
                'message' => 'Invalid QR code token',
            ];
        }

        // Token is valid, mark QR code as used
        $qrCode->markAsUsed();

        return [
            'valid' => true,
            'message' => 'QR code validated successfully',
            'qrCode' => $qrCode,
        ];
    }

    /**
     * Generate a unique token for the QR code.
     * Combines UUID, user ID, and timestamp for uniqueness.
     *
     * @param int $userId The user ID
     * @return string The generated token
     */
    private function generateToken(int $userId): string
    {
        $uuid = Str::uuid()->toString();
        $timestamp = time();
        $random = bin2hex(random_bytes(8));

        return sprintf(
            '%s:%d:%d:%s',
            $uuid,
            $userId,
            $timestamp,
            $random
        );
    }

    /**
     * Create a secure hash of the token for validation.
     * Uses SHA-256 for consistent hashing.
     *
     * @param string $token The raw token to hash
     * @return string The hashed token
     */
    private function createTokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Get the encrypted token from a QR code for display/generation.
     *
     * @param QRCode $qrCode The QR code record
     * @return string The encrypted token
     */
    public function getEncryptedToken(QRCode $qrCode): string
    {
        return $qrCode->encrypted_token;
    }

    /**
     * Render a QR code model into a base64 PNG data URI.
     * Centralises image generation so both web and API controllers
     * use the same output format and settings.
     *
     * @param QRCode $qrCode The QR code record to render
     * @return string Base64-encoded PNG data URI (data:image/png;base64,...)
     * @throws \RuntimeException If image generation fails
     */
    public function generateImage(QRCode $qrCode): string
    {
        $token = $this->getEncryptedToken($qrCode);

        $endroidQr = new EndroidQrCode(
            data:                 $token,
            encoding:             new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size:                 300,
            margin:               10,
            roundBlockSizeMode:   RoundBlockSizeMode::Margin,
            foregroundColor:      new Color(0, 0, 0),
            backgroundColor:      new Color(255, 255, 255),
        );

        $result = (new PngWriter())->write($endroidQr);

        return $result->getDataUri();
    }

    /**
     * Check if the QR code service is available and properly configured.
     *
     * @return bool True if the service can generate and validate QR codes
     */
    public function isAvailable(): bool
    {
        return $this->encryptionService->isAvailable();
    }
}