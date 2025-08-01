<?php

namespace Wesleydeveloper\DataProcessor\Tests\Mocks;

use Wesleydeveloper\DataProcessor\Contracts\Importable;
use Wesleydeveloper\DataProcessor\Contracts\ShouldQueue;
use Wesleydeveloper\DataProcessor\Contracts\WithChunking;

class TestImport implements Importable, ShouldQueue, WithChunking
{
    public array $processedData = [];
    public int $processCount = 0;

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'email' => 'required|email',
            'age' => 'nullable|integer|min:0'
        ];
    }

    public function map(array $row): array
    {
        return [
            'name'  => $row['name'] ?? null,
            'email' => $row['email'] ?? null,
            'age'   => isset($row['age']) ? (int) $row['age'] : null,
        ];
    }

    public function process(array $data): void
    {
        $this->processedData = array_merge($this->processedData, $data);
        $this->processCount++;
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function onQueue(): ?string
    {
        return 'test-queue';
    }

    public function timeout(): int
    {
        return 300;
    }

    public function memory(): int
    {
        return 256;
    }

    public function maxFileSize(): int
    {
        return 1024 * 1024; // 1MB
    }

    public function chunkRows(): int
    {
        return 500;
    }
}
