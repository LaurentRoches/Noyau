<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Persistence\Schema;
use PDO;

trait CreatesInMemoryDatabase
{
    private function createInMemoryDatabase(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        Schema::initialize($pdo);

        return $pdo;
    }
}
