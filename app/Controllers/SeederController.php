<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Seeders\DatabaseSeeder;
use PDO;

class SeederController extends BaseController
{
    private DatabaseSeeder $seeder;

    public function __construct(PDO $db)
    {
        parent::__construct($db);

        $this->seeder = new DatabaseSeeder($db);
    }

    public function run(): never
    {
        $results = $this->seeder->run();
        $this->success($results, 'database seeders success', 200);
    }

    public function reset(): never
    {
        $results = $this->seeder->resetDb();
        $this->success($results, 'database seeders reset success', 200);
    }
}