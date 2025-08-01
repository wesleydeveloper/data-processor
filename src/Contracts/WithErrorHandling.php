<?php

namespace Wesleydeveloper\DataProcessor\Contracts;

interface WithErrorHandling
{
    /**
     * @param \Throwable $error
     * @param array $row
     * @param int $rowNumber
     */
    public function onError(\Throwable $error, array $row, int $rowNumber): void;

    /**
     * @return bool
     */
    public function shouldSkipOnError(): bool;

    /**
     * @return int|null
     */
    public function maxErrors(): ?int;
}
