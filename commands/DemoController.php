<?php

declare(strict_types=1);

namespace app\commands;

use app\components\DemoDataSeeder;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Демонстрационные данные каталога.
 */
class DemoController extends Controller
{
    /**
     * Удалить существующие книги и авторов перед наполнением.
     */
    public bool $fresh = false;

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
        $seeder = new DemoDataSeeder();

        if ($this->fresh) {
            $seeder->clear();
            $this->stdout("Каталог очищен.\n");
        } elseif (!$seeder->catalogueIsEmpty()) {
            // Не ошибка: команда вызывается из make init, где каталог может быть уже наполнен.
            $this->stdout("В каталоге уже есть данные, демо-записи не добавлены.\n");
            $this->stdout("Чтобы пересоздать их, выполните: php yii demo/seed --fresh\n");

            return ExitCode::OK;
        }

        $result = $seeder->seed();

        $this->stdout(sprintf(
            "Создано авторов: %d, книг: %d (показательный год — %d).\n",
            $result['authors'],
            $result['books'],
            DemoDataSeeder::REPORT_YEAR,
        ));

        return ExitCode::OK;
    }
}
