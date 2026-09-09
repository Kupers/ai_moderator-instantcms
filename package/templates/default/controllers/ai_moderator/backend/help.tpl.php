<?php $this->setPageTitle(LANG_AIM_HELP_TITLE); ?>

<h1><?php echo LANG_AIM_HELP_TITLE; ?></h1>

<div class="alert alert-info">
    Настройки подключения находятся на вкладке <strong>«Основное»</strong> → раздел <strong>«Бэкенд LLM»</strong>.
</div>

<div class="row">

    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-cloud text-warning mr-2"></i>YandexGPT</h5>
            </div>
            <div class="card-body">
                <p>API-ключ берётся в <a href="https://console.yandex.cloud/" target="_blank">Yandex Cloud</a> (сервис Foundation Models).</p>
                <ol>
                    <li>Создайте платёжный аккаунт (новым пользователям даётся стартовый грант).</li>
                    <li>Создайте или выберите <strong>каталог</strong> (folder). Его ID нужно вставить в поле <em>YandexGPT: ID каталога</em>.</li>
                    <li>Создайте <strong>сервисный аккаунт</strong>: IAM → Сервисные аккаунты → Создать.</li>
                    <li>Назначьте ему роль <code>ai.languageModels.user</code> (и <code>ai.imageGeneration.user</code>, если планируете генерацию изображений).</li>
                    <li>В сервисном аккаунте перейдите на вкладку <strong>API-ключи → Создать</strong> и сохраните ключ.</li>
                    <li>Вставьте ключ в поле <em>YandexGPT: API-ключ</em>.</li>
                </ol>
                <p class="mb-0"><strong>Формат авторизации:</strong> <code>Authorization: Api-Key &lt;ключ&gt;</code>.</p>
                <p class="text-muted mt-2 mb-0">Модели: <code>yandexgpt</code>, <code>yandexgpt-lite</code>, <code>yandexgpt-pro</code> и др.</p>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-server text-success mr-2"></i>Ollama</h5>
            </div>
            <div class="card-body">
                <p>Локальный или удалённый сервер Ollama. Подходит для работы без интернета и без платы за токены.</p>
                <ol>
                    <li>Установите Ollama: <a href="https://ollama.com/download" target="_blank">ollama.com/download</a>.</li>
                    <li>Скачайте модель, например: <code>ollama pull qwen2.5:7b</code>.</li>
                    <li>Убедитесь, что сервер доступен по адресу, например <code>http://127.0.0.1:11434</code>.</li>
                    <li>Если Ollama на другой машине, запустите его с переменной окружения <code>OLLAMA_HOST=0.0.0.0:11434</code>.</li>
                    <li>В поле <em>Ollama: адрес сервера</em> укажите URL.</li>
                    <li>В поле <em>Ollama: модель</em> введите имя, например <code>qwen2.5:7b</code>.</li>
                </ol>
                <p class="mb-0"><strong>Рекомендуемые модели:</strong> <code>qwen2.5:7b</code> (русский, стабильный JSON), <code>qwen2.5:3b</code> для слабых машин.</p>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-robot text-primary mr-2"></i>OpenAI / совместимые API</h5>
            </div>
            <div class="card-body">
                <p>OpenAI, OpenRouter, локальные прокси и любые совместимые с OpenAI API сервисы.</p>
                <ol>
                    <li>Получите ключ в личном кабинете провайдера:
                        <ul>
                            <li>OpenAI: <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com/api-keys</a></li>
                            <li>OpenRouter: <a href="https://openrouter.ai/keys" target="_blank">openrouter.ai/keys</a></li>
                        </ul>
                    </li>
                    <li>В поле <em>OpenAI: endpoint</em> укажите базовый URL:
                        <ul>
                            <li>OpenAI: <code>https://api.openai.com/v1</code></li>
                            <li>OpenRouter: <code>https://openrouter.ai/api/v1</code></li>
                        </ul>
                    </li>
                    <li>Вставьте ключ в поле <em>OpenAI: API-ключ</em>.</li>
                    <li>В поле <em>OpenAI: модель</em> укажите название, например <code>gpt-4o-mini</code> или <code>openai/gpt-4o-mini</code> для OpenRouter.</li>
                </ol>
                <p class="mb-0"><strong>Формат авторизации:</strong> <code>Authorization: Bearer &lt;ключ&gt;</code>.</p>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-lightbulb text-info mr-2"></i>Советы по настройке</h5>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>После ввода параметров нажмите <strong>«Проверить соединение»</strong> — аддон запросит список моделей (или версию Ollama).</li>
                    <li>Если Ollama установлен на той же машине, адрес обычно <code>http://127.0.0.1:11434</code>.</li>
                    <li>Для Ollama важно, чтобы модель корректно возвращала JSON: лучше всего справляются <code>qwen2.5</code> и <code>llama3.1</code>.</li>
                    <li>YandexGPT и OpenAI работают без локального сервера, но требуют пополнения баланса после исчерпания гранта.</li>
                    <li>Если ответы LLM слишком медленные, увеличьте <strong>таймаут</strong> или используйте более лёгкую модель.</li>
                </ul>
            </div>
        </div>
    </div>

</div>

<div class="card mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="fas fa-temperature-high text-danger mr-2"></i>Фоновый прогрев Ollama: как «будим» модель</h5>
    </div>
    <div class="card-body">
        <p>Ollama выгружает модель из памяти после простоя. Если в момент проверки модель холодная, первый запрос может идти десятки секунд и упираться в таймаут. Чтобы этого избежать, в модуле работают <strong>два независимых уровня прогрева</strong>.</p>

        <h6>1. Прогрев перед проверкой</h6>
        <p>Опция <strong>«Ollama: прогревать модель перед проверкой»</strong> (<code>preload_model</code>, включена по умолчанию). Перед каждой проверкой текста модуль отправляет короткий запрос-<strong>«будильник»</strong> (<code>ping</code>, <code>num_predict: 1</code>) с увеличенным таймаутом (максимум из <code>таймаут × 2</code> и <code>таймаут + 180 сек</code>). Этого достаточно, чтобы Ollama загрузила модель, и холодный старт после простоя не приводил к таймауту на самой генерации. Если «будильник» не прошёл — модуль всё равно делает обычный запрос, прогрев не критичен.</p>

        <h6>2. Ollama: keep_alive</h6>
        <p>Опция <strong>«Ollama: keep_alive»</strong> (<code>ollama_keep_alive</code>, по умолчанию <code>30m</code>) передаётся в каждый запрос <code>/api/chat</code> и говорит Ollama, <strong>сколько держать модель в памяти после последнего запроса</strong>. Чем больше значение, тем дольше модель остаётся «тёплой» без обращений. Примеры: <code>30m</code>, <code>1h</code>, <code>10m</code>; <code>-1</code> — держать всегда (до перезапуска Ollama); <code>0</code> — выгружать сразу после ответа.</p>

        <h6>3. Фоновый прогрев по таймеру</h6>
        <p>Опция <strong>«Фоновый прогрев по таймеру»</strong> (<code>bg_preload_enabled</code>) + <strong>«Интервал фонового прогрева (мин)»</strong> (<code>bg_preload_interval</code>). Модуль при сохранении настроек сам создаёт (или обновляет) задачу планировщика ICM <code>ai_moderator → warmup</code> («AI-модератор: фоновый прогрев Ollama»), период которой равен интервалу. Планировщик запускается системным CRON и в срок выполняет тот же запрос-«будильник». Если интервал прогрева меньше (или равен) значению <code>keep_alive</code>, модель вообще не успевает выгрузиться и все проверки проходят мгновенно.</p>

        <p class="mb-0"><strong>Что это не то же самое, что глобальные настройки планировщика ICM</strong> (<code>/admin/settings/scheduler</code>): там включается сам крон и показывается список всех задач — в том числе и наша «AI-модератор: фоновый прогрев Ollama». Поле <code>bg_preload_interval</code> управляет только периодом этой задачи. При выключении опции задача не удаляется, а лишь деактивируется (<code>is_active = 0</code>). Прогрев активен только при бэкенде <strong>Ollama</strong>, для YandexGPT/OpenAI он бессмыслен.</p>

        <h6 class="mt-3">Рекомендации</h6>
        <ul class="mb-0">
            <li>Комментируйте только реальный трафик: период фонового прогрева — не реже периода вашего системного CRON (обычно 5 минут).</li>
            <li>Если сайт «спит» ночью — не ставьте всегда-включённый прогрев: это держит модель в памяти и тратит ресурсы. Используйте разумный <code>keep_alive</code> (например <code>30m</code>) и интервал прогрева по фактической активности.</li>
        </ul>
    </div>
</div>
