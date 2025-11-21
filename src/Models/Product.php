<?php

namespace OrderReport\Models;

class Product
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $category,
        public readonly float $price,
        public readonly float $weight,
        public readonly bool $taxable
    ) {
    }
}

