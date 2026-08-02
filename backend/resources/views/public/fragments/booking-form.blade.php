<section class="site-booking" aria-labelledby="site-booking-h2">
    <div class="container site-booking__container">
        <h2 id="site-booking-h2" class="visually-hidden">{{ $form['heading'] ?? 'طلب حجز' }}</h2>
        <div class="site-booking__panel">
            <form id="site-booking-form" class="site-booking__form" novalidate data-wa-base="https://wa.me/{{ $contact['whatsapp'] ?? '' }}" data-other-value="{{ $form['other_value'] ?? '__other__' }}">
                <div class="site-booking__field">
                    <label class="site-booking__label" for="booking-name">{{ $form['name_label'] ?? 'الاسم / الجهة' }}</label>
                    <input class="site-booking__input" type="text" id="booking-name" name="name" autocomplete="name" placeholder="{{ $form['name_placeholder'] ?? '' }}" required>
                    <p class="site-booking__error" id="booking-name-err" role="alert" hidden></p>
                </div>
                <div class="site-booking__field">
                    <label class="site-booking__label" for="booking-occasion">{{ $form['occasion_label'] ?? 'نوع المناسبة / الخدمة' }}</label>
                    <div class="site-booking__select-wrap">
                        <select class="site-booking__select" id="booking-occasion" name="occasion" required>
                            <option value="" disabled selected hidden>{{ $form['occasion_placeholder'] ?? 'اختر' }}</option>
                            @foreach ($options as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <p class="site-booking__error" id="booking-occasion-err" role="alert" hidden></p>
                </div>
                <div id="booking-occasion-custom-wrap" class="site-booking__field site-booking__field--conditional" hidden>
                    <label class="site-booking__label" for="booking-occasion-custom">{{ $form['other_label'] ?? 'اكتب المناسبة' }}</label>
                    <input class="site-booking__input" type="text" id="booking-occasion-custom" name="occasion_custom" autocomplete="off" placeholder="{{ $form['other_placeholder'] ?? '' }}">
                    <p class="site-booking__error" id="booking-occasion-custom-err" role="alert" hidden></p>
                </div>
                <div class="site-booking__field">
                    <label class="site-booking__label" for="booking-phone">{{ $form['phone_label'] ?? 'رقم التواصل' }}</label>
                    <input class="site-booking__input" type="tel" id="booking-phone" name="phone" inputmode="tel" placeholder="{{ $form['phone_placeholder'] ?? '' }}" dir="ltr" autocomplete="tel" required>
                    <p class="site-booking__error" id="booking-phone-err" role="alert" hidden></p>
                </div>
                <button type="submit" class="site-booking__submit">{{ $form['submit_label'] ?? 'إرسال الطلب' }}</button>
            </form>
        </div>
    </div>
</section>
