<?php

namespace Wesleydeveloper\DataProcessor\Contracts;

interface Importable
{
    /**
     * @param array $row
     * @return array
     */
    public function map(array $row): array;

    /**
     * @param array $data
     */
    public function process(array $data): void;

    /**
     * @return int
     */
    public function chunkSize(): int;
}
