<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */

/**
 * Модальное окно подписки и контейнер для уведомления.
 *
 * Подключается один раз на страницу: автор, на которого оформляется подписка,
 * приходит из data-атрибутов нажатой кнопки, поэтому дублировать разметку
 * для каждого автора не нужно.
 */
?>
<div class="modal fade" id="subscription-modal" tabindex="-1" aria-labelledby="subscription-modal-title"
     aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="subscription-form"
              data-url="<?= Html::encode(Url::to(['/subscription/create'])) ?>">
            <div class="modal-header">
                <h5 class="modal-title" id="subscription-modal-title">Подписка на автора</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <p>
                    Укажите номер телефона, при добавлении новой книги
                    <span id="subscription-author-name" class="fw-semibold"></span>
                    вам придёт SMS
                </p>

                <input type="hidden" name="Subscription[author_id]" id="subscription-author-id" value="">

                <div class="mb-2">
                    <label class="form-label" for="subscription-phone">Номер телефона</label>
                    <input type="tel" class="form-control" id="subscription-phone" name="Subscription[phone]"
                           placeholder="+7 999 123-45-67" autocomplete="tel" required>
                    <div class="invalid-feedback" id="subscription-error"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="submit" class="btn btn-primary" id="subscription-submit" disabled>
                    <span class="spinner-border spinner-border-sm d-none" id="subscription-spinner"
                          aria-hidden="true"></span>
                    <span id="subscription-submit-text">Подписаться</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div class="toast align-items-center text-bg-success border-0" id="subscription-toast" role="status"
         aria-live="polite" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">Подписка оформлена!</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                    aria-label="Закрыть"></button>
        </div>
    </div>
</div>
