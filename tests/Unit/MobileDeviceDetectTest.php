<?php

namespace Tests\Unit;

use App\Http\Middleware\MobileDeviceDetect;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class MobileDeviceDetectTest extends TestCase
{
    private MobileDeviceDetect $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new MobileDeviceDetect();
    }

    /**
     * Test that iPhone User-Agent is detected as mobile.
     * @test
     */
    public function test_detects_iphone_as_mobile(): void
    {
        $userAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1';
        
        $isMobile = $this->detectMobile($userAgent);
        
        $this->assertTrue($isMobile, 'iPhone should be detected as mobile');
    }

    /**
     * Test that Android device is detected as mobile.
     * @test
     */
    public function test_detects_android_as_mobile(): void
    {
        $userAgent = 'Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Mobile Safari/537.36';
        
        $isMobile = $this->detectMobile($userAgent);
        
        $this->assertTrue($isMobile, 'Android should be detected as mobile');
    }

    /**
     * Test that iPad is detected as mobile.
     * @test
     */
    public function test_detects_ipad_as_mobile(): void
    {
        $userAgent = 'Mozilla/5.0 (iPad; CPU OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Safari/604.1';
        
        $isMobile = $this->detectMobile($userAgent);
        
        $this->assertTrue($isMobile, 'iPad should be detected as mobile');
    }

    /**
     * Test that desktop Chrome is not detected as mobile.
     * @test
     */
    public function test_detects_desktop_chrome_as_desktop(): void
    {
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Safari/537.36';
        
        $isMobile = $this->detectMobile($userAgent);
        
        $this->assertFalse($isMobile, 'Desktop Chrome should not be detected as mobile');
    }

    /**
     * Test that desktop Firefox is not detected as mobile.
     * @test
     */
    public function test_detects_desktop_firefox_as_desktop(): void
    {
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/112.0';
        
        $isMobile = $this->detectMobile($userAgent);
        
        $this->assertFalse($isMobile, 'Desktop Firefox should not be detected as mobile');
    }

    /**
     * Test that Mac Safari is not detected as mobile.
     * @test
     */
    public function test_detects_mac_safari_as_desktop(): void
    {
        $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.4 Safari/605.1.15';
        
        $isMobile = $this->detectMobile($userAgent);
        
        $this->assertFalse($isMobile, 'Mac Safari should not be detected as mobile');
    }

    /**
     * Test that Windows Phone is detected as mobile.
     * @test
     */
    public function test_detects_windows_phone_as_mobile(): void
    {
        $userAgent = 'Mozilla/5.0 (Windows Phone 10.0; Android 6.0.1; Microsoft; Lumia 950) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Mobile Safari/537.36 Edge/15.15254';
        
        $isMobile = $this->detectMobile($userAgent);
        
        $this->assertTrue($isMobile, 'Windows Phone should be detected as mobile');
    }

    /**
     * Test that BlackBerry is detected as mobile.
     * @test
     */
    public function test_detects_blackberry_as_mobile(): void
    {
        $userAgent = 'Mozilla/5.0 (BlackBerry; U; BlackBerry 9900; en) AppleWebKit/534.11+ (KHTML, like Gecko) Version/7.1.0.346 Mobile Safari/534.11+';
        
        $isMobile = $this->detectMobile($userAgent);
        
        $this->assertTrue($isMobile, 'BlackBerry should be detected as mobile');
    }

    /**
     * Test that Samsung device is detected as mobile.
     * @test
     */
    public function test_detects_samsung_as_mobile(): void
    {
        $userAgent = 'Mozilla/5.0 (Linux; Android 12; SM-G991B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.5615.135 Mobile Safari/537.36';
        
        $isMobile = $this->detectMobile($userAgent);
        
        $this->assertTrue($isMobile, 'Samsung device should be detected as mobile');
    }

    /**
     * Test that Huawei device is detected as mobile.
     * @test
     */
    public function test_detects_huawei_as_mobile(): void
    {
        $userAgent = 'Mozilla/5.0 (Linux; Android 12; HarmonyOS; MHA-L29; HUAWEI) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Mobile Safari/537.36';
        
        $isMobile = $this->detectMobile($userAgent);
        
        $this->assertTrue($isMobile, 'Huawei device should be detected as mobile');
    }

    /**
     * Test that empty User-Agent is not detected as mobile.
     * @test
     */
    public function test_empty_user_agent_is_not_mobile(): void
    {
        $userAgent = '';
        
        $isMobile = $this->detectMobile($userAgent);
        
        $this->assertFalse($isMobile, 'Empty User-Agent should not be detected as mobile');
    }

    /**
     * Test that Opera Mini is detected as mobile.
     * @test
     */
    public function test_detects_opera_mini_as_mobile(): void
    {
        $userAgent = 'Opera/9.80 (Android; Opera Mini/36.2.2254/119.132; U; en) Presto/2.12.423 Version/12.16';
        
        $isMobile = $this->detectMobile($userAgent);
        
        $this->assertTrue($isMobile, 'Opera Mini should be detected as mobile');
    }

    /**
     * Test that Xiaomi device is detected as mobile.
     * @test
     */
    public function test_detects_xiaomi_as_mobile(): void
    {
        $userAgent = 'Mozilla/5.0 (Linux; Android 11; Xiaomi) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Mobile Safari/537.36';
        
        $isMobile = $this->detectMobile($userAgent);
        
        $this->assertTrue($isMobile, 'Xiaomi device should be detected as mobile');
    }

    /**
     * Test that OPPO device is detected as mobile.
     * @test
     */
    public function test_detects_oppo_as_mobile(): void
    {
        $userAgent = 'Mozilla/5.0 (Linux; Android 12; CPH2375) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Mobile Safari/537.36';
        
        $isMobile = $this->detectMobile($userAgent);
        
        $this->assertTrue($isMobile, 'OPPO device should be detected as mobile');
    }

    /**
     * Test that desktop Linux is not detected as mobile.
     * @test
     */
    public function test_detects_desktop_linux_as_desktop(): void
    {
        $userAgent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Safari/537.36';
        
        $isMobile = $this->detectMobile($userAgent);
        
        $this->assertFalse($isMobile, 'Desktop Linux should not be detected as mobile');
    }

    /**
     * Call the private detectMobileDevice method via reflection.
     */
    private function detectMobile(string $userAgent): bool
    {
        $request = new Request();
        $request->headers->set('User-Agent', $userAgent);
        
        // Use reflection to call the private method
        $reflection = new \ReflectionClass($this->middleware);
        $method = $reflection->getMethod('detectMobileDevice');
        $method->setAccessible(true);
        
        return $method->invoke($this->middleware, $request);
    }
}