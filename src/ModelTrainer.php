<?php

namespace Ivordotpro\GmailClassifier;

use Phpml\Classification\NaiveBayes;
use Phpml\CrossValidation\StratifiedRandomSplit;
use Phpml\Dataset\ArrayDataset;
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\Pipeline;
use Phpml\Tokenization\WhitespaceTokenizer;

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

        echo "\n\nTraining model...\n\n";

        // Train the model
        $pipeline->train(
        $split->getTrainSamples(),
        $split->getTrainLabels()
        );

        // Save the trained model to a file
        file_put_contents($modelFilePath, serialize($pipeline));

        echo "\n\nModel trained and saved to {$modelFilePath}\n\n";
    }
}
