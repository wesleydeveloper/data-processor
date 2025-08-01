<?php

namespace Wesleydeveloper\DataProcessor\Tests\Feature;

use Wesleydeveloper\DataProcessor\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Wesleydeveloper\DataProcessor\Tests\Mocks\TestImport;
use Wesleydeveloper\DataProcessor\Tests\Mocks\TestExport;
use Wesleydeveloper\DataProcessor\DataProcessor;
use Wesleydeveloper\DataProcessor\FileManager;
use Wesleydeveloper\DataProcessor\ChunkProcessor;
use Wesleydeveloper\DataProcessor\Jobs\ProcessImportChunk;

class PerformanceTest extends TestCase
{
    private DataProcessor $dataProcessor;

    protected function setUp(): void
    {
        parent::setUp();

        $fileManager = new FileManager('testing');
        $chunkProcessor = new ChunkProcessor($fileManager);
        $this->dataProcessor = new DataProcessor($fileManager, $chunkProcessor);
    }

    public function testCanHandleLargeDatasetsEfficiently(): void
    {
        // Criar dataset grande para teste
        $largeData = [['Name', 'Email', 'Age']]; // Header

        for ($i = 1; $i <= 10000; $i++) {
            $largeData[] = [
                "User {$i}",
                "user{$i}@example.com",
                rand(18, 80)
            ];
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $filePath = $this->createTestExcelFile($largeData, 'large-test.xlsx');
        $content = file_get_contents($filePath);
        Storage::disk('testing')->put('large-import.xlsx', $content);

        
        $import = new class implements \Wesleydeveloper\DataProcessor\Contracts\Importable, \Wesleydeveloper\DataProcessor\Contracts\WithChunking {
            public array $processedData = [];
            public function rules(): array { return []; }
            public function map(array $row): array { return $row; }
            public function process(array $data): void { $this->processedData = array_merge($this->processedData, $data); }
            public function chunkSize(): int { return 1000; }
            public function maxFileSize(): int { return 0; }
            public function chunkRows(): int { return 1000; }
        };
        $this->dataProcessor->import($import, 'large-import.xlsx');

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $duration = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;

        // Verificar performance
        $this->assertLessThan(30, $duration, 'Import deve levar menos de 30 segundos');
        $this->assertLessThan(100 * 1024 * 1024, $memoryUsed, 'Uso de memória deve ser menor que 100MB');

        // Verificar dados processados
        $this->assertCount(10000, $import->processedData);

        echo "\n=== Performance Test Results ===\n";
        echo "Duration: " . round($duration, 2) . " seconds\n";
        echo "Memory used: " . round($memoryUsed / 1024 / 1024, 2) . " MB\n";
        echo "Rows processed: " . count($import->processedData) . "\n";
        echo "Rate: " . round(count($import->processedData) / $duration) . " rows/second\n";
    }

    public function testProcessesChunksCorrectlyForLargeFiles(): void
    {
        // Criar arquivo que será dividido em chunks
        $testData = [['Name', 'Email', 'Age']];

        for ($i = 1; $i <= 1500; $i++) { // Mais que maxFileSize simulado
            $testData[] = ["User {$i}", "user{$i}@example.com", rand(18, 80)];
        }

        $filePath = $this->createTestExcelFile($testData, 'chunked-test.xlsx');

        // Simular arquivo grande fazendo com que seja maior que maxFileSize
        $content = file_get_contents($filePath);
        Storage::disk('testing')->put('chunked-import.xlsx', $content);

        $import = new class extends TestImport {
            public function maxFileSize(): int
            {
                return 1024; // 1KB para forçar chunking
            }

            public function chunkRows(): int
            {
                return 500;
            }
        };

        $this->dataProcessor->import($import, 'chunked-import.xlsx');

        
        Queue::assertPushed(ProcessImportChunk::class);
    }

    public function testHandlesMemoryEfficientlyDuringExport(): void
    {
        // Criar grande dataset para exportar
        $largeDataset = [];
        for ($i = 1; $i <= 5000; $i++) {
            $largeDataset[] = ["User {$i}", "user{$i}@example.com", rand(18, 80)];
        }

        $startMemory = memory_get_usage(true);

        $export = new TestExport($largeDataset);
        $this->dataProcessor->export($export, 'large-export.xlsx', 'xlsx');

        $endMemory = memory_get_usage(true);
        $memoryUsed = $endMemory - $startMemory;

        // Verificar uso de memória
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, 'Export deve usar menos de 50MB');

        Storage::disk('testing')->assertExists('large-export.xlsx');

        echo "\n=== Export Memory Test ===\n";
        echo "Memory used: " . round($memoryUsed / 1024 / 1024, 2) . " MB\n";
        echo "Rows exported: " . count($largeDataset) . "\n";
    }
}
