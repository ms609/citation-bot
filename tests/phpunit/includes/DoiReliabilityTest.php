<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class DoiReliabilityTest extends testBaseClass {
    public function testThrottleDelayUsesSecondsToMicrosecondsConversion(): void {
        $this->assertSame(50000, dx_throttle_delay(10.0, 10.0));
        $half_delay = dx_throttle_delay(10.020, 10.0);
        $this->assertGreaterThanOrEqual(19999, $half_delay);
        $this->assertLessThanOrEqual(20001, $half_delay);
        $this->assertSame(0, dx_throttle_delay(10.041, 10.0));
        $this->assertSame(0, dx_throttle_delay(11.0, 10.0));
    }

    public function testFirstThrottleCallDoesNotSleep(): void {
        $this->assertSame(0, dx_throttle_delay(10.0, 0.0));
    }
}
