<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/test_db.php';

/**
 * Application configuration shared by all test types
 */
return [
    'id' => 'basic-tests',
    'name' => 'Каталог книг на Yii2 (тест)',
    'basePath' => dirname(__DIR__),
    'bootstrap' => [
        \app\tests\Support\MailerBootstrap::class,
        'smsQueue',
    ],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'language' => 'ru-RU',
    'components' => [
        'db' => $db,
        // Тесты не должны мусорить в web/uploads — файлы уходят в runtime.
        'coverStorage' => [
            'class' => \app\components\CoverStorage::class,
            'basePath' => '@runtime/test-uploads/covers',
        ],
        'smsQueue' => [
            'class' => \yii\queue\db\Queue::class,
            'db' => 'db',
            'tableName' => '{{%queue}}',
            'channel' => 'sms',
            // Постоянного воркера нет: очередь разбирает `yii sms-queue/run` по крону.
            'mutex' => \yii\mutex\MysqlMutex::class,
        ],
        'smsSender' => [
            'class' => \app\components\sms\SmsPilotClient::class,
            'apiKey' => getenv('SMSPILOT_API_KEY') ?: 'XXXXXXXXXXXXYYYYYYYYYYYYZZZZZZZZZZZZ',
        ],
        'bookNotifier' => [
            'class' => \app\components\BookNotifier::class,
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'messageClass' => \yii\symfonymailer\Message::class,
            'useFileTransport' => true,
            'viewPath' => '@app/mail',
        ],
        'assetManager' => [
            'basePath' => __DIR__ . '/../web/assets',
        ],
        'urlManager' => [
            'showScriptName' => true,
        ],
        'user' => [
            'identityClass' => \app\models\User::class,
        ],
        'request' => [
            'cookieValidationKey' => 'test',
            'enableCsrfValidation' => false,
            // but if you absolutely need it set cookie domain to localhost
            /*
            'csrfCookie' => [
                'domain' => 'localhost',
            ],
            */
        ],
    ],
    'params' => $params,
];
