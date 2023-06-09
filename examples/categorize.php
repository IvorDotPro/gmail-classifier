<?php

require_once 'vendor/autoload.php';

use Ivordotpro\GmailClassifier\GmailCategorizer;

$credentialsPath = '/path/to/credentials.json';
$tokenPath = '/path/to/token.txt';
$modelLocation = '/path/to/model.dat';

try {
    $gmailCategorizer = new GmailCategorizer($credentialsPath, $tokenPath, $modelLocation);
} catch (\Exception $e) {
    echo 'An error occurred: ' . $e->getMessage();
}
