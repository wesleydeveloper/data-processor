<?php

namespace Wesleydeveloper\DataProcessor\Contracts;

interface WithProgress
{
    /**
     * @param int $processed
     * @param int $total
     */
    public function onProgress(int $processed, int $total): void;

    /**
     * @param int $total
     */
    public function onStart(int $total): void;

    public function onComplete(): void;

    /**
     * @param \Throwable $exception
     */
    public function onFailed(\Throwable $exception): void;
}
