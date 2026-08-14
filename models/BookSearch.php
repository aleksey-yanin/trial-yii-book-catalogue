<?php

declare(strict_types=1);

namespace app\models;

use yii\data\ActiveDataProvider;

/**
 * Поиск и фильтрация книг в каталоге.
 */
class BookSearch extends Book
{
    /**
     * Фильтр по автору: id выбранного автора.
     *
     * Без строгого типа намеренно: форма поиска отправляется методом GET, и незаполненное
     * поле приходит пустой строкой — объявление `?int` роняет load() с TypeError.
     * Приведением занимается правило `integer` в rules().
     *
     * @var int|string|null
     */
    public $authorId = null;

    /**
     * Правила базовой модели здесь не нужны: у формы фильтра нет обязательных полей,
     * а пустой год не должен считаться ошибкой.
     */
    public function rules(): array
    {
        return [
            // Те же соображения, что и в AuthorSearch: значения приходят из строки
            // запроса и могут оказаться массивом, который сломает и запрос, и вывод формы.
            [['title', 'isbn'], 'filter', 'filter' => static fn (mixed $value): string => is_scalar($value)
                ? trim((string) $value)
                : ''],
            [['year', 'authorId'], 'filter', 'filter' => static fn (mixed $value): string => is_scalar($value)
                ? trim((string) $value)
                : ''],
            [['year', 'authorId'], 'integer'],
        ];
    }

    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            'authorId' => 'Автор',
        ]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function search(array $params): ActiveDataProvider
    {
        // with('authors') — иначе колонка с авторами делает запрос на каждую строку списка.
        $query = Book::find()->with('authors');

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 20],
            'sort' => [
                'defaultOrder' => ['created_at' => SORT_DESC],
                'attributes' => ['title', 'year', 'created_at'],
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // Некорректный фильтр не должен показывать «всё подряд».
            $query->where('0=1');

            return $dataProvider;
        }

        $query->andFilterWhere(['year' => $this->year]);
        $query->andFilterWhere(['like', 'title', $this->title]);
        $query->andFilterWhere(['like', 'isbn', $this->isbn]);

        // Пустое поле формы приходит строкой, а не null, поэтому сравнение с null
        // здесь не годится: иначе фильтр «любой автор» не нашёл бы ни одной книги.
        $authorId = (int) $this->authorId;

        if ($authorId > 0) {
            // innerJoin вместо joinWith('authors'): фильтр по связи не должен
            // конфликтовать с жадной загрузкой авторов выше.
            $query->innerJoin('{{%book_author}} ba', 'ba.book_id = {{%book}}.id')
                ->andWhere(['ba.author_id' => $authorId]);
        }

        return $dataProvider;
    }
}
