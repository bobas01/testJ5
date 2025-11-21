<?php

namespace OrderReport\Models;

class Customer
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $level,
        public readonly string $shippingZone,
        public readonly string $currency
    ) {
    }
}

