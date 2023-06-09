# Gmail Classifier

This package is a PHP library that uses the Gmail API to classify your emails based on labels. It employs the PHP-ML library for machine learning, to create a model that helps in the classification of emails.

## Table of Contents
- [Prerequisites](#prerequisites)
- [Setting Up Google Cloud Project](#setting-up-google-cloud-project)
- [Configuring OAuth 2.0](#configuring-oauth-20)
- [Organizing Gmail Labels](#organizing-gmail-labels)
- [Installation](#installation)
- [Training the Model](#training-the-model)
- [Ignoring Certain Labels](#ignoring-certain-labels)
- [Using the Model](#using-the-model)

## Prerequisites
- PHP 8.0 or higher
- Composer
- Google account with Gmail enabled
- Google Cloud Project
- At least 100 emails per label for training

## Setting Up Google Cloud Project
1. Go to [Google Cloud Console](https://console.cloud.google.com/).
2. Create a new project by clicking on the project dropdown, then on “New Project”.
3. Enter your Project Name and click "Create".
4. After your project is created, select it from the projects list.

## Configuring OAuth 2.0
1. Go to your project on Google Cloud Console.
2. Navigate to APIs & Services > Dashboard.
3. Click on “ENABLE APIS AND SERVICES” and enable the Gmail API.
4. Go to APIs & Services > Credentials.
5. Click on “Create credentials” and select OAuth 2.0 Client ID.
6. Set the Application type to "Web application".
7. Under "Authorized redirect URIs," add `http://localhost:8080/oauth2callback.php`
8. Click “Create”. A JSON file will be downloaded. Rename this file to `credentials.json` and move it to the root of your project directory.

For more details, you can refer to the [official documentation](https://github.com/googleapis/google-api-php-client/).

## Organizing Gmail Labels
Organize your Gmail by creating labels with a 3-digit prefix followed by a description. For example:
- `100 - Important`
- `200 - Private`
- `300 - Work`
- `400 - Newsletters`
- `999 - Just Ignore`

Labels without a numeric prefix will be ignored. Label your emails accordingly. It’s recommended to have at least 100 emails per label for training purposes.

## Installation
1. Clone the repository to your local machine.
2. Navigate to the project directory.
3. Run `composer install` to install dependencies.

## Training the Model
1. Navigate to your project's root directory.
2. Use PHP's built-in server to start your local web server: `php -S localhost:8080`.
3. In your browser, navigate to `http://localhost:8080/train.php`.
4. The first time you access it, you will be redirected to a Google login page. Log in with the Gmail account you want to access.
5. Allow the app to view your email mesages and settings.
6. Your browser will redirect back to the local server and begin fetching and processing your Gmail data for training the model.

## Ignoring Certain Labels
If you want to ignore certain labels during training, you can do this by specifying the label names in an array and passing it to the `excludeLabels` method before training the model. Example:
```php
$emailClassifier->excludeLabels(['999 - Just Ignore']);
```
## Using the Model
After training the model, you can use it to predict the classification of new emails. Example:

```php
$predictedLabel = $emailClassifier->classify($newEmailData);
```
For further information on PHP-ML library, visit the [official PHP-ML documentation](http://php-ml.readthedocs.org/).

## Examples
Examples on training and using models can be found in the examples directory.

## License
This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Acknowledgments
A big thanks to [PHP-ML](http://php-ml.readthedocs.org/) for making machine learning in PHP possible.

## Last words
If you have any questions or suggestions, feel free to open an issue or contact me at [mail@ivor.pro](mailto:ivor.pro).
Don't forget to retrain your model from time to time to keep it up to date.

 
