<?php

/*
 * Copyright (c) 2019 Puskás Zsolt <errotan@gmail.com>
 * Licensed under the MIT license.
 */

error_reporting(E_ALL);
ini_set('display_errors', true);

require 'RainTPL_SyntaxException.php';
require 'RainTPL2Blade.php';

if (1 === $argc) {
    echo 'Usage: php convert.php [<extension>] <directory>'."\n";
    exit(0);
} else if (2 === $argc) {
    $extension = 'html';
    $directory = $argv[1];
} else if (3 === $argc) {
    $extension = $argv[1];
    $directory = $argv[2];
}

$directory = new RecursiveDirectoryIterator($directory);
$iterator = new RecursiveIteratorIterator($directory);
$regex = new RegexIterator($iterator, '/.+\.' . $extension . '$/i', RecursiveRegexIterator::GET_MATCH);
$counter = 0;

foreach ($regex as $filename) {
    $filename = $filename[0];
    $tp = new RainTPL2Blade(file_get_contents($filename));
    $filename = dirname($filename) . '/' . pathinfo($filename, PATHINFO_FILENAME);

    try {
        file_put_contents($filename . '.blade.php', $tp->convert());
        $counter++;
    } catch (Exception $e) {
        echo 'Exception occurred at file: ' . $filename . ' > ' .  $e->getMessage() . "\n";
    }
}

echo 'Converted ' . $counter . ' file(s).'."\n";
