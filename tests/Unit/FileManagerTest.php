<?php

namespace Wesleydeveloper\DataProcessor\Tests\Unit;

use Wesleydeveloper\DataProcessor\Tests\TestCase;
use Wesleydeveloper\DataProcessor\FileManager;
use Illuminate\Support\Facades\Storage;

class FileManagerTest extends TestCase
{
    private FileManager $fileManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fileManager = new FileManager('testing');
    }

    public function testCanDetermineIfFileShouldBeChunked(): void
    {
        // Criar arquivo pequeno
        Storage::disk('testing')->put('small.txt', 'small content');

        // Criar arquivo "grande" (simulado)
        Storage::disk('testing')->put('large.txt', str_repeat('x', 2000));

        $this->assertFalse($this->fileManager->shouldChunk('small.txt', 1000));
        $this->assertTrue($this->fileManager->shouldChunk('large.txt', 1000));
    }

    public function testCanGetFileSize(): void
    {
        $content = 'test content';
        Storage::disk('testing')->put('test.txt', $content);

        $size = $this->fileManager->getFileSize('test.txt');

        $this->assertEquals(strlen($content), $size);
    }

    public function testReturnsZeroForNonExistentFile(): void
    {
        $size = $this->fileManager->getFileSize('non-existent.txt');

        $this->assertEquals(0, $size);
    }

    public function testCanDownloadToTemp(): void
    {
        // Criar arquivo no storage fake
        Storage::disk('testing')->put('cloud-file.txt', 'cloud content');

        $tempPath = $this->fileManager->downloadToTemp('cloud-file.txt');

        $this->assertFileExists($tempPath);
        $this->assertEquals('cloud content', file_get_contents($tempPath));
    }

    public function testCanUploadFromTemp(): void
    {
        // Criar arquivo temporário
        $tempPath = storage_path('app/temp-upload.txt');
        file_put_contents($tempPath, 'temp content');

        $this->fileManager->uploadFromTemp($tempPath, 'uploaded-file.txt');

        Storage::disk('testing')->assertExists('uploaded-file.txt');
        $this->assertEquals('temp content', Storage::disk('testing')->get('uploaded-file.txt'));

        // Cleanup
        unlink($tempPath);
    }

    public function testCanDeleteTempFiles(): void
    {
        $tempPath = storage_path('app/temp-delete.txt');
        file_put_contents($tempPath, 'temp content');

        $this->assertFileExists($tempPath);

        $this->fileManager->deleteTemp($tempPath);

        $this->assertFileDoesNotExist($tempPath);
    }
}
