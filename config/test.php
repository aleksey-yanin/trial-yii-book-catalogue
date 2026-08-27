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
    // Настройка классов живёт в контейнере, компоненты ниже — псевдонимы к ней (пояснение
    // в config/web.php). Здесь это особенно важно: хранилище переведено на @runtime, и пока
    // определения не было, код получал экземпляр, пишущий в web/uploads, а вьюхи читали
    // из runtime.
    'container' => [
        'singletons' => [
            \app\components\CoverStorage::class => [
                // Тесты не должны мусорить в web/uploads — файлы уходят в runtime.
                'basePath' => '@runtime/test-uploads/covers',
            ],
            \app\components\BookNotifier::class => [],
        ],
    ],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'language' => 'ru-RU',
    'components' => [
        'db' => $db,
        'coverStorage' => static fn (): \app\components\CoverStorage
            => Yii::$container->get(\app\components\CoverStorage::class),
        'bookNotifier' => static fn (): \app\components\BookNotifier
            => Yii::$container->get(\app\components\BookNotifier::class),
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
