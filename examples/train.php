<?php

require 'vendor/autoload.php';

use Ivordotpro\GmailClassifier\GmailClient;

// Set paths to credentials.json, token.txt, and a temp folder for storing the model
$credentialsPath = 'path_to/credentials.json';
$tokenPath = 'path_to/token.txt';
$tempFolder = 'path_to/temp_folder';

// Initialize the GmailClient
$gmailClient = new GmailClient($credentialsPath, $tokenPath, $tempFolder);

// Optionally, specify an array of label names to exclude from processing
$excludeLabels = ['ExcludeThisLabel', 'AlsoExcludeThisLabel'];

// Fetch and process messages, and train the model
$gmailClient->fetchAndProcessMessages($excludeLabels);

echo "\nModel training complete.\n";
