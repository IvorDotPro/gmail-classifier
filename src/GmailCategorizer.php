<?php

namespace Ivordotpro\GmailClassifier;

use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Label;
use Google_Service_Gmail_ModifyMessageRequest;
use Phpml\Classification\NaiveBayes;
use Phpml\ModelManager;
use Phpml\Pipeline;
use Phpml\Classification\SVC;
use Phpml\Preprocessing\Imputer;
use Phpml\Preprocessing\Normalizer;
use Phpml\Preprocessing\Imputer\Strategy\MostFrequentStrategy;


require_once(__DIR__ . '/helpers.php');

/**
 *
 * Class GmailCategorizer
 * @package Ivordotpro\GmailClassifier
 */
class GmailCategorizer
{
    private Google_Service_Gmail $gmailService;
    private Pipeline $pipeline;
    private string $userId = 'me';

    private array $labels;
    private array $uniqueEmailsFromSent;
    private array $labelsByNane;

    /**
     * GmailCategorizer constructor.
     * @param string $credentialsPath
     * @param string $tokenPath
     * @param string $modelLocation
     * @throws \Google\Exception
     */
    public function __construct(string $credentialsPath, string $tokenPath, string $modelLocation)
    {
        ini_set('memory_limit', -1);

        $client = new Google_Client();
        $client->setAuthConfig($credentialsPath);
        $client->addScope(Google_Service_Gmail::GMAIL_MODIFY);

        if (file_exists($tokenPath)) {
            $client->setAccessToken(file_get_contents($tokenPath));
        }

        if ($client->isAccessTokenExpired()) {
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            } else {
                $authUrl = $client->createAuthUrl();
                printf("Open the following link in your browser:\n%s\n", $authUrl);
                print 'Enter verification code: ';
                $authCode = trim(fgets(STDIN));
                $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
                $client->setAccessToken($accessToken);
                if (array_key_exists('access_token', $accessToken)) {
                    file_put_contents($tokenPath, json_encode($accessToken));
                }
            }
        }

        $this->gmailService = new Google_Service_Gmail($client);

        $this->labels = $this->gmailService->users_labels->listUsersLabels($this->userId)->getLabels();

        echo "\nLoading model...\n";
        $this->pipeline = unserialize(gzuncompress(file_get_contents($modelLocation)));

        echo "\nEnsuring backup labels exist...\n";
        $this->ensureBackupLabelsExist();

        echo "\nGetting all labels...\n";
        $this->getAllLabels();


        echo "\nFetching last 1000 sent messages...";
        $this->populateSentEmailAddresses();



        echo "\n\nCategorizing unread emails...\n";
        $this->categorizeUnreadEmails();
    }


    private function getAllLabels():void {
        $this->labels = [];

        try {
            $labels = $this->gmailService->users_labels->listUsersLabels($this->userId);

            if (count($labels->getLabels()) == 0) {
                print "No labels found.\n";
            } else {
                foreach ($labels->getLabels() as $label) {
                    // Store the label name as key and label id as value
                    $this->labelsByNane[$label->getName()] = $label->getId();
                    $this->labels[] = $label;
                }
            }
        } catch (Exception $e) {
            print 'An error occurred: ' . $e->getMessage();
        }

    }


    /**
     * Ensure backup labels exist in Gmail.
     * @throws \Google\Exception
     */
    private function ensureBackupLabelsExist(): void
    {
        $labelNames = ['_AI_HAS_ATTACHMENT', '_AI_HANDWRITTEN', '_AI_NEWSLETTER', '_AI_AUTOMATED', '_AI_UNCLASSIFIED'];

        foreach ($labelNames as $labelName) {
            $labelExists = false;

            foreach ($this->labels as $label) {
                if ($label->getName() === $labelName) {
                    $labelExists = true;
                    break;
                }
            }

            if (!$labelExists) {
                $newLabel = new Google_Service_Gmail_Label();
                $newLabel->setName($labelName);
                $this->gmailService->users_labels->create($this->userId, $newLabel);
            }
        }
    }

    /**
     * Populate unique email addresses from sent emails.
     * @throws \Google\Exception
     */
    private function populateSentEmailAddresses(): void
    {
        $pageToken = null;
        $emailAddresses = [];

        $params = [
        'q' => 'in:sent',
        'maxResults' => 1000,
        ];



        $messages = $this->gmailService->users_messages->listUsersMessages($this->userId, $params);



        foreach ($messages->getMessages() as $messageData) {
            echo '.';
            $messageId = $messageData->getId();
            $message = $this->gmailService->users_messages->get($this->userId, $messageId);
            $payload = $message->getPayload();

            if ($payload !== null) {
                $headers = $payload->getHeaders();

                foreach ($headers as $header) {
                    if ($header->getName() === 'To') {
                        $email = $header->getValue();
                        $emailAddresses[$email] = true;
                    }
                }
            }
        }

        $this->uniqueEmailsFromSent = array_keys($emailAddresses);
    }

    /**
     * Categorize unread emails.
     * @throws \Google\Exception
     */

    private function categorizeUnreadEmails(): void
    {
        $unreadEmails = $this->gmailService->users_messages->listUsersMessages($this->userId, ['q' => 'is:unread', 'maxResults' => 1000, 'includeSpamTrash' => 'false']);


        $totalMessages = count($unreadEmails->getMessages());
        $progress = 0;

        foreach ($unreadEmails->getMessages() as $messageInfo) {
            $progress++;
            $messageId = $messageInfo->getId();
            $message = $this->gmailService->users_messages->get($this->userId, $messageId);

            $subject = '';
            $from = '';

            $headers = $message->getPayload()->getHeaders();

            foreach ($headers as $header) {
                if ($header->getName() === 'Subject') {
                    $subject = $header->getValue();
                }
                if ($header->getName() === 'From') {
                    $from = $header->getValue();
                }
            }

            printf("Processing message %d out of %d\n", $progress, $totalMessages);
            printf("Sender: %s\n", $from);
            printf("Subject: %s\n", $subject);

            $label = false;

            if (!$label) {
                $label = $this->isHandwritten($message);
            }

            if (!$label) {
                $label = $this->checkUsingModel($message);
            }



            if (!$label) {
                $label = $this->checkForNewsletters($message);
            }

            if(!$label) {
                $label = $this->checkForAttachments($message);
            }

            if(!$label) {

                $label = "_AI_UNCLASSIFIED";
            }

            printf("Classification: %s\n", $label ?? '_AI_AUTOMATED');
            printf("--------------------\n");

            $this->moveMessageToLabel($messageId, $label ?? '_AI_AUTOMATED');
        }
    }


    /**
     * Check email using the trained model.
     * @param \Google_Service_Gmail_Message $message
     * @return string
     */
    private function checkUsingModel(\Google_Service_Gmail_Message $message): ?string
    {

        $transformers = [
        new Imputer(null, new MostFrequentStrategy()),
        new Normalizer(),
        ];

        $estimator = new NaiveBayes();


        $headers = $message->getPayload()->getHeaders();
        $subject = '';
        $meta = '';
        $body = $message->getSnippet();

        foreach ($headers as $header) {
            if ($header->getName() === 'Subject') {
                $subject = $header->getValue();
            }
            $meta .= $header->getName() . ': ' . $header->getValue() . "\n";
        }

        $content = $meta . "\n\n" . $subject . ' ' . $body;
        $prediction = $this->pipeline->predict([$content]);


        return $prediction[0];
    }

    /**
     * Check if the email has PDF attachments.
     * @param \Google_Service_Gmail_Message $message
     * @return string|null
     */
    private function checkForAttachment(\Google_Service_Gmail_Message $message): ?string
    {
        $payload = $message->getPayload();

        if ($payload->getParts()) {
            foreach ($payload->getParts() as $part) {
                $mimeType = $part->getMimeType();
                if ($mimeType === 'application/pdf') {
                    return '_AI_HAS_ATTACHMENT';
                }
            }
        }

        return null;
    }
    /**
     * Check if the email is handwritten.
     * @param \Google_Service_Gmail_Message $message
     * @return string|null
     */
    private function isHandwritten(\Google_Service_Gmail_Message $message): ?string
    {
        $from = '';
        $headers = $message->getPayload()->getHeaders();

        foreach ($headers as $header) {
            if ($header->getName() === 'From') {
                $from = $header->getValue();
            }
        }

        if (in_array($from, $this->uniqueEmailsFromSent, true)) {
            return '_AI_HANDWRITTEN';
        }

        return null;
    }

    /**
     * Check if the email is a newsletter.
     * @param \Google_Service_Gmail_Message $message
     * @return string|null
     */
    private function checkForNewsletters(\Google_Service_Gmail_Message $message): ?string
    {
        $body = $message->getSnippet();
        $newsletterKeywords = [
        'unsubscribe', 'stop receiving', 'buy now', 'order now', 'view online', 'privacy policy', // English
        'uitschrijven', 'stop ontvangen', 'koop nu', 'bestel nu', 'bekijk online', 'privacybeleid', // Dutch
            // ... other languages
        ];

        foreach ($newsletterKeywords as $keyword) {
            if (stripos($body, $keyword) !== false) {
                return '_AI_NEWSLETTER';
            }
        }

        return null;
    }

    /**
     * Move the email to the specified label.
     * @param string $messageId
     * @param string $labelName
     * @throws \Google\Exception
     */
    private function moveMessageToLabel(string $messageId, string $labelName): void
    {

        $labelId = $this->labelsByNane[$labelName];

        try {
            $mods = new Google_Service_Gmail_ModifyMessageRequest();
            $mods->setAddLabelIds([$labelId]);
            $mods->setRemoveLabelIds(['INBOX']);
            $this->gmailService->users_messages->modify('me', $messageId, $mods);
        } catch (\Exception $e) {
            echo 'An error occurred: ' . $e->getMessage();
        }
    }



    // Other necessary functions
}
