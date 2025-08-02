# 🚀 Laravel Data Processor

[![Latest Version on Packagist](https://img.shields.io/packagist/v/wesleydeveloper/data-processor.svg?style=flat-square)](https://packagist.org/packages/wesleydeveloper/data-processor)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/wesleydeveloper/data-processor/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/wesleydeveloper/data-processor/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/wesleydeveloper/data-processor/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/wesleydeveloper/data-processor/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/wesleydeveloper/data-processor.svg?style=flat-square)](https://packagist.org/packages/wesleydeveloper/data-processor)

A **high-performance** Laravel package for importing and exporting large datasets with **cloud storage support**, **automatic chunking**, **queue processing**, and **memory-efficient generators**.

Built on top of [OpenSpout](https://github.com/openspout/openspout) for maximum performance and minimal memory usage.

## 🌟 Features

- ⚡ **High Performance**: Process millions of rows with minimal memory usage
- ☁️ **Cloud Storage**: Native support for AWS S3, Google Cloud Storage, Azure, and more
- 🔄 **Auto Chunking**: Automatically splits large files into smaller chunks
- 📋 **Queue Support**: Background processing with Laravel Queues
- 🧠 **Memory Efficient**: Uses PHP generators to handle large datasets
- 📁 **Multiple Formats**: Excel (XLSX), CSV, ODS support
- ✅ **Data Validation**: Built-in validation with Laravel's validator
- 🎯 **Laravel Integration**: Seamless integration with Laravel ecosystem
- 🧪 **Well Tested**: Comprehensive test suite with performance benchmarks

## 📋 Requirements

-- OpenSpout 4.0+

## 🐳 Docker

Use Docker para rodar a suíte de testes em um ambiente PHP 8.3 isolado:

```bash
# Build da imagem
docker build -t data-processor-tests .

# Run os testes (usa o código já copiado na imagem, sem volume mount)
docker run --rm data-processor-tests
```

## 📦 Installation

You can install the package via composer:
```bash composer require wesleydeveloper/data-processor```

Publish the config file:
```bash php artisan vendor:publish --tag="data-processor-config"```

## 🚀 Quick Start

### Import Data

Create an import class:

```php
<?php

namespace App\Imports;

use Wesleydeveloper\DataProcessor\Contracts\Importable;
use Wesleydeveloper\DataProcessor\Contracts\ShouldQueue;
use Wesleydeveloper\DataProcessor\Contracts\WithChunking;
use Illuminate\Support\Facades\DB;

class UsersImport implements Importable, ShouldQueue, WithChunking
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string'
        ];
    }

    public function map(array $row): array
    {
        return [
            'name' => $row[0],
            'email' => $row[1],
            'phone' => $row[2] ?? null,
            'created_at' => now(),
            'updated_at' => now()
        ];
    }

    public function process(array $data): void
    {
        DB::table('users')->insert($data);
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    // Queue configuration
    public function onQueue(): ?string
    {
        return 'imports';
    }

    public function timeout(): int
    {
        return 300;
    }

    public function memory(): int
    {
        return 512;
    }

    // Chunking configuration
    public function maxFileSize(): int
    {
        return 50 * 1024 * 1024; // 50MB
    }

    public function chunkRows(): int
    {
        return 10000;
    }
}
```

Process the import:

```php
use Wesleydeveloper\DataProcessor\Facades\DataProcessor;
use App\Imports\UsersImport;

DataProcessor::import(new UsersImport(), 'users-import.xlsx');

```

### Export Data

Create an export class:

```php
<?php

namespace App\Exports;

use Wesleydeveloper\DataProcessor\Contracts\Exportable;
use App\Models\User;
use Generator;

class UsersExport implements Exportable
{
    public function query(): Generator
    {
        User::chunk(1000, function ($users) {
            foreach ($users as $user) {
                yield $user;
            }
        });
    }

    public function headings(): array
    {
        return ['ID', 'Name', 'Email', 'Created At'];
    }

    public function map($user): array
    {
        return [
            $user->id,
            $user->name,
            $user->email,
            $user->created_at->format('Y-m-d H:i:s')
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
```

Process the export:

```php
use Wesleydeveloper\DataProcessor\Facades\DataProcessor;
use App\Exports\UsersExport;

DataProcessor::export(new UsersExport(), 'users-export.xlsx');
```
