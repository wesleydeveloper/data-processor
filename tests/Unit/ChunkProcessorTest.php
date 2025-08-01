<?php

namespace Wesleydeveloper\DataProcessor\Tests\Unit;

use Wesleydeveloper\DataProcessor\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Wesleydeveloper\DataProcessor\ChunkProcessor;
use Wesleydeveloper\DataProcessor\FileManager;

class ChunkProcessorTest extends TestCase
{
    private ChunkProcessor $chunkProcessor;

    protected function setUp(): void
    {
        parent::setUp();

        $fileManager = new FileManager('testing');
        $this->chunkProcessor = new ChunkProcessor($fileManager);
    }

    public function testCanSplitExcelFileIntoChunks(): void
    {
        // Criar arquivo Excel com dados de teste
        $testData = [
            ['Name', 'Email', 'Age'], // Header
            ['John Doe', 'john@example.com', 25],
            ['Jane Smith', 'jane@example.com', 30],
            ['Bob Johnson', 'bob@example.com', 35],
            ['Alice Brown', 'alice@example.com', 28],
            ['Charlie Wilson', 'charlie@example.com', 40],
        ];

        $filePath = $this->createTestExcelFile($testData);

        $reader = new \OpenSpout\Reader\XLSX\Reader();
        $chunks = [];

        foreach ($this->chunkProcessor->splitFile($reader, $filePath, 2, 'xlsx') as $chunkPath) {
            $chunks[] = $chunkPath;
            $this->assertFileExists($chunkPath);
        }

        // Deve criar 3 chunks (2 linhas cada, excluindo header)
        $this->assertCount(3, $chunks);

        // Verificar conteúdo do primeiro chunk
        $chunkReader = new \OpenSpout\Reader\XLSX\Reader();
        $chunkReader->open($chunks[0]);

        $rowCount = 0;
        foreach ($chunkReader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rowCount++;
            }
        }
        $chunkReader->close();

        // Header + 2 linhas de dados
        $this->assertEquals(3, $rowCount);

        // Cleanup
        foreach ($chunks as $chunkPath) {
            unlink($chunkPath);
        }
    }

    public function testHandlesSmallFilesWithoutChunking(): void
    {
        $testData = [
            ['Name', 'Email'],
            ['John', 'john@example.com'],
        ];

        $filePath = $this->createTestExcelFile($testData);
        $reader = new \OpenSpout\Reader\XLSX\Reader();

        $chunks = iterator_to_array(
            $this->chunkProcessor->splitFile($reader, $filePath, 10, 'xlsx')
        );

        // Arquivo pequeno deve gerar apenas 1 chunk
        $this->assertCount(1, $chunks);
    }

    public function testSplitsChunksToCloudStorageWhenEnabled(): void
    {
        config()->set('data-processor.use_cloud_temp', true);
        Storage::fake('testing');
        $fileManager = new FileManager('testing');
        $chunkProcessor = new ChunkProcessor($fileManager);

        $testData = [
            ['Name'],
            ['A'],
            ['B'],
        ];
        $filePath = $this->createTestCsvFile($testData);

        $chunks = iterator_to_array(
            $chunkProcessor->splitFile(
                new \OpenSpout\Reader\CSV\Reader(),
                $filePath,
                1,
                'csv'
            )
        );

        // Deve criar 2 chunks na cloud storage
        $this->assertCount(2, $chunks);
        foreach ($chunks as $cloudPath) {
            Storage::disk('testing')->assertExists($cloudPath);
        }
    }
}
