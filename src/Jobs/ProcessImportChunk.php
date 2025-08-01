<?php

namespace Wesleydeveloper\DataProcessor\Jobs;

use Illuminate\Foundation\Queue\Queueable;
use Wesleydeveloper\DataProcessor\Contracts\Importable;
use Wesleydeveloper\DataProcessor\DataProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ProcessImportChunk implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;
    public int $memory = 512;
    public int $tries = 1;

    private Importable $import;
    private array $data;
    private ?string $chunkFilePath;

    public function __construct(Importable $import, array $data = [], ?string $chunkFilePath = null)
    {
        $this->import = $import;
        $this->data = $data;
        $this->chunkFilePath = $chunkFilePath;
    }

    public function handle(DataProcessor $processor): void
    {
        ini_set('memory_limit', $this->memory . 'M');
        ini_set('max_execution_time', $this->timeout);
        ini_set('max_input_time', $this->timeout);
        set_time_limit($this->timeout);


        Log::info('[ProcessImportChunk] Iniciando processamento do chunk', [
            'dados_count' => count($this->data),
            'chunk_file' => $this->chunkFilePath,
            'classe' => get_class($this->import)
        ]);

        try {
            if ($this->chunkFilePath) {
                $processor->import($this->import, $this->chunkFilePath);
                if (file_exists($this->chunkFilePath)) {
                    unlink($this->chunkFilePath);
                }
            } else {
                $this->import->process($this->data);
            }
        } catch (\Exception $e) {
            Log::error('[ProcessImportChunk] Erro no processamento', [
                'erro' => $e->getMessage(),
                'chunk_file' => $this->chunkFilePath
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[ProcessImportChunk] Job falhou', [
            'erro' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        if ($this->chunkFilePath && file_exists($this->chunkFilePath)) {
            unlink($this->chunkFilePath);
        }
    }
}
