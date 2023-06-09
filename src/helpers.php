<?php
use Codedungeon\PHPCliColors\Color;

function output($message, $type) {
    switch ($type) {
        case 'error':
            echo Color::RED . $message . Color::RESET . PHP_EOL;
            break;
        case 'warning':
            echo Color::YELLOW . $message . Color::RESET . PHP_EOL;
            break;
        case 'info':
            echo Color::CYAN . $message . Color::RESET . PHP_EOL;
            break;
        case 'success':
            echo Color::GREEN . $message . Color::RESET . PHP_EOL;
            break;
        default:
            echo $message . PHP_EOL;
            break;
    }
}