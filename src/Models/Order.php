<?php

namespace OrderReport\Models;

class Order
{
    public function __construct(
        public readonly string $id,
        public readonly string $customerId,
        public readonly string $productId,
        public readonly int $qty,
        public readonly float $unitPrice,
        public readonly string $date,
        public readonly string $promoCode,
        public readonly string $time
    ) {
    }
}

