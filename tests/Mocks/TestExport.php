<?php

namespace Wesleydeveloper\DataProcessor\Tests\Mocks;

use Wesleydeveloper\DataProcessor\Contracts\Exportable;
use Generator;

class TestExport implements Exportable
{
    private array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data ?: [
            ['John Doe', 'john@example.com', 25],
            ['Jane Smith', 'jane@example.com', 30],
            ['Bob Johnson', 'bob@example.com', 35],
        ];
    }

    public function query(): Generator
    {
        foreach ($this->data as $row) {
            yield $row;
        }
    }

    public function headings(): array
    {
        return ['Name', 'Email', 'Age'];
    }

    public function map($row): array
    {
        return [
            $row[0], // name
            $row[1], // email
            $row[2], // age
        ];
    }

    public function chunkSize(): int
    {
        return 50;
    }
}
