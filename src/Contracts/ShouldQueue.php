<?php

namespace Wesleydeveloper\DataProcessor\Contracts;

interface ShouldQueue
{
    /**
     * @return string|null
     */
    public function onQueue(): ?string;

    /**
     * @return int
     */
    public function timeout(): int;

    /**
     * @return int
     */
    public function memory(): int;
}
