<?php

namespace OrderReport\Models;

class ShippingZone
{
    public function __construct(
        public readonly string $zone,
        public readonly float $base,
        public readonly float $perKg
    ) {
    }
}

