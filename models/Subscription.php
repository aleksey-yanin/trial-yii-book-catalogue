<?php

declare(strict_types=1);

namespace app\models;

use app\components\validators\PhoneValidator;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Подписка гостя на новые книги автора.
 *
 * Подписчик не является пользователем приложения: по ТЗ подписывается неаутентифицированный
 * посетитель, и единственное, что о нём известно, — номер телефона.
 *
 * @property int $id
 * @property int $author_id
 * @property string $phone
 * @property int $created_at
 * @property-read Author $author
 */
class Subscription extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%subscription}}';
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                // Подписку не редактируют, поэтому updated_at в таблице нет.
                'updatedAtAttribute' => false,
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['author_id', 'phone'], 'required'],
            ['author_id', 'integer'],
            [
                'author_id',
                'exist',
                'targetClass' => Author::class,
                'targetAttribute' => 'id',
                'message' => 'Автор не найден.',
            ],

            // Валидатор нормализует номер, поэтому unique обязан идти после него —
            // иначе сравнивались бы разные написания одного номера.
            ['phone', PhoneValidator::class],
            [
                'phone',
                'unique',
                'targetAttribute' => ['author_id', 'phone'],
                'message' => 'Вы уже подписаны на этого автора.',
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'author_id' => 'Автор',
            'phone' => 'Номер телефона',
            'created_at' => 'Дата подписки',
        ];
    }

    public function getAuthor(): ActiveQuery
    {
        return $this->hasOne(Author::class, ['id' => 'author_id']);
    }

    /**
     * Телефоны подписчиков автора — их и обойдёт рассылка на шаге 8.
     *
     * @return string[]
     */
    public static function phonesForAuthor(int $authorId): array
    {
        return static::find()
            ->select('phone')
            ->where(['author_id' => $authorId])
            ->column();
    }
}
