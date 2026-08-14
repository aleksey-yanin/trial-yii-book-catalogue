<?php

declare(strict_types=1);

namespace app\components\specifications;

use Yii;
use app\models\User;

/**
 * Право изменять каталог: добавлять, редактировать и удалять книги и авторов.
 *
 * По ТЗ ролей ровно две — гость только читает, аутентифицированный пользователь
 * получает CRUD, — поэтому условие сводится к «кандидат является пользователем».
 * Когда появятся роли или владельцы записей, менять нужно будет только этот класс.
 */
final class UserCanEdit implements SpecificationInterface
{
    public function isSatisfiedBy(mixed $candidate): bool
    {
        // Гость приходит сюда как null (Yii::$app->user->identity незалогиненного).
        return $candidate instanceof User && !$candidate->isNewRecord;
    }

    /**
     * Проверка для текущего посетителя — то, что нужно представлениям и фильтрам доступа.
     */
    public function isSatisfiedByCurrentUser(): bool
    {
        return $this->isSatisfiedBy(Yii::$app->user->identity);
    }
}
