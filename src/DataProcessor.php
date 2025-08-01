<?php

namespace Wesleydeveloper\DataProcessor;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Writer\WriterInterface;
use Wesleydeveloper\DataProcessor\Contracts\Exportable;
use Wesleydeveloper\DataProcessor\Contracts\Importable;
use Wesleydeveloper\DataProcessor\Contracts\ShouldQueue;
use Wesleydeveloper\DataProcessor\Contracts\WithChunking;
use Wesleydeveloper\DataProcessor\Contracts\WithErrorHandling;
use Wesleydeveloper\DataProcessor\Contracts\WithProgress;
use Wesleydeveloper\DataProcessor\Exceptions\ProcessingException;
use Wesleydeveloper\DataProcessor\Jobs\ProcessImportChunk;

class DataProcessor
{
    private FileManager $fileManager;

    private ChunkProcessor $chunkProcessor;

    protected int $totalRows = 0;

    protected int $processedRows = 0;

    protected int $errorCount = 0;

    public function __construct(
        FileManager $fileManager,
        ChunkProcessor $chunkProcessor
    ) {
        $this->fileManager = $fileManager;
        $this->chunkProcessor = $chunkProcessor;
    }

    public function import(Importable $import, string $filePath): void
    {
        $startTime = microtime(true);

        Log::info('[DataProcessor] Iniciando importação', [
            'arquivo' => $filePath,
            'classe' => get_class($import),
        ]);

        ini_set('memory_limit', 512 . 'M');
        ini_set('max_execution_time', 3600);
        ini_set('max_input_time', 3600);
        set_time_limit(3600);

        $localPath = ! file_exists($filePath) ? $this->fileManager->downloadToTemp($filePath) : $filePath;

        try {
            $this->totalRows = $this->fileManager->getTotalRows($localPath);
            $this->processedRows = 0;
            $this->errorCount = 0;

            if ($import instanceof WithProgress) {
                $import->onStart($this->totalRows);
            }

            if ($import instanceof WithChunking &&
                $this->fileManager->shouldChunk($localPath, $import->maxFileSize())
            ) {
                $this->processLargeImport($import, $localPath);
            } else {
                $this->processSmallImport($import, $localPath);
            }

            if ($import instanceof WithProgress) {
                $import->onComplete();
            }

        } catch (\Throwable $e) {
            if ($import instanceof WithProgress) {
                $import->onFailed($e);
            }

            throw new ProcessingException(
                'Erro durante o processamento: '.$e->getMessage(),
                $e->getCode(),
                $e
            );
        } finally {
            $this->fileManager->deleteTemp($localPath);
        }

        $duration = microtime(true) - $startTime;
        Log::info('[DataProcessor] Importação concluída', [
            'tempo' => round($duration, 2).'s',
            'total_linhas' => $this->totalRows,
            'processadas' => $this->processedRows,
            'erros' => $this->errorCount,
        ]);
    }

    public function export(Exportable $export, string $outputPath, string $format = 'xlsx'): void
    {
        $startTime = microtime(true);

        Log::info('[DataProcessor] Iniciando exportação', [
            'arquivo' => $outputPath,
            'classe' => get_class($export),
            'formato' => $format,
        ]);

        $tempPath = $this->fileManager->getTempPath('export_'.Str::random().'.'.$format);

        try {
            if ($export instanceof WithProgress) {
                $export->onStart($this->totalRows);
            }

            $this->processExport($export, $tempPath, $format);
            $this->fileManager->uploadFromTemp($tempPath, $outputPath);

            if ($export instanceof WithProgress) {
                $export->onComplete();
            }

        } catch (\Throwable $e) {
            if ($export instanceof WithProgress) {
                $export->onFailed($e);
            }
            throw $e;
        } finally {
            $this->fileManager->deleteTemp($tempPath);
        }

        $duration = microtime(true) - $startTime;
        Log::info('[DataProcessor] Exportação concluída', [
            'tempo' => round($duration, 2).'s',
        ]);
    }

    protected function validateRow(array $row, array $rules, int $rowNumber): void
    {
        if (empty($rules)) {
            return;
        }

        $validator = validator($row, $rules);

        if ($validator->fails()) {
            throw new \InvalidArgumentException(
                "Erro de validação na linha {$rowNumber}: ".$validator->errors()->toJson()
            );
        }
    }

    private function processSmallImport(Importable $import, string $filePath): void
    {
        $reader = $this->createReader($filePath);
        $reader->open($filePath);

        $batch = [];
        $chunkSize = $import->chunkSize();
        $currentRow = 0;
        $headers = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $isFirstRow = true;

            foreach ($sheet->getRowIterator() as $row) {
                if ($isFirstRow) {
                    foreach ($row->getCells() as $cell) {
                        $headers[] = Str::of($cell->getValue())->lower()->snake()->toString();
                    }

                    $isFirstRow = false;
                    continue;
                }

                $currentRow++;

                try {
                    $rowData = [];
                    $associativeRow = [];

                    foreach ($row->getCells() as $cell) {
                        $rowData[] = $cell->getValue();
                    }

                    foreach ($headers as $index => $header) {
                        $associativeRow[$header] = $rowData[$index] ?? null;
                    }

                    $mappedData = $import->map($associativeRow);

                    if (method_exists($import, 'rules')) {
                        $this->validateRow($mappedData, $import->rules(), $currentRow);
                    }

                    $batch[] = $mappedData;

                } catch (\Throwable $e) {
                    $this->handleRowError($import, $e, $rowData ?? [], $currentRow);

                    continue;
                }

                if (count($batch) >= $chunkSize) {
                    $this->processBatch($import, $batch, $currentRow - count($batch) + 1);
                    $batch = [];
                }
            }
        }

        if (! empty($batch)) {
            $this->processBatch($import, $batch, $currentRow - count($batch) + 1);
        }

        $reader->close();
    }

    private function processLargeImport(Importable $import, string $filePath): void
    {
        $reader = $this->createReader($filePath);
        $chunkRows = $import instanceof WithChunking ? $import->chunkRows() : config('data-processor.chunk_rows', 10000);

        foreach ($this->chunkProcessor->splitFile($reader, $filePath, $chunkRows) as $chunkPath) {
            if ($import instanceof ShouldQueue) {
                dispatch(new ProcessImportChunk($import, [], $chunkPath))
                    ->onQueue($import->onQueue() ?: config('data-processor.queue'));
            } else {
                $this->processSmallImport($import, $chunkPath);
            }
        }
    }

    private function processBatch(Importable $import, array $batch, int $startRowNumber): void
    {
        try {
            if ($import instanceof ShouldQueue) {
                dispatch(new ProcessImportChunk($import, $batch))
                    ->onQueue($import->onQueue() ?: config('data-processor.queue'));
            } else {
                if (method_exists($import, 'processRow')) {
                    foreach ($batch as $index => $rowData) {
                        try {
                            $import->processRow($rowData, $startRowNumber + $index);
                            $this->processedRows++;
                        } catch (\Throwable $e) {
                            $this->handleRowError($import, $e, $rowData, $startRowNumber + $index);
                        }
                    }
                } else {
                    $import->process($batch);
                    $this->processedRows += count($batch);
                }
            }

            $this->reportProgress($import);

        } catch (\Throwable $e) {
            $this->errorCount += count($batch);

            if ($import instanceof WithErrorHandling) {
                $import->onError($e, end($batch), $startRowNumber + count($batch) - 1);

                if (! $import->shouldSkipOnError()) {
                    throw $e;
                }

                if ($this->shouldStopProcessing($import)) {
                    throw new ProcessingException(
                        'Limite máximo de erros atingido: '.$import->maxErrors(),
                        0,
                        $e
                    );
                }
            } else {
                throw $e;
            }
        }
    }

    private function handleRowError(Importable $import, \Throwable $error, array $rowData, int $rowNumber): void
    {
        $this->errorCount++;

        if ($import instanceof WithErrorHandling) {
            $import->onError($error, $rowData, $rowNumber);

            if (! $import->shouldSkipOnError()) {
                throw $error;
            }

            if ($this->shouldStopProcessing($import)) {
                throw new ProcessingException(
                    'Limite máximo de erros atingido: '.$import->maxErrors(),
                    0,
                    $error
                );
            }
        } else {
            throw $error;
        }
    }

    private function shouldStopProcessing(WithErrorHandling $import): bool
    {
        $maxErrors = $import->maxErrors();

        return $maxErrors !== null && $this->errorCount >= $maxErrors;
    }

    private function reportProgress(Importable $import): void
    {
        if ($import instanceof WithProgress) {
            $import->onProgress($this->processedRows, $this->totalRows);
        }
    }

    private function processExport(Exportable $export, string $outputPath, string $format): void
    {
        $writer = $this->createWriter($format);
        $writer->openToFile($outputPath);

        try {
            $headings = $export->headings();
            if (! empty($headings)) {
                $writer->addRow($this->createRow($headings));
            }

            $chunkSize = $export->chunkSize();
            $batch = [];
            $processedRows = 0;

            $estimatedTotal = method_exists($export, 'count') ? $export->count() : null;

            foreach ($export->query() as $row) {
                try {
                    $mappedRow = $export->map($row);
                    $batch[] = $mappedRow;
                    $processedRows++;

                    if (count($batch) >= $chunkSize) {
                        $this->writeBatch($writer, $batch);
                        $batch = [];

                        if ($export instanceof WithProgress && $estimatedTotal) {
                            $export->onProgress($processedRows, $estimatedTotal);
                        }
                    }
                } catch (\Throwable $e) {
                    if ($export instanceof WithErrorHandling) {
                        $export->onError($e, $row, $processedRows);

                        if (! $export->shouldSkipOnError()) {
                            throw $e;
                        }
                    } else {
                        throw $e;
                    }
                }
            }

            if (! empty($batch)) {
                $this->writeBatch($writer, $batch);

                if ($export instanceof WithProgress && $estimatedTotal) {
                    $export->onProgress($processedRows, $estimatedTotal);
                }
            }

        } finally {
            $writer->close();
        }
    }

    private function writeBatch(WriterInterface $writer, array $batch): void
    {
        foreach ($batch as $rowData) {
            $writer->addRow($this->createRow($rowData));
        }
    }

    private function createRow(array $data): \OpenSpout\Common\Entity\Row
    {
        $cells = [];
        foreach ($data as $value) {
            $cells[] = \OpenSpout\Common\Entity\Cell::fromValue($value);
        }

        return new \OpenSpout\Common\Entity\Row($cells);
    }

    private function createReader(string $filePath): ReaderInterface
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'xlsx' => new \OpenSpout\Reader\XLSX\Reader,
            'csv' => new \OpenSpout\Reader\CSV\Reader,
            'ods' => new \OpenSpout\Reader\ODS\Reader,
            default => throw new \InvalidArgumentException("Formato não suportado: {$extension}")
        };
    }

    private function createWriter(string $format): WriterInterface
    {
        return match ($format) {
            'xlsx' => new \OpenSpout\Writer\XLSX\Writer,
            'csv' => new \OpenSpout\Writer\CSV\Writer,
            'ods' => new \OpenSpout\Writer\ODS\Writer,
            default => throw new \InvalidArgumentException("Formato não suportado: {$format}")
        };
    }

    public function getStats(): array
    {
        return [
            'total_rows' => $this->totalRows,
            'processed_rows' => $this->processedRows,
            'error_count' => $this->errorCount,
            'success_rate' => $this->processedRows > 0
                ? round((($this->processedRows - $this->errorCount) / $this->processedRows) * 100, 2)
                : 100,
        ];
    }
}
