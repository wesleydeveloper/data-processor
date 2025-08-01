<?php

namespace Wesleydeveloper\DataProcessor\Contracts;

interface WithChunking
{
    /**
     * @return int
     */
    public function maxFileSize(): int;

    /**
     * @return int
     */
    public function chunkRows(): int;
}
