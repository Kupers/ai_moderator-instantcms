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
