<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic-console',
    'name' => 'Каталог книг на Yii2 (тест)',
    'basePath' => dirname(__DIR__),
    // smsQueue в bootstrap: именно так yii2-queue регистрирует консольный контроллер
    // `sms-queue` — имя берётся от компонента.
    'bootstrap' => ['log', 'smsQueue'],
    'controllerNamespace' => 'app\commands',
    // Настройка классов живёт в контейнере, компоненты ниже — псевдонимы к ней.
    // Подробное пояснение и описание грабли с рекурсией — в config/web.php.
    'container' => [
        'singletons' => [
            \app\components\CoverStorage::class => [],
            \app\components\BookNotifier::class => [],
        ],
    ],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
        '@tests' => '@app/tests',
        // В консольном приложении @webroot и @web не определены, а хранилище обложек
        // адресуется теми же алиасами, что и в вебе.
        '@webroot' => '@app/web',
        '@web' => '/',
    ],
    'language' => 'ru-RU',
    'components' => [
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
        'cache' => [
            'class' => \yii\caching\FileCache::class,
        ],
        'log' => [
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                    // Причина та же, что в config/web.php: дамп $_SERVER уносил бы в лог
                    // пароль БД и ключ SMS-шлюза.
                    'logVars' => [],
                ],
            ],
        ],
        'db' => $db,
    ],
    'params' => $params,
    'controllerMap' => [
        'migrate' => [
            'class' => \yii\console\controllers\MigrateController::class,
            // Схему очереди сопровождает сам пакет: дублировать её своей миграцией —
            // значит разойтись с ней при обновлении. Так `make migrate` поднимает всё сразу.
            'migrationPath' => ['@app/migrations'],
            // Миграции пакета лежат в собственном пространстве имён, поэтому подключаются
            // через migrationNamespaces, а не как путь.
            'migrationNamespaces' => ['yii\queue\db\migrations'],
        ],
    ],
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => \yii\gii\Module::class,
    ];
    // configuration adjustments for 'dev' environment
    // requires version `2.1.21` of yii2-debug module
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => \yii\debug\Module::class,
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
