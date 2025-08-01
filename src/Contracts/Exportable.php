<?php

namespace Wesleydeveloper\DataProcessor\Contracts;

use Generator;

interface Exportable
{
    /**
     * @return Generator
     */
    public function query(): Generator;

    /**
     * @return array
     */
    public function headings(): array;

    /**
     * @param mixed $row
     * @return array
     */
    public function map(mixed $row): array;

    /**
     * @return int
     */
    public function chunkSize(): int;
}
