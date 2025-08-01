<?php

namespace Wesleydeveloper\DataProcessor\Tests\Unit;

use Wesleydeveloper\DataProcessor\Tests\TestCase;
use Wesleydeveloper\DataProcessor\Tests\Mocks\TestImport;
use Wesleydeveloper\DataProcessor\Tests\Mocks\TestExport;
use Wesleydeveloper\DataProcessor\DataProcessor;
use Wesleydeveloper\DataProcessor\FileManager;
use Wesleydeveloper\DataProcessor\ChunkProcessor;
use Wesleydeveloper\DataProcessor\Jobs\ProcessImportChunk;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class DataProcessorTest extends TestCase
{
    private DataProcessor $dataProcessor;

    protected function setUp(): void
    {
        parent::setUp();

        $fileManager = new FileManager('testing');
        $chunkProcessor = new ChunkProcessor($fileManager);
        $this->dataProcessor = new DataProcessor($fileManager, $chunkProcessor);
    }

    public function testCanProcessSmallImportSynchronously(): void
    {
        // Criar arquivo de teste
        $testData = [
            ['Name', 'Email', 'Age'],
            ['John Doe', 'john@example.com', '25'],
            ['Jane Smith', 'jane@example.com', '30'],
        ];

        $filePath = $this->createTestExcelFile($testData);

        // Fazer upload para storage fake
        $content = file_get_contents($filePath);
        Storage::disk('testing')->put('import-test.xlsx', $content);

        
        $import = new class implements \Wesleydeveloper\DataProcessor\Contracts\Importable {
            public array $processedData = [];
            public function chunkSize(): int
            {
                return 100;
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
            }
        };
        $this->dataProcessor->import($import, 'import-test.xlsx');

        
        $this->assertCount(2, $import->processedData);
        $this->assertEquals('John Doe', $import->processedData[0]['name']);
        $this->assertEquals('jane@example.com', $import->processedData[1]['email']);
    }

    public function testCanQueueImportJobs(): void
    {
        $testData = [
            ['Name', 'Email', 'Age'],
            ['John Doe', 'john@example.com', '25'],
        ];

        $filePath = $this->createTestExcelFile($testData);
        $content = file_get_contents($filePath);
        Storage::disk('testing')->put('queue-test.xlsx', $content);

        $import = new TestImport();
        $this->dataProcessor->import($import, 'queue-test.xlsx');

        // Verificar se jobs foram despachados
        Queue::assertPushed(ProcessImportChunk::class);
    }

    public function testCanExportDataToExcel(): void
    {
        $testData = [
            ['Alice Johnson', 'alice@example.com', 28],
            ['Bob Smith', 'bob@example.com', 35],
        ];

        $export = new TestExport($testData);
        $this->dataProcessor->export($export, 'export-test.xlsx', 'xlsx');

        // Verificar se arquivo foi criado no storage
        Storage::disk('testing')->assertExists('export-test.xlsx');

        // Verificar conteúdo do arquivo
        $content = Storage::disk('testing')->get('export-test.xlsx');
        $tempPath = storage_path('app/temp-export-test.xlsx');
        file_put_contents($tempPath, $content);

        $reader = new \OpenSpout\Reader\XLSX\Reader();
        $reader->open($tempPath);

        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rowData = [];
                foreach ($row->getCells() as $cell) {
                    $rowData[] = $cell->getValue();
                }
                $rows[] = $rowData;
            }
        }
        $reader->close();

        // Header + 2 linhas de dados
        $this->assertCount(3, $rows);
        $this->assertEquals(['Name', 'Email', 'Age'], $rows[0]); // Header
        $this->assertEquals(['Alice Johnson', 'alice@example.com', 28], $rows[1]);

        // Cleanup
        unlink($tempPath);
    }

    public function testCanExportDataToCsv(): void
    {
        $export = new TestExport();
        $this->dataProcessor->export($export, 'export-test.csv', 'csv');

        Storage::disk('testing')->assertExists('export-test.csv');

        $content = Storage::disk('testing')->get('export-test.csv');
        $lines = explode("\n", trim($content));

        // Header + 3 linhas de dados
        $this->assertCount(4, $lines);
        $this->assertStringContainsString('Name,Email,Age', $lines[0]);
    }
}
