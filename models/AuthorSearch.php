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
     */
    public ?string $name = null;

    public function rules(): array
    {
        return [
            ['name', 'safe'],
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

        if ($this->name !== null && $this->name !== '') {
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
