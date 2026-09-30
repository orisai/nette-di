<?php declare(strict_types = 1);

use Orisai\Installer\Schema\ModuleSchema;

$schema = new ModuleSchema();

$schema->addSwitch('consoleMode', false);
$schema->addSwitch('debugMode', false);

return $schema;
