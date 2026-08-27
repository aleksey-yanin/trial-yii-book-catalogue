<?php

declare(strict_types=1);

namespace app\models;

use app\components\validators\IsbnValidator;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\web\UploadedFile;

/**
 * Книга каталога. У книги может быть несколько авторов.
 *
 * @property int $id
 * @property string $title
 * @property int $year
 * @property string|null $description
 * @property string $isbn
 * @property string|null $cover_path
 * @property int $created_at
 * @property int $updated_at
 * @property int[] $authorIds
 * @property-read Author[] $authors
 */
class Book extends ActiveRecord
{
    /**
     * Книгопечатание началось в середине XV века — более ранний год выпуска
     * заведомо является опечаткой.
     */
    public const MIN_YEAR = 1450;

    /**
     * Предел длины описания. Колонка TEXT вместила бы больше, но форма каталога
     * не то место, куда стоит принимать текст неограниченного размера.
     */
    public const MAX_DESCRIPTION_LENGTH = 10000;

    /**
     * Предел размера обложки. Держать его ниже upload_max_filesize (8M в docker/php/php.ini),
     * иначе PHP отбросит файл до валидации и пользователь получит пустую форму без объяснения.
     */
    public const MAX_COVER_SIZE = 5 * 1024 * 1024;

    /**
     * Загружаемый файл обложки. В базе хранится только имя файла: сохранением
     * занимается CoverStorage, модель о файловой системе ничего не знает.
     *
     * Свойство намеренно без строгого типа: рядом с полем файла Yii рендерит скрытый input
     * с тем же именем, поэтому форма без выбранного файла присылает пустую строку, и
     * объявление `?UploadedFile` роняло бы load() с TypeError. Реальный объект подставляет
     * контроллер через UploadedFile::getInstance(), а сохраняет его BookService.
     *
     * @var UploadedFile|string|null
     */
    public $coverFile = null;

    /**
     * @var int[]|null Кэш выбранных авторов; null — ещё не запрашивали.
     */
    private ?array $_authorIds = null;

    public static function tableName(): string
    {
        return '{{%book}}';
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
            [['title', 'year', 'isbn'], 'required'],
            [['title', 'isbn'], 'trim'],
            ['title', 'string', 'max' => 255],
            // Верхняя граница нужна не столько колонке TEXT, сколько форме: без неё
            // в описание уходит текст любого размера, и ограничения нет нигде.
            ['description', 'string', 'max' => self::MAX_DESCRIPTION_LENGTH],
            // Восклицательный знак снимает атрибут с массового присваивания, оставляя
            // валидацию: имя файла ставит только CoverStorage, и подделанное поле формы
            // не должно ни подменять запись, ни приводить к удалению чужого файла.
            ['!cover_path', 'string', 'max' => 255],

            ['year', 'integer', 'min' => self::MIN_YEAR, 'max' => self::maxYear()],

            // Валидатор приводит ISBN к каноническому виду, поэтому unique обязан идти
            // после него — иначе сравнивались бы разные написания одного номера.
            ['isbn', IsbnValidator::class],
            ['isbn', 'unique', 'message' => 'Книга с таким ISBN уже есть в каталоге.'],

            ['authorIds', 'each', 'rule' => ['integer']],
            ['authorIds', 'default', 'value' => []],

            // checkExtensionByMimeType не даёт загрузить исполняемый файл, переименованный в .jpg.
            [
                'coverFile',
                'file',
                'skipOnEmpty' => true,
                'extensions' => ['png', 'jpg', 'jpeg', 'webp'],
                'checkExtensionByMimeType' => true,
                'maxSize' => self::MAX_COVER_SIZE,
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'title' => 'Название',
            'year' => 'Год выпуска',
            'description' => 'Описание',
            'isbn' => 'ISBN',
            'cover_path' => 'Обложка',
            'coverFile' => 'Файл обложки',
            'authorIds' => 'Авторы',
            'created_at' => 'Создана',
            'updated_at' => 'Изменена',
        ];
    }

    /**
     * Верхняя граница года вычисляется, а не хардкодится: иначе через год каталог
     * начнёт отклонять новинки.
     */
    public static function maxYear(): int
    {
        return (int) date('Y');
    }

    public function getAuthors(): ActiveQuery
    {
        return $this->hasMany(Author::class, ['id' => 'author_id'])
            ->viaTable('{{%book_author}}', ['book_id' => 'id']);
    }

    /**
     * Выбранные авторы книги. Виртуальный атрибут: связи живут в отдельной таблице.
     *
     * Значение подтягивается лениво, а не в afterFind(), иначе вывод списка книг
     * порождал бы отдельный запрос на каждую строку.
     *
     * @return int[]
     */
    public function getAuthorIds(): array
    {
        if ($this->_authorIds === null) {
            $this->_authorIds = $this->isNewRecord
                ? []
                : array_map('intval', $this->getAuthors()->select('id')->column());
        }

        return $this->_authorIds;
    }

    /**
     * @param int[]|string[]|null $ids
     */
    public function setAuthorIds(?array $ids): void
    {
        $this->_authorIds = array_map('intval', $ids ?? []);
    }

    public function afterSave($insert, $changedAttributes): void
    {
        parent::afterSave($insert, $changedAttributes);

        $this->syncAuthors();
    }

    /**
     * Приводит связи книги с авторами в соответствие выбранным.
     */
    private function syncAuthors(): void
    {
        // Читаем до unlinkAll: если атрибут ещё не загружали, ленивый геттер после
        // удаления связей вернул бы пустой список и авторы книги потерялись бы.
        $ids = $this->authorIds;

        $this->unlinkAll('authors', true);

        if ($ids === []) {
            return;
        }

        foreach (Author::findAll(['id' => $ids]) as $author) {
            $this->link('authors', $author);
        }
    }
}
