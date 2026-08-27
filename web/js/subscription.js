/**
 * Подписка гостя на новые книги автора.
 *
 * Работает на штатных средствах Bootstrap 5 (Modal, Toast) и нативном fetch —
 * дополнительных библиотек проект не подключает.
 */
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('subscription-modal');

    // Модал есть только на страницах книги и автора, и только у гостя.
    if (modalElement === null) {
        return;
    }

    const modal = new bootstrap.Modal(modalElement);
    const form = document.getElementById('subscription-form');
    const authorIdInput = document.getElementById('subscription-author-id');
    const authorName = document.getElementById('subscription-author-name');
    const phoneInput = document.getElementById('subscription-phone');
    const submitButton = document.getElementById('subscription-submit');
    const spinner = document.getElementById('subscription-spinner');
    const errorBox = document.getElementById('subscription-error');
    const toast = new bootstrap.Toast(document.getElementById('subscription-toast'), {delay: 5000});

    function setError(message) {
        errorBox.textContent = message;
        phoneInput.classList.toggle('is-invalid', message !== '');
    }

    function setLoading(isLoading) {
        spinner.classList.toggle('d-none', !isLoading);
        // Во время запроса кнопка заблокирована, чтобы подписка не ушла дважды.
        submitButton.disabled = isLoading || phoneInput.value.trim() === '';
    }

    document.querySelectorAll('[data-subscription-author-id]').forEach(function (button) {
        button.addEventListener('click', function () {
            authorIdInput.value = button.dataset.subscriptionAuthorId;
            authorName.textContent = button.dataset.subscriptionAuthorName || '';

            phoneInput.value = '';
            setError('');
            setLoading(false);
            modal.show();
        });
    });

    // Кнопка отправки остаётся неактивной, пока номер не введён.
    phoneInput.addEventListener('input', function () {
        setError('');
        submitButton.disabled = phoneInput.value.trim() === '';
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        setError('');
        setLoading(true);

        fetch(form.dataset.url, {
            method: 'POST',
            body: new FormData(form),
            headers: {
                'X-CSRF-Token': yii.getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(function (response) {
                if (response.ok) {
                    return response.json();
                }

                // Отказ фильтра (например, лимит запросов) приходит тем же JSON:
                // показываем его текст, а не голый код ответа.
                return response.json().then(
                    function (body) {
                        throw new Error(body.message || 'Сервер ответил ошибкой ' + response.status);
                    },
                    function () {
                        throw new Error('Сервер ответил ошибкой ' + response.status);
                    },
                );
            })
            .then(function (result) {
                if (result.success) {
                    modal.hide();
                    toast.show();

                    return;
                }

                // Модал остаётся открытым: пользователь должен увидеть причину отказа.
                setError(result.message);
            })
            .catch(function (error) {
                setError(error.message);
            })
            .then(function () {
                setLoading(false);
            });
    });
});
