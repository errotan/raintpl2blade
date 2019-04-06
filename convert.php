<?php

/*
 * Copyright (c) 2019 Puskás Zsolt <errotan@gmail.com>
 * Licensed under the MIT license.
 */

error_reporting(E_ALL);
ini_set('display_errors', true);

require 'RainTPL_SyntaxException.php';
require 'RainTPL2Blade.php';

$contents = file_get_contents('tests/test.html');
$tp = new RainTPL2Blade($contents);

file_put_contents('tests/test.blade.php', $tp->convert());
