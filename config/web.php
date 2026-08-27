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
            // Хранилище и уведомитель настраиваются ЗДЕСЬ, а одноимённые компоненты ниже —
            // лишь псевдонимы для вьюх. Раньше настройка жила в components, а контейнер о ней
            // не знал, поэтому автовайринг конструктора собирал второй экземпляр с дефолтным
            // basePath: код писал обложки мимо каталога, из которого их читают вьюхи (в тестах
            // компонент переведён на @runtime, и расхождение было настоящим).
            //
            // Обратная связка — определить синглтон замыканием `Yii::$app->get('coverStorage')` —
            // даёт бесконечную рекурсию: ServiceLocator строит компонент через createObject(),
            // тот уходит в контейнер и попадает обратно в это же замыкание.
            \app\components\CoverStorage::class => [],
            \app\components\BookNotifier::class => [],
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
        // Псевдонимы к определениям контейнера: настройка не дублируется, а вьюхи
        // и старый код продолжают обращаться через Yii::$app.
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
        'request' => [
            // Ключ подписывает cookie, в том числе `_identity` автологина, поэтому в исходниках
            // ему не место: прежнее значение осталось в истории git и заменено. Дефолт —
            // заведомая заглушка, чтобы забытый COOKIE_VALIDATION_KEY бросался в глаза.
            'cookieValidationKey' => getenv('COOKIE_VALIDATION_KEY') ?: 'dev-only-key-replace-in-env',
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
                    // По умолчанию Yii дописывает к каждой записи дамп $_SERVER, а в контейнере
                    // там лежат DB_PASSWORD и SMSPILOT_API_KEY — секреты оказывались в файле
                    // на диске. Заодно уходит $_POST, который уносил бы введённый пароль
                    // с формы входа. Трассировка исключения от этого не страдает.
                    'logVars' => [],
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
    // Запрос приходит через FastCGI от контейнера nginx, поэтому REMOTE_ADDR — всегда адрес
    // этого контейнера, а не посетителя. Без расширенного списка Gii и Debug отдают 403,
    // но и фильтровать по IP он здесь не может: адрес у всех один. Настоящий выключатель —
    // YII_ENV, и он берётся из окружения (web/index.php), а не правится в коде.
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
