jQuery(function ($) {
    // Используем 'mousedown' вместо 'click' для перехвата события как можно раньше.
    // Это более надежно, чем 'click' или 'submit'.
    $('body').on('mousedown', '#place_order', async function (e) {
        const checkoutForm = $('form.checkout');
        const paymentMethodId = 'alternative_payments';
        const placeOrderButton = $(this);

        // 1. Проверяем, наш ли это метод оплаты и не был ли он уже обработан
        if ($(`#payment_method_${paymentMethodId}`).is(':checked') && !checkoutForm.data('alternative_payment_processed')) {
            // 2. Немедленно отменяем стандартное действие кнопки
            e.preventDefault();
            e.stopImmediatePropagation(); // Останавливаем другие обработчики клика

            // Блокируем кнопку, чтобы избежать повторных нажатий
            if (placeOrderButton.is('.disabled')) {
                return false;
            }
            placeOrderButton.addClass('disabled').val('Մշակվում է...');
            $('.woocommerce-checkout-payment').block({
                message: null,
                overlayCSS: { background: '#fff', opacity: 0.6 }
            });

            const fileInput = $('#payment_receipt')[0];

            // 3. Проверяем наличие файла
            if (!fileInput || !fileInput.files || !fileInput.files.length) {
                // Если файла нет, выводим ошибку и разблокируем кнопку
                alert('Խնդրում ենք վերբեռնել վճարման անդորրագիրը'); // Пожалуйста, загрузите чек
                placeOrderButton.removeClass('disabled').val('Оплатить'); // Вернуть исходный текст кнопки
                $('.woocommerce-checkout-payment').unblock();
                return false;
            }
            
            try {
                // 4. Выполняем асинхронную обработку файла
                const file = fileInput.files[0];
                const base64String = await getBase64(file);

                // 5. Добавляем данные в форму
                // Удаляем старое поле, если оно есть
                $('#payment_receipt_base64').remove(); 
                $('<input>').attr({
                    type: 'hidden',
                    id: 'payment_receipt_base64',
                    name: 'payment_receipt_base64',
                    value: base64String
                }).appendTo(checkoutForm);

                // 6. Устанавливаем флаг, что все готово
                checkoutForm.data('alternative_payment_processed', true);

                // 7. Добавляем небольшую задержку (как вы и предложили) и отправляем форму
                setTimeout(function() {
                    placeOrderButton.click(); // Нажимаем на кнопку еще раз
                }, 100); // 100 миллисекунд задержки

            } catch (error) {
                console.error("Ошибка обработки чека:", error);
                alert("Произошла ошибка при обработке файла. Попробуйте еще раз.");
                placeOrderButton.removeClass('disabled').val('Оплатить');
                $('.woocommerce-checkout-payment').unblock();
            }

            return false;
        }
    });

    // Вспомогательная функция для конвертации
    function getBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = () => resolve(reader.result);
            reader.onerror = (error) => reject(error);
        });
    }

    // Сбрасываем наш флаг, если пользователь меняет способ оплаты
    $('body').on('change', 'input[name="payment_method"]', function() {
        $('form.checkout').data('alternative_payment_processed', false);
    });
});