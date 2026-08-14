<?php

declare(strict_types=1);

namespace app\models;

use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * Учётная запись приложения.
 *
 * Регистрации нет: единственный пользователь заводится сидером
 * (migrations/m260814_082612_seed_admin_user.php).
 *
 * @property int $id
 * @property string $username
 * @property string $password_hash
 * @property string $auth_key
 * @property int $created_at
 * @property int $updated_at
 */
class User extends ActiveRecord implements IdentityInterface
{
    public static function tableName(): string
    {
        return '{{%user}}';
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
            [['username', 'password_hash', 'auth_key'], 'required'],
            ['username', 'trim'],
            ['username', 'string', 'max' => 64],
            ['username', 'unique'],
            ['password_hash', 'string', 'max' => 255],
            ['auth_key', 'string', 'max' => 32],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'username' => 'Логин',
            'created_at' => 'Создан',
            'updated_at' => 'Изменён',
        ];
    }

    public static function findIdentity($id): static|null
    {
        return static::findOne(['id' => $id]);
    }

    /**
     * Аутентификация по токену не поддерживается: API в приложении нет.
     * Молчаливый null здесь прятал бы ошибку в конфигурации компонента user.
     */
    public static function findIdentityByAccessToken($token, $type = null): static|null
    {
        throw new NotSupportedException('Вход по токену не поддерживается.');
    }

    public static function findByUsername(string $username): static|null
    {
        return static::findOne(['username' => $username]);
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function getAuthKey(): string|null
    {
        return $this->auth_key;
    }

    public function validateAuthKey($authKey): bool
    {
        return $this->auth_key === $authKey;
    }

    /**
     * Проверка пароля живёт в модели пользователя, а не в форме входа:
     * форма не должна знать, как именно хранится пароль.
     */
    public function validatePassword(string $password): bool
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    public function setPassword(string $password): void
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    public function generateAuthKey(): void
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }
}
