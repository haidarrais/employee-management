<?php

namespace Tests\Unit;

use App\Services\MFAService;
use PHPUnit\Framework\TestCase;

class MFAServiceTest extends TestCase
{
    private MFAService $mfaService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mfaService = new MFAService();
    }

    /**
     * Test that generateSecret returns a non-empty string.
     */
    public function test_generate_secret_returns_string(): void
    {
        $secret = $this->mfaService->generateSecret();
        
        $this->assertNotEmpty($secret);
        $this->assertIsString($secret);
    }

    /**
     * Test that generateSecret returns different secrets each time.
     */
    public function test_generate_secret_returns_unique_secrets(): void
    {
        $secret1 = $this->mfaService->generateSecret();
        $secret2 = $this->mfaService->generateSecret();
        
        $this->assertNotEquals($secret1, $secret2);
    }

    /**
     * Test that getQRCodeUrl returns a valid otpauth URL.
     */
    public function test_get_qr_code_url_returns_valid_format(): void
    {
        $secret = $this->mfaService->generateSecret();
        $email = 'test@example.com';
        
        $url = $this->mfaService->getQRCodeUrl($secret, $email);
        
        $this->assertStringStartsWith('otpauth://totp/', $url);
        // Email is URL-encoded in the URI
        $this->assertStringContainsString(rawurlencode($email), $url);
    }

    /**
     * Test that getQRCodeUrl includes issuer when provided.
     */
    public function test_get_qr_code_url_includes_issuer(): void
    {
        $secret = $this->mfaService->generateSecret();
        $email = 'test@example.com';
        $issuer = 'TestApp';
        
        $url = $this->mfaService->getQRCodeUrl($secret, $email, $issuer);
        
        $this->assertStringContainsString($issuer, $url);
    }

    /**
     * Test that verify returns false for empty secret.
     */
    public function test_verify_returns_false_for_empty_secret(): void
    {
        $result = $this->mfaService->verify('', '123456');
        
        $this->assertFalse($result);
    }

    /**
     * Test that verify returns false for empty code.
     */
    public function test_verify_returns_false_for_empty_code(): void
    {
        $secret = $this->mfaService->generateSecret();
        
        $result = $this->mfaService->verify($secret, '');
        
        $this->assertFalse($result);
    }

    /**
     * Test that verify returns false for invalid code format.
     */
    public function test_verify_returns_false_for_invalid_code_format(): void
    {
        $secret = $this->mfaService->generateSecret();
        
        // Code too short
        $this->assertFalse($this->mfaService->verify($secret, '12345'));
        
        // Code too long
        $this->assertFalse($this->mfaService->verify($secret, '1234567'));
        
        // Code with non-digits
        $this->assertFalse($this->mfaService->verify($secret, '12ab56'));
    }

    /**
     * Test that verify returns true for valid current TOTP code.
     */
    public function test_verify_returns_true_for_valid_code(): void
    {
        $secret = $this->mfaService->generateSecret();
        $currentCode = $this->mfaService->getCurrentCode($secret);
        
        $result = $this->mfaService->verify($secret, $currentCode);
        
        $this->assertTrue($result);
    }

    /**
     * Test that verify returns false for invalid code.
     */
    public function test_verify_returns_false_for_invalid_code(): void
    {
        $secret = $this->mfaService->generateSecret();
        
        // Use a definitely wrong code
        $result = $this->mfaService->verify($secret, '000000');
        
        $this->assertFalse($result);
    }

    /**
     * Test that the provisioning URI is rendered as a real, scannable PNG.
     *
     * This previously asserted a `data:otpauth/...` prefix, which was not an
     * image at all and could not be rendered as a QR code — the MFA setup page
     * had nothing to show. The method now returns a base64 PNG, so the test
     * asserts an actual image and that the otpauth URI survives inside it.
     */
    public function test_generate_qr_code_returns_a_png_data_url(): void
    {
        $secret = $this->mfaService->generateSecret();
        $email  = 'test@example.com';

        $qrUrl = $this->mfaService->generateQRCodeDataUri($secret, $email);

        $this->assertStringStartsWith('data:image/png;base64,', $qrUrl);

        $binary = base64_decode(substr($qrUrl, strlen('data:image/png;base64,')), true);
        $this->assertNotFalse($binary, 'The data URL does not carry valid base64.');
        $this->assertStringStartsWith("\x89PNG", $binary, 'The decoded payload is not a PNG.');
    }

    /**
     * Test that getCurrentCode returns a 6-digit code.
     */
    public function test_get_current_code_returns_six_digits(): void
    {
        $secret = $this->mfaService->generateSecret();
        
        $code = $this->mfaService->getCurrentCode($secret);
        
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    /**
     * Test that isAvailable returns true when dependencies are present.
     */
    public function test_is_available_returns_true(): void
    {
        $this->assertTrue($this->mfaService->isAvailable());
    }
}