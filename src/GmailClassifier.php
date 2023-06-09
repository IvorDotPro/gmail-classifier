<?php

namespace Ivordotpro\GmailClassifier;

use Google\Service\Gmail;
use Phpml\Classification\NaiveBayes;
use Phpml\CrossValidation\StratifiedRandomSplit;
use Phpml\Dataset\ArrayDataset;
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\Metric\Accuracy;
use Phpml\Tokenization\WhitespaceTokenizer;
use Phpml\Pipeline;
use HTMLPurifier;
use HTMLPurifier_Config;
use Google\Client as GoogleClient;

/**
 * Class GmailClassifier
 * @package Ivordotpro\GmailClassifier
 */
class GmailClassifier
{
    /**
     * @var GoogleClient
     */
    private GoogleClient $client;

    /**
     * @var string
     */
    private string $tempFolderPath = '';

    /**
     * GmailClassifier constructor.
     *
     * @param string $applicationName
     * @param string $credentialsPath
     */
    public function __construct(string $applicationName, string $credentialsPath)
    {
        $this->client = new GoogleClient();
        $this->client->setApplicationName($applicationName);
        $this->client->setScopes([
        'https://www.googleapis.com/auth/gmail.readonly',
        'https://www.googleapis.com/auth/gmail.modify',
        'https://www.googleapis.com/auth/gmail.settings.basic',
        'https://www.googleapis.com/auth/gmail.settings.sharing'
        ]);
        $this->client->setAuthConfig($credentialsPath);
        $this->client->setAccessType('offline');
    }

    /**
     * Set the temporary folder path for storing the refresh token
     *
     * @param string $path
     */
    public function setTempFolderPath(string $path)
    {
        $this->tempFolderPath = rtrim($path, '/') . '/';
    }

    /**
     * Authenticate user
     */
    public function authenticate()
    {
        $tokenPath = $this->tempFolderPath . 'token.txt';

        if (file_exists($tokenPath)) {
            $this->refreshToken($tokenPath);
            if ($this->client->isAccessTokenExpired()) {
                exit('Access token expired.');
            }
        } else {
            $this->authorize();
            $this->refreshToken($tokenPath);
        }
    }

    /**
     * Classify emails
     *
     * @param array $excludeLabels
     * @return array
     */
    public function classifyEmails(array $excludeLabels = []): array
    {
        $gmail = new Gmail($this->client);

        // Fetch labels
        $labels = $gmail->users_labels->listUsersLabels('me');

        // Array to store messages
        $messagesData = [];

        foreach ($labels as $label) {

            $labelsById[$label->getId()] = $label->getName();
            $labelsByName[$label->getName()] = $label->getId();

            if (preg_match('/^\d/', $label->getName()) && !in_array($label->getName(), $skip)) {
                // The label starts with a number.
                $labelId = $label->getId();
                echo "\n\nFetching messages for label: {$label->getName()}";

                // Fetch the list of message ids with this label
                $messageIdsList = $gmail->users_messages->listUsersMessages('me', [
                'labelIds' => [$labelId],
                'maxResults' => 1000 // set maxResults to 1000
                ]);

                // Iterate through each message ID and get message details
                foreach ($messageIdsList->getMessages() as $messageIdData) {
                    echo '.';
                    $messageId = $messageIdData->getId();

                    // Fetch a single message by ID
                    $message = $gmail->users_messages->get('me', $messageId);

                    // Extract content, sender, and subject from the message payload headers
                    $headers = $message->getPayload()->getHeaders();
                    $meta = '';
                    $data = [
                    'label' => $label->getId(),
                    'labelname' => $label->getName(),
                    'content' => ''
                    ];

                    foreach ($headers as $header) {
                        $meta .= $header->getName() . ': ' . $header->getValue() . "\n";
                    }

                    $bodyData = $message->getPayload()->getBody()->getData();
                    $decodedBody = $purifier->purify(decodeBody($bodyData));

                    $words = array_filter(explode(' ', trim(str_replace(["\n", "\t", '  '], ' ', $decodedBody))));

                    $content = implode(' ', $words);

                    $data['content'] = $meta . "\n\n" . $content;

                    // Store the data in the array
                    $messagesData[] = $data;
                }
            }
        }

        // Process the messages data to train the model
        // ...

        return $classifiedEmails;
    }


    /**
     * Refresh the token
     *
     * @param string $tokenPath
     */
    private function refreshToken(string $tokenPath)
    {
        $token = unserialize(file_get_contents($tokenPath));
        $token = $this->client->fetchAccessTokenWithRefreshToken($token['refresh_token']);
        file_put_contents($tokenPath, serialize($token));
    }

    /**
     * Authorize user
     */
    private function authorize()
    {
        $this->client->setPrompt('select_account consent');
        $this->client->setRedirectUri('http://localhost:8080/oauth2callback.php');
        $authUrl = $this->client->createAuthUrl();
        printf("Open the following link in your browser:\n%s\n", $authUrl);
        print 'Enter the verification code: ';
        $authCode = trim(fgets(STDIN));
        $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);
        $this->client->setAccessToken($accessToken);
    }
}
