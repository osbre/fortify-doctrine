<?php

namespace Laravel\Fortify\Tests\Traits;

use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Facades\Artisan;
use LaravelDoctrine\ORM\Facades\EntityManager;

trait DoctrineDatabaseTransactions
{
    public function setUpDoctrineDatabaseTransactions(): void
    {
        Artisan::call('doctrine:schema:create');

        // Share Doctrine's PDO with Laravel so assertDatabaseHas works
        $pdo = app(EntityManagerInterface::class)->getConnection()->getNativeConnection();
        app('db')->connection()->setPdo($pdo);

        EntityManager::getConnection()->beginTransaction();
    }

    public function tearDownDoctrineDatabaseTransactions(): void
    {
        EntityManager::getConnection()->rollBack();
        app(EntityManagerInterface::class)->clear();
    }
}
