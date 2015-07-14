<?php

mb_internal_encoding('UTF-8');

if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    $classLoader = require __DIR__ . '/../vendor/autoload.php';
}
