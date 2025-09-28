<?php declare(strict_types=1);

require "../../base/src/SimpleLoader.php";

use gabbro\SimpleLoader;

$loader = SimpleLoader::getInstance();
$loader->enableAutoload();
$loader->addPath(__DIR__ . "/../src", "gabbro");
$loader->addPath(__DIR__ . "/../../http/src", "gabbro");
$loader->addPath(__DIR__, "gabbro\\test");

