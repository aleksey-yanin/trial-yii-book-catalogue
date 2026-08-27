<?php

declare(strict_types=1);

// Режим задаётся окружением (docker-compose.yml), а не правкой этого файла: иначе
// единственной защитой от трассировок и Gii на боевом сервере остаётся «не забыть
// закомментировать две строки».
defined('YII_DEBUG') or define('YII_DEBUG', (getenv('YII_DEBUG') ?: '1') === '1');
defined('YII_ENV') or define('YII_ENV', getenv('YII_ENV') ?: 'dev');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/../config/web.php';

(new yii\web\Application($config))->run();
