<?php

declare(strict_types=1);

use JakubBoucek\ComposerConsistency\ComposerConsistency;
use Tracy\Debugger;

require __DIR__ . '/../vendor/autoload.php';

$configurator = new Nette\Configurator;

//$configurator->setDebugMode(true); // enable for your remote IP
$configurator->enableTracy(__DIR__ . '/../log', 'pan@jakubboucek.cz');

if (class_exists(ComposerConsistency::class)) {
    ComposerConsistency::rootDir(__DIR__ . '/..')->validate();
}

$configurator->setTimeZone('Europe/Prague');
$configurator->setTempDirectory(__DIR__ . '/../temp');

$configurator->createRobotLoader()
    ->addDirectory(__DIR__)
    ->register();

$configurator->addConfig(__DIR__ . '/Config/config.neon');
$configurator->addConfig(__DIR__ . '/Config/config.local.neon');

$container = $configurator->createContainer();

return $container;
