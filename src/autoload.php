<?php
// This file is being autoloaded by Composers' autoloader before each request

// Load .env variables
$repository = Dotenv\Repository\RepositoryBuilder::createWithNoAdapters()
	->addAdapter(Dotenv\Repository\Adapter\EnvConstAdapter::class)
	->addWriter(Dotenv\Repository\Adapter\PutenvAdapter::class)
	->immutable()
	->make();

$dotenv = Dotenv\Dotenv::create($repository, __DIR__ . '/..');
$dotenv->load();