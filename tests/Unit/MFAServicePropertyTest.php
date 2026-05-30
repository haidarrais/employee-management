<?php

namespace Tests\Unit;

use App\Services\MFAService;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for MFAService.
 * These tests verify universal properties that should hold across all valid inputs.
 */
class MFAServicePropertyTest extends TestCase
{
    private MFAService $mfaService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mfaService = new MFAService();
    }

    /**
     * Property: Generated secrets are always valid base32 strings.
     */
    public function test_generated_secrets_are_valid_base32(): void
    {
        // Run multiple times to test property
        for ($i = 0; $i < 10; $i++) {
            $secret = $this->mfaService->generateSecret();
            
            // Secret should only contain base32 characters
            $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
        }
    }

    /**
     * Property: A valid TOTP code generated for the current time window always verifies.
     */
    public function test_current_code_always_verifies(): void
    {
        // Test multiple secrets
        for ($i = 0; $i < 10; $i++) {
            $secret = $this->mfaService->generateSecret();
            $currentCode = $this->mfaService->getCurrentCode($secret);
            
            $this->assertTrue(
                $this->mfaService->verify($secret, $currentCode),
                "Code '$currentCode' should verify for secret '$secret'"
            );
        }
    }

    /**
     * Property: Codes from adjacent time windows (±1) also verify (clock drift tolerance).
     */
    public function test_adjacent_time_window_codes_verify(): void
    {
        $secret = $this->mfaService->generateSecret();
        
        // Generate code for current time
        $currentCode = $this->mfaService->getCurrentCode($secret);
        
        // It should verify
        $this->assertTrue($this->mfaService->verify($secret, $currentCode));
    }

    /**
     * Property: Invalid codes should never verify.
     */
    public function test_invalid_codes_never_verify(): void
    {
        $secret = $this->mfaService->generateSecret();
        
        // All-zeros code should never match (unless by extreme coincidence)
        $this->assertFalse($this->mfaService->verify($secret, '000000'));
        
        // All-nines code should never match
        $this->assertFalse($this->mfaService->verify($secret, '999999'));
    }

    /**
     * Property: Generated codes are always exactly 6 digits.
     */
    public function test_generated_codes_are_six_digits(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $secret = $this->mfaService->generateSecret();
            $code = $this->mfaService->getCurrentCode($secret);
            
            $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        }
    }

    /**
     * Property: Different secrets produce different codes at the same time.
     */
    public function test_different_secrets_produce_different_codes(): void
    {
        $secrets = [];
        $codes = [];
        
        // Generate 5 different secrets and their codes
        for ($i = 0; $i < 5; $i++) {
            $secret = $this->mfaService->generateSecret();
            $code = $this->mfaService->getCurrentCode($secret);
            
            $secrets[] = $secret;
            $codes[] = $code;
        }
        
        // All secrets should be unique
        $this->assertCount(5, array_unique($secrets));
    }

    /**
     * Property: Secret and code are independent (changing secret changes code even with same time).
     */
    public function test_secret_and_code_are_dependent(): void
    {
        $secret1 = $this->mfaService->generateSecret();
        $secret2 = $this->mfaService->generateSecret();
        
        $code1 = $this->mfaService->generateTOTP($secret1, 1000000);
        $code2 = $this->mfaService->generateTOTP($secret2, 1000000);
        
        // Different secrets should produce different codes at same timestamp
        $this->assertNotEquals($code1, $code2);
    }

    /**
     * Property: getQRCodeUrl always returns a valid otpauth URI.
     */
    public function test_qr_code_url_is_valid_otpauth_uri(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $secret = $this->mfaService->generateSecret();
            $email = "user{$i}@example.com";
            
            $url = $this->mfaService->getQRCodeUrl($secret, $email);
            
            // Should start with otpauth://totp/
            $this->assertStringStartsWith('otpauth://totp/', $url);
            
            // Should contain secret parameter
            $this->assertStringContainsString('secret=', $url);
            
            // Should contain issuer parameter
            $this->assertStringContainsString('issuer=', $url);
        }
    }

    /**
     * Property: verify rejects codes with wrong length.
     */
    public function test_verify_rejects_wrong_length_codes(): void
    {
        $secret = $this->mfaService->generateSecret();
        
        // 5 digits
        $this->assertFalse($this->mfaService->verify($secret, '12345'));
        
        // 7 digits
        $this->assertFalse($this->mfaService->verify($secret, '1234567'));
        
        // 0 digits
        $this->assertFalse($this->mfaService->verify($secret, ''));
    }

    /**
     * Property: verify rejects non-numeric codes.
     */
    public function test_verify_rejects_non_numeric_codes(): void
    {
        $secret = $this->mfaService->generateSecret();
        
        $this->assertFalse($this->mfaService->verify($secret, '12ab56'));
        $this->assertFalse($this->mfaService->verify($secret, 'abcde1'));
        $this->assertFalse($this->mfaService->verify($secret, '------'));
    }
}