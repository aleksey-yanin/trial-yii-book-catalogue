<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Автор книги. Это сущность каталога, а не учётная запись: автор не входит
 * в систему и не связан с моделью User.
 *
 * @property int $id
 * @property string $last_name
 * @property string $first_name
 * @property string|null $middle_name
 * @property int $created_at
 * @property int $updated_at
 * @property-read string $fullName
 * @property-read Book[] $books
 */
class Author extends ActiveRecord
{
    /**
     * Число книг автора: заполняется подзапросом в AuthorSearch, чтобы список
     * не делал запрос на каждую строку. При обычном find() остаётся null.
     */
    public ?int $booksCount = null;

    public static function tableName(): string
    {
        return '{{%author}}';
    }

    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

    public function rules(): array
    {
        return [
            [['last_name', 'first_name'], 'required'],
            [['last_name', 'first_name', 'middle_name'], 'trim'],
            [['last_name', 'first_name', 'middle_name'], 'string', 'max' => 100],
            ['middle_name', 'default', 'value' => null],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'last_name' => 'Фамилия',
            'first_name' => 'Имя',
            'middle_name' => 'Отчество',
            'fullName' => 'ФИО',
            'created_at' => 'Создан',
            'updated_at' => 'Изменён',
        ];
    }

    /**
     * ФИО одной строкой — для списков, выпадающих полей и отчёта.
     */
    public function getFullName(): string
    {
        return implode(' ', array_filter([
            $this->last_name,
            $this->first_name,
            $this->middle_name,
        ]));
    }

    public function getBooks(): ActiveQuery
    {
        return $this->hasMany(Book::class, ['id' => 'book_id'])
            ->viaTable('{{%book_author}}', ['author_id' => 'id']);
    }

    /**
     * Книги автора для его карточки.
     *
     * Провайдер собирается здесь, а не во вьюхе: сортировка относится к связи и должна
     * лежать рядом с ней, а представление не обязано знать, как устроена выборка.
     */
    public function booksProvider(int $pageSize = 20): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => $this->getBooks()->orderBy(['year' => SORT_DESC]),
            'pagination' => ['pageSize' => $pageSize],
        ]);
    }

    /**
     * Авторы для выпадающих списков: идентификатор → ФИО.
     *
     * Собирается циклом, а не ArrayHelper::map(): хелпер объявлен как бестиповый array,
     * и на PHPStan обещание array<int, string> с ним не сходится.
     *
     * @return array<int, string>
     */
    public static function optionList(): array
    {
        $options = [];

        $query = self::find()
            // Списку нужны только идентификатор и то, из чего складывается ФИО.
            ->select(['id', 'last_name', 'first_name', 'middle_name'])
            ->orderBy(['last_name' => SORT_ASC, 'first_name' => SORT_ASC]);

        foreach ($query->all() as $author) {
            $options[$author->id] = $author->fullName;
        }

        return $options;
    }
}
