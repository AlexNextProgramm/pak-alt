<?php

include './vendor/autoload.php';
include './vendor/pet/framework/function.php';
include './config.constant.php';
use Pet\Command\Command;

if ($argc == 1) die("Not arguments console \n");

$option = $argv;
unset($option[0]);
Command::init($option);