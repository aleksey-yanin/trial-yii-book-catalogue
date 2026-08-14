<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'name' => 'Каталог книг на Yii2 (тест)',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'container' => [
        'singletons' => [
            \yii\mail\MailerInterface::class => [
                'class' => \yii\symfonymailer\Mailer::class,
                // send all mails to a file by default.
                'useFileTransport' => true,
                'viewPath' => '@app/mail',
            ],
        ],
    ],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    // Интерфейс русский, поэтому встроенные сообщения валидации тоже должны быть русскими.
    'language' => 'ru-RU',
    'components' => [
        'coverStorage' => [
            'class' => \app\components\CoverStorage::class,
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
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'XqIKuESWexTfI-V63WA1UU89pAUgB1ih',
        ],
        'cache' => [
            'class' => \yii\caching\FileCache::class,
        ],
        'user' => [
            'identityClass' => \app\models\User::class,
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => \yii\mail\MailerInterface::class,
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        // nginx направляет несуществующие пути на index.php, поэтому красивые URL
        // работают без index.php в адресе.
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
            ],
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // Запрос приходит через FastCGI от контейнера nginx, поэтому REMOTE_ADDR — адрес
    // из подсети docker, а не 127.0.0.1. Без этого Gii и Debug отдают 403.
    $devAllowedIPs = ['127.0.0.1', '::1', '172.*.*.*', '192.168.*.*', '10.*.*.*'];

    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => \yii\debug\Module::class,
        'allowedIPs' => $devAllowedIPs,
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => \yii\gii\Module::class,
        'allowedIPs' => $devAllowedIPs,
    ];
}

return $config;
