<?php

declare(strict_types=1);

namespace app\models;

use yii\data\ActiveDataProvider;

/**
 * Поиск авторов.
 */
class AuthorSearch extends Author
{
    /**
     * Одна строка поиска по ФИО: разделять фамилию, имя и отчество в фильтре неудобно,
     * человек вводит то, что помнит.
     *
     * Тип не объявлен по той же причине, что и в BookSearch: значение приходит из строки
     * запроса и подделывается массивом (`?name[]=…`), а типизированное свойство на этом падает.
     *
     * @var string|string[]|null
     */
    public $name = null;

    public function rules(): array
    {
        return [
            // Значение приходит из строки запроса и подделывается массивом
            // (?AuthorSearch[name][]=…). Без приведения массив дошёл бы до LIKE
            // и до вывода поля формы, где Html падает на «Array to string conversion».
            ['name', 'filter', 'filter' => static fn (mixed $value): string => is_scalar($value)
                ? trim((string) $value)
                : ''],
        ];
    }

    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            'name' => 'ФИО',
        ]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function search(array $params): ActiveDataProvider
    {
        // Число книг считается подзапросом: обращение к связи на каждую строку списка
        // дало бы запрос на автора.
        $query = Author::find()->select([
            '{{%author}}.*',
            'booksCount' => '(SELECT COUNT(*) FROM {{%book_author}} ba WHERE ba.author_id = {{%author}}.id)',
        ]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 20],
            'sort' => [
                'defaultOrder' => ['last_name' => SORT_ASC],
                'attributes' => ['last_name', 'first_name'],
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            $query->where('0=1');

            return $dataProvider;
        }

        // К этому моменту правило filter уже привело значение к строке.
        if ($this->name !== '') {
            $query->andWhere([
                'or',
                ['like', 'last_name', $this->name],
                ['like', 'first_name', $this->name],
                ['like', 'middle_name', $this->name],
            ]);
        }

        return $dataProvider;
    }
}
