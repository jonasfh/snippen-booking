<?php

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\AvailabilityService;

class OverlapReproductionTest extends TestCase {

    public function testConsecutiveWholeDayBookingsDoNotOverlap() {
        $service = new AvailabilityService();
        
        // Window 1: Day 1, 11:00 - 23:00 + 12h cleanup
        // Logical end: Day 2, 11:00
        $win1 = $this->callPrivateMethod($service, 'calculateWindow', ['2026-05-20', '11:00:00', '23:00:00', 12]);
        
        // Window 2: Day 2, 11:00 - 23:00 + 12h cleanup
        // Logical start: Day 2, 11:00
        $win2 = $this->callPrivateMethod($service, 'calculateWindow', ['2026-05-21', '11:00:00', '23:00:00', 12]);
        
        $isOverlapping = $this->callPrivateMethod($service, 'isOverlapping', [$win1, $win2]);
        
        $this->assertFalse($isOverlapping, 'Consecutive bookings should NOT overlap');
    }

    private function callPrivateMethod($object, $methodName, $parameters) {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
}
