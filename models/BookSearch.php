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
     */
    public ?int $authorId = null;

    /**
     * Правила базовой модели здесь не нужны: у формы фильтра нет обязательных полей,
     * а пустой год не должен считаться ошибкой.
     */
    public function rules(): array
    {
        return [
            [['title', 'isbn'], 'safe'],
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

        if ($this->authorId !== null) {
            // innerJoin вместо joinWith('authors'): фильтр по связи не должен
            // конфликтовать с жадной загрузкой авторов выше.
            $query->innerJoin('{{%book_author}} ba', 'ba.book_id = {{%book}}.id')
                ->andWhere(['ba.author_id' => $this->authorId]);
        }

        return $dataProvider;
    }
}
