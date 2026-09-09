<?php

/**
 * Хук controller_ai_moderator_before_save_options:
 * вызывается в core/backend.php:actionOptions() перед сохранением опций.
 *
 * Ядро не передаёт неотмеченные чекбоксы, поэтому при выключении
 * галочки старое значение (например null) оставалось бы в настройках.
 * Здесь явно выставляем 0 для всех чекбоксов, которых нет в $data.
 *
 * Данные: $options (массив)
 */
class onAiModeratorControllerAiModeratorBeforeSaveOptions extends cmsAction {

    public function run($data) {

        $checkboxes = [
            'enabled', 'skip_moderators', 'flag_urls', 'notify_admin', 'logging_enabled',
            'sanctions_enabled', 'sanction_karma', 'sanction_ban', 'sanction_warning',
            'preload_model', 'bg_preload_enabled',
        ];

        $categories = ['spam', 'ad', 'insult', 'malicious', 'hate', 'extremism', 'political'];
        foreach ($categories as $cat) {
            $checkboxes[] = "cat_enabled_{$cat}";
            $checkboxes[] = "cat_karma_{$cat}";
            $checkboxes[] = "cat_ban_{$cat}";
            $checkboxes[] = "cat_warning_{$cat}";
        }

        if (!is_array($data)) {
            $data = [];
        }

        foreach ($checkboxes as $name) {
            if (!array_key_exists($name, $data)) {
                $data[$name] = 0;
            }
        }

        return $data;
    }

}