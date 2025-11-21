<?php

namespace OrderReport\Models;

class Promotion
{
    public function __construct(
        public readonly string $code,
        public readonly string $type,
        public readonly string $value,
        public readonly bool $active
    ) {
    }
}

