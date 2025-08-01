<?php

namespace Wesleydeveloper\DataProcessor\Tests\Feature;

use Wesleydeveloper\DataProcessor\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Wesleydeveloper\DataProcessor\Tests\Mocks\TestImport;
use Wesleydeveloper\DataProcessor\DataProcessor;
use Wesleydeveloper\DataProcessor\FileManager;
use Wesleydeveloper\DataProcessor\ChunkProcessor;

/**
 * @group benchmark
 */
class BenchmarkTest extends TestCase
{
    public function testBenchmarkImportPerformance(): void
    {
        $sizes = [1000, 5000, 10000, 25000];
        $results = [];

        foreach ($sizes as $size) {
            $result = $this->benchmarkImport($size);
            $results[] = $result;

            echo "\n=== Benchmark Results for {$size} rows ===\n";
            echo "Duration: {$result['duration']} seconds\n";
            echo "Memory: {$result['memory']} MB\n";
            echo "Rate: {$result['rate']} rows/second\n";
        }

        // Verificar que a performance escala adequadamente
        $lastResult = end($results);
        $this->assertLessThan(60, $lastResult['duration'], 'Import de 25k linhas deve levar menos de 60s');
        $this->assertLessThan(200, $lastResult['memory'], 'Import deve usar menos de 200MB');
    }

    private function benchmarkImport(int $rowCount): array
    {
        $testData = [['Name', 'Email', 'Age']];

        for ($i = 1; $i <= $rowCount; $i++) {
            $testData[] = [
                "User {$i}",
                "user{$i}@example.com",
                rand(18, 80)
            ];
        }

        $filePath = $this->createTestExcelFile($testData, "benchmark-{$rowCount}.xlsx");
        $content = file_get_contents($filePath);
        Storage::disk('testing')->put("benchmark-{$rowCount}.xlsx", $content);

        $fileManager = new FileManager('testing');
        $chunkProcessor = new ChunkProcessor($fileManager);
        $dataProcessor = new DataProcessor($fileManager, $chunkProcessor);

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $import = new TestImport();
        $dataProcessor->import($import, "benchmark-{$rowCount}.xlsx");

        $endTime = microtime(true);
        $endMemory = memory_get_peak_usage(true);

        $duration = round($endTime - $startTime, 2);
        $memory = round(($endMemory - $startMemory) / 1024 / 1024, 2);
        $rate = round($rowCount / ($endTime - $startTime));

        return [
            'rows' => $rowCount,
            'duration' => $duration,
            'memory' => $memory,
            'rate' => $rate
        ];
    }
}
