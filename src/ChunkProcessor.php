<?php

namespace Wesleydeveloper\DataProcessor;

use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Common\Entity\Row;
use Generator;
use Wesleydeveloper\DataProcessor\FileManager;

class ChunkProcessor
{
    private FileManager $fileManager;

    public function __construct(FileManager $fileManager)
    {
        $this->fileManager = $fileManager;
    }

    public function splitFile(
        ReaderInterface $reader,
        string $originalPath,
        int $chunkRows,
        string $outputFormat = 'xlsx'
    ): Generator {
        $reader->open($originalPath);

        $chunkNumber = 1;
        $currentRows = 0;
        $headerRow = null;
        $chunkData = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                if ($rowIndex === 1) {
                    $headerRow = $row;
                    continue;
                }

                $chunkData[] = $row;
                $currentRows++;

                if ($currentRows >= $chunkRows) {
                    $chunkPath = $this->createChunkFile(
                        $chunkData,
                        $headerRow,
                        $chunkNumber,
                        $outputFormat
                    );

                    yield $chunkPath;

                    $chunkData = [];
                    $currentRows = 0;
                    $chunkNumber++;
                }
            }
        }

        if (!empty($chunkData)) {
            $chunkPath = $this->createChunkFile(
                $chunkData,
                $headerRow,
                $chunkNumber,
                $outputFormat
            );
            yield $chunkPath;
        }

        $reader->close();
    }

    private function createChunkFile(
        array $rows,
        ?Row $headerRow,
        int $chunkNumber,
        string $format
    ): string {
        // Generate chunk file path using FileManager temporary directory
        $chunkPath = $this->fileManager->getTempPath("chunk_{$chunkNumber}.{$format}");

        // Ensure temporary directory exists
        $dir = dirname($chunkPath);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        $writer = match ($format) {
            'xlsx' => new \OpenSpout\Writer\XLSX\Writer(),
            'csv'  => new \OpenSpout\Writer\CSV\Writer(),
            default => throw new \InvalidArgumentException("Formato não suportado: {$format}"),
        };

        $writer->openToFile($chunkPath);

        if ($headerRow) {
            $writer->addRow($headerRow);
        }

        foreach ($rows as $row) {
            $writer->addRow($row);
        }

        $writer->close();

        // Upload chunk to cloud if configured (multi-server environments)
        if (config('data-processor.use_cloud_temp', false)) {
            $cloudPrefix = trim(config('data-processor.temp_path', 'temp/data-processor'), '/');
            $cloudPath = $cloudPrefix.'/chunk_'.$chunkNumber.'.'.$format;
            $this->fileManager->uploadFromTemp($chunkPath, $cloudPath);
            $this->fileManager->deleteTemp($chunkPath);
            return $cloudPath;
        }

        return $chunkPath;
    }
}
