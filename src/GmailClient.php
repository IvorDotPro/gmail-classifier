<?php

namespace Ivordotpro\GmailClassifier;

use Google\Service\Gmail;
use HTMLPurifier;
use HTMLPurifier_Config;

class GmailClient
{
    private $client;
    private $gmail;
    private $purifier;
    private $tempFolder;

    /**
     * GmailClient constructor.
     *
     * @param string $credentialsPath Path to the credentials.json file.
     * @param string $tokenPath Path to the token.txt file.
     * @param string $tempFolder Path to the temp folder for storing refresh token.
     */
    public function __construct(string $credentialsPath, string $tokenPath, string $tempFolder)
    {
        ini_set('memory_limit', -1);
        $this->tempFolder = $tempFolder;

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', ''); // This will strip all tags
        $this->purifier = new HTMLPurifier($config);

        // Initialize Google API client
        $this->client = new \Google\Client();
        $this->client->setApplicationName('Gmail Unsubscribe');
        $this->client->setScopes([
        'https://www.googleapis.com/auth/gmail.readonly',
        'https://www.googleapis.com/auth/gmail.modify',
        ]);

        $this->client->setAuthConfig($credentialsPath);
        $this->client->setAccessType('offline');

        if (file_exists($tokenPath)) {
            $this->refreshToken($tokenPath);
            $this->gmail = new Gmail($this->client);
        } else {
            $this->authenticateAndInitialize($tokenPath);
            $this->gmail = new Gmail($this->client);
        }
    }

    /**
     * Refresh the token.
     *
     * @param string $tokenPath Path to the token.txt file.
     *
     * @return void
     */
    private function refreshToken(string $tokenPath): void
    {
        if(!file_exists($tokenPath)) {
            file_put_contents($tokenPath, serialize($this->client->getAccessToken()));
        }
        $token = unserialize(file_get_contents($tokenPath));
        $token = $this->client->fetchAccessTokenWithRefreshToken($token['refresh_token']);
        file_put_contents($tokenPath, serialize($token));
    }

    /**
     * Authenticate and initialize the Gmail client.
     *
     * @param string $tokenPath Path to the token.txt file.
     *
     * @return void
     */
    private function authenticateAndInitialize(string $tokenPath): void
    {
        $this->client->setPrompt('select_account consent');
        $this->client->setRedirectUri('http://localhost:8080/oauth2callback.php');
        $authUrl = $this->client->createAuthUrl();
        printf("Open the following link in your browser:\n%s\n", $authUrl);
        print 'Enter the verification code: ';
        $authCode = trim(fgets(STDIN));
        $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);
        $this->client->setAccessToken($accessToken);
        $this->refreshToken($tokenPath);
    }

    /**
     * Fetches and processes Gmail messages, then trains and saves the model.
     *
     * @param array $excludeLabels Array of label names to exclude.
     * @param int $maxMessages Number of messages per folder to use for training.
     *
     * @return void
     */
    public function fetchAndProcessMessages(array $excludeLabels = [],$maxMessages=500): void
    {
        // Fetch labels
        $labels = $this->gmail->users_labels->listUsersLabels('me')->getLabels();

        $labelsById = [];
        $labelsByName = [];
        $messagesData = [];

        foreach ($labels as $label) {
            $labelsById[$label->getId()] = $label->getName();
            $labelsByName[$label->getName()] = $label->getId();

            if (preg_match('/^\d/', $label->getName()) && !in_array($label->getName(), $excludeLabels)) {
                // Label starts with a number and not in the excluded list
                echo "\n\nFetching messages for label: {$label->getName()}";
                $messageIdsList = $this->gmail->users_messages->listUsersMessages('me', [
                'labelIds' => [$label->getId()],
                'maxResults' => $maxMessages
                ]);

                foreach ($messageIdsList->getMessages() as $messageIdData) {
                    echo '.';
                    $messageId = $messageIdData->getId();
                    $message = $this->gmail->users_messages->get('me', $messageId);
                    $headers = $message->getPayload()->getHeaders();


                    $fromHeader = '';
                    $subjectHeader = '';

                    foreach ($headers as $header) {
                        $name = $header->getName();
                        $value = $header->getValue();

                        if ($name === 'From') {
                            $fromHeader = $value;
                        } elseif ($name === 'Subject') {
                            $subjectHeader = $value;
                        }
                    }

                    $meta = "$fromHeader $subjectHeader ";

                    try {
                        $boduData = $message->getPayload()->getBody()->getData();

                        if($boduData == null) {
                            $parts = $message->getPayload()->getParts();
                            foreach ($parts as $part) {
                                switch($part->getMimeType()) {


                                    case 'text/html':
                                    case 'text/plain':
                                    case 'multipart/alternative':
                                    $boduData = $part->getBody()->getData();
                                    break;

                                    default:
                                        throw new \Exception('Unknown mime type: ' . $part->getMimeType());
                                        break;
                                }
                            }
                        }

                        if($boduData == null) {
                            continue;
                        }

                        $words = explode(' ', trim(str_replace(["\n", "\t", '  '], ' ',
                        $this->purifier->purify($this->decodeBody($boduData)))));

                        $content = '';
                        foreach ($words as $w) {
                            $w = trim($w);
                            if ($w != '') {
                                $content .= $w . ' ';
                            }
                        }

                        $data = [
                        'label' => $label->getId(),
                        'labelname' => $label->getName(),
                        'content' => $meta . "\n\n" . $content
                        ];

                        $messagesData[] = $data;

                    } catch (\Exception $e) {
                        echo '!'; // Skip the message'
                    }

                }
            }
        }

        // Process the messages data to train the model
        $modelTrainer = new ModelTrainer();
        $modelTrainer->trainAndSaveModel($messagesData,  'models/model.dat');
    }

    /**
     * Decode the body data.
     *
     * @param string $bodyData The body data to decode.
     *
     * @return string Decoded body data.
     */
    private function decodeBody(string|null $bodyData): string
    {
        $body = strtr($bodyData, '-_', '+/');
        return base64_decode($body);
    }
}
