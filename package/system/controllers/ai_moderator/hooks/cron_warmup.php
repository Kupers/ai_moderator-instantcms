<?php

/**
 * Хук cron_warmup: вызывается системным планировщиком ICM2
 * (админка → «Расписание», задача ai_moderator → warmup,
 * выполняется по системному CRON на сервере).
 *
 * Держит модель Ollama «тёплой», если в настройках включён
 * «Фоновый прогрев по таймеру».
 */
class onAiModeratorCronWarmup extends cmsAction {

    // Крон-хук вызывается напрямую ($controller->runHook('cron_warmup')),
    // регистрация в базе событий не требуется.
    public $disallow_event_db_register = true;

    public function run() {

        $options = cmsController::loadOptions('ai_moderator');

        if (empty($options['bg_preload_enabled'])) { return; }
        if (($options['backend'] ?? 'ollama') !== 'ollama') { return; }

        $model = cmsCore::getModel('ai_moderator');

        try {
            $model->warmupOllama();
        } catch (\Throwable $e) {
            // Прогрев не критичен — не роняем задание из-за недоступной модели
        }
    }

}