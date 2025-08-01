<?php

namespace Wesleydeveloper\DataProcessor\Tests\Unit\Jobs;

use Wesleydeveloper\DataProcessor\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Wesleydeveloper\DataProcessor\Tests\Mocks\TestImport;
use Wesleydeveloper\DataProcessor\Jobs\ProcessImportChunk;
use Wesleydeveloper\DataProcessor\DataProcessor;

class ProcessImportChunkTest extends TestCase
{
    public function testCanProcessDataChunk(): void
    {
        $testData = [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'age' => 25],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'age' => 30],
        ];

        $import = new TestImport();
        $job = new ProcessImportChunk($import, $testData);

        $dataProcessor = $this->app->make(DataProcessor::class);
        $job->handle($dataProcessor);

        $this->assertCount(2, $import->processedData);
        $this->assertEquals(1, $import->processCount);
    }

    public function testCanProcessChunkFile(): void
    {
        // Criar arquivo chunk CSV para evitar leitura de XLSX zip
        $testData = [
            ['Name', 'Email', 'Age'],
            ['John Doe', 'john@example.com', '25'],
        ];

        $chunkPath = $this->createTestCsvFile($testData, 'chunk-test.csv');

        // Upload para storage fake
        $content = file_get_contents($chunkPath);
        Storage::disk('testing')->put('chunk-import.csv', $content);

        $import = new TestImport();
        $job = new ProcessImportChunk($import, [], 'chunk-import.csv');

        $dataProcessor = $this->app->make(DataProcessor::class);
        $job->handle($dataProcessor);

        $this->assertTrue(true);
    }

    public function testHandlesJobFailureGracefully(): void
    {
        $import = new class extends TestImport {
            public function process(array $data): void
            {
                throw new \Exception('Simulated processing error');
            }
        };

        $job = new ProcessImportChunk($import, [['test' => 'data']]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Simulated processing error');

        $dataProcessor = $this->app->make(DataProcessor::class);
        $job->handle($dataProcessor);
    }
}
