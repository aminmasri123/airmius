<?php

namespace Tests\Unit;

use App\Support\CarrierTracking;
use PHPUnit\Framework\TestCase;

class CarrierTrackingTest extends TestCase
{
    public function test_it_normalizes_known_carriers_and_tracking_numbers(): void
    {
        $this->assertSame('DHL', CarrierTracking::normalizeCarrier(' dhl '));
        $this->assertSame('UPS', CarrierTracking::normalizeCarrier('United Parcel Service'));
        $this->assertSame('1Z999AA10123456784', CarrierTracking::trackingNumber('1z 999 aa 10123456784'));
    }

    public function test_it_builds_tracking_url_when_known_carrier_has_number(): void
    {
        $this->assertSame(
            'https://www.ups.com/track?tracknum=1Z999AA10123456784',
            CarrierTracking::trackingUrl('UPS', '1Z999AA10123456784'),
        );
    }

    public function test_it_keeps_manual_tracking_url(): void
    {
        $this->assertSame(
            'https://tracking.example.test/abc',
            CarrierTracking::trackingUrl('DHL', 'ABC123', 'https://tracking.example.test/abc'),
        );
    }
}
