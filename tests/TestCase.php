<?php

namespace Wesleydeveloper\DataProcessor\Tests;

use Illuminate\Foundation\Testing\Concerns\InteractsWithContainer;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;

abstract class TestCase extends BaseTestCase
{
    use WithFaker;
    use InteractsWithContainer;

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            \Wesleydeveloper\DataProcessor\DataProcessorServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Setup fake storage
        Storage::fake('testing');
        Storage::fake('gcs');

        // Setup fake queue
        Queue::fake();
    }

    /**
     * Criar arquivo Excel de teste
     */
    protected function createTestExcelFile(array $data, string $filename = 'test.xlsx'): string
    {
        $writer = new \OpenSpout\Writer\XLSX\Writer();
        $filePath = storage_path('app/testing/' . $filename);

        // Garantir que diretório existe
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $writer->openToFile($filePath);

        foreach ($data as $row) {
            $cells = [];
            foreach ($row as $value) {
                $cells[] = \OpenSpout\Common\Entity\Cell::fromValue($value);
            }
            $writer->addRow(new \OpenSpout\Common\Entity\Row($cells));
        }

        $writer->close();

        return $filePath;
    }

    /**
     * Criar arquivo CSV de teste
     */
    protected function createTestCsvFile(array $data, string $filename = 'test.csv'): string
    {
        $filePath = storage_path('app/testing/' . $filename);

        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $handle = fopen($filePath, 'w');
        foreach ($data as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $filePath;
    }

    protected function tearDown(): void
    {
        // Limpar arquivos de teste
        $testDir = storage_path('app/testing');
        if (is_dir($testDir)) {
            $this->deleteDirectory($testDir);
        }

        parent::tearDown();
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
