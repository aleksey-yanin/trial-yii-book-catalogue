<?php

declare(strict_types=1);

namespace app\commands;

use app\components\DemoDataSeeder;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Демонстрационные данные каталога.
 */
final class DemoController extends Controller
{
    /**
     * Удалить существующие книги и авторов перед наполнением.
     */
    public bool $fresh = false;

    public function __construct(
        $id,
        $module,
        private readonly DemoDataSeeder $seeder,
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['fresh']);
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), ['f' => 'fresh']);
    }

    /**
     * Наполняет каталог книгами и авторами для наглядной работы отчёта.
     */
    public function actionSeed(): int
    {
        $result = $this->fresh ? $this->seeder->refresh() : $this->seeder->seedIfEmpty();

        if ($result === null) {
            // Не ошибка: команда вызывается из make init, где каталог может быть уже наполнен.
            $this->stdout("В каталоге уже есть данные, демо-записи не добавлены.\n");
            $this->stdout("Чтобы пересоздать их, выполните: php yii demo/seed --fresh\n");

            return ExitCode::OK;
        }

        $this->stdout(sprintf(
            "Создано авторов: %d, книг: %d (показательный год — %d).\n",
            $result['authors'],
            $result['books'],
            DemoDataSeeder::REPORT_YEAR,
        ));

        return ExitCode::OK;
    }
}
