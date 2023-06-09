<?php

namespace Ivordotpro\GmailClassifier;

use Phpml\Classification\NaiveBayes;
use Phpml\CrossValidation\StratifiedRandomSplit;
use Phpml\Dataset\ArrayDataset;
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\Pipeline;
use Phpml\Tokenization\WhitespaceTokenizer;

require_once(__DIR__ . '/helpers.php');

class ModelTrainer
{
    /**
     * Trains a NaiveBayes model with the provided messages data and saves the model to a file.
     *
     * @param array $messagesData Array of messages data where each element is an associative array with 'content' and 'labelname'.
     * @param string $modelFilePath Path to the file where the trained model will be saved.
     *
     * @return void
     */
    public function trainAndSaveModel(array $messagesData, string $modelFilePath): void
    {
        // Extract features and targets from messagesData
        $features = [];
        $targets = [];

        foreach ($messagesData as $message) {
            $features[] = $message['content'];
            $targets[] = $message['labelname'];
        }

        // Create a dataset
        $dataset = new ArrayDataset($features, $targets);

        // Split the dataset into training and testing sets
        $split = new StratifiedRandomSplit($dataset, 0.1);

        // Create a pipeline: Vectorizer -> Classifier
        $pipeline = new Pipeline([
        new TokenCountVectorizer(new WhitespaceTokenizer()),
        ], new NaiveBayes());

        output("\n\nTraining model...\n\n", 'info');

        // Train the model
        $pipeline->train(
        $split->getTrainSamples(),
        $split->getTrainLabels()
        );

        output("\n\nModel trained, compressing\n\n", 'success');
        $data = gzcompress(serialize($pipeline),9);

        // Save the trained model to a file
        output("\n\nWriting to disk\n\n", 'info');
        @unlink($modelFilePath);
        $fp = fopen($modelFilePath,'w');
        if (flock($fp, LOCK_EX)) {
            fwrite($fp, $data);
            flock($fp, LOCK_UN);
            fclose($fp);
        } else {
            throw new \Exception(Color::RED . "Couldn't lock the file {$modelFilePath}!" . Color::RESET);
        }

        output("\n\nModel trained and saved to {$modelFilePath}\n\n", 'success');
    }
}
