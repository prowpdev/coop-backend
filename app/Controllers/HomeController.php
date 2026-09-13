<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;

class HomeController extends BaseController
{
    public function __construct(
        PDO $db
    ) {
        parent::__construct($db);
    }

    public function test(): never
    {
        $this->json(['success']);
    }

    public function index(): never
    {
        $this->json([
            'status'     => 'online',
            'system'     => 'Mayap Care Agriculture Cooperative API',
            'version'    => '1.0.0',
            'php'        => PHP_VERSION,
            'database'   => 'connected',
            'timestamp'  => date('c')
        ]);
    }
}
