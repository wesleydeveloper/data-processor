<?php

namespace Wesleydeveloper\DataProcessor;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileManager
{
    private string $disk;

    private string $tempPath;

    public function __construct(string $disk)
    {
        $this->disk = $disk;
        $this->tempPath = config('data-processor.temp_path', 'temp/data-processor');
    }

    public function getTempPath(?string $path = null): string
    {
        $tempPath = storage_path('app/'.trim($this->tempPath, '/'));

        return $path ? $tempPath.'/'.rtrim($path, '/') : $tempPath;
    }

    public function downloadToTemp(string $cloudPath): string
    {
        $tempFileName = Str::uuid().'_'.basename($cloudPath);
        $localPath = $this->getTempPath($tempFileName);

        $dir = dirname($localPath);
        if (! is_dir($dir)) {
            if (! mkdir($dir, 0755, true) && ! is_dir($dir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $dir));
            }
        }

        $disk = Storage::disk($this->disk);
        if (method_exists($disk, 'path') && file_exists($disk->path($cloudPath))) {
            copy($disk->path($cloudPath), $localPath);
        } else {
            $cloudContent = $disk->get($cloudPath);
            file_put_contents($localPath, $cloudContent);
        }

        return $localPath;
    }

    public function uploadFromTemp(string $localPath, string $cloudPath): void
    {
        $content = file_get_contents($localPath);
        Storage::disk($this->disk)->put($cloudPath, $content);
    }

    public function deleteTemp(string $localPath): void
    {
        if (file_exists($localPath)) {
            unlink($localPath);
        }
    }

    public function getFileSize(string $path): int
    {
        if (Storage::disk($this->disk)->exists($path)) {
            return Storage::disk($this->disk)->size($path);
        }

        return 0;
    }

    public function shouldChunk(string $path, int $maxSize): bool
    {
        return $this->getFileSize($path) > $maxSize;
    }

    public function getTotalRows(string $filePath): int
    {

        if (file_exists($filePath)) {
            return $this->countRowsInLocalFile($filePath);
        }

        if (Storage::disk($this->disk)->exists($filePath)) {
            $tempPath = $this->downloadToTemp($filePath);

            try {
                return $this->countRowsInLocalFile($tempPath);
            } finally {
                $this->deleteTemp($tempPath);
            }
        }

        throw new \InvalidArgumentException("Arquivo não encontrado: {$filePath}");
    }

    private function countRowsInLocalFile(string $localPath): int
    {
        $extension = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => $this->countCSVRows($localPath),
            'xlsx' => $this->countExcelRows($localPath),
            'ods' => $this->countODSRows($localPath),
            default => throw new \InvalidArgumentException("Formato não suportado para contagem: {$extension}")
        };
    }

    private function countCSVRows(string $filePath): int
    {
        if (filesize($filePath) < 50 * 1024 * 1024) {
            $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            return max(0, count($lines) - 1);
        }

        return $this->countLargeCSVRows($filePath);
    }

    private function countLargeCSVRows(string $filePath): int
    {
        $lines = 0;
        $handle = fopen($filePath, 'rb');

        if ($handle) {
            fgets($handle);

            while (fgets($handle) !== false) {
                $lines++;
            }
            fclose($handle);
        }

        return $lines;
    }

    private function countExcelRows(string $filePath): int
    {
        $reader = new \OpenSpout\Reader\XLSX\Reader;

        $reader->open($filePath);

        $totalRows = 0;
        $sheetCount = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            $sheetCount++;
            if ($sheetCount > 1) {
                break;
            }

            $rowCount = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $rowCount++;
                if ($rowCount === 1) {
                    continue;
                }
                $totalRows++;
            }
        }

        $reader->close();

        return $totalRows;
    }

    private function countODSRows(string $filePath): int
    {
        $reader = new \OpenSpout\Reader\ODS\Reader;

        $reader->open($filePath);

        $totalRows = 0;
        $sheetCount = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            $sheetCount++;
            if ($sheetCount > 1) {
                break;
            }

            $rowCount = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $rowCount++;
                if ($rowCount === 1) {
                    continue;
                }
                $totalRows++;
            }
        }

        $reader->close();

        return $totalRows;
    }
}
