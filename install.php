<?php

function install_package() {

    $db = \cmsDatabase::getInstance();

    // Создаём / обновляем таблицы до загрузки бэкенда,
    // чтобы при обновлении не было ошибок вроде "таблица не существует"
    try {
        @$db->query("CREATE TABLE IF NOT EXISTS `{#}ai_moderator_logs` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `subject` varchar(32) NOT NULL DEFAULT 'comment',
            `subject_id` int(11) unsigned NOT NULL DEFAULT '0',
            `object_id` int(11) unsigned NOT NULL DEFAULT '0',
            `target_url` varchar(500) DEFAULT NULL,
            `user_id` int(11) unsigned NOT NULL DEFAULT '0',
            `action` varchar(16) NOT NULL DEFAULT 'log',
            `score` float NOT NULL DEFAULT '0',
            `category` varchar(16) NOT NULL DEFAULT 'normal',
            `reason` varchar(500) DEFAULT NULL,
            `text_hash` char(32) DEFAULT NULL,
            `data` text,
            `date_pub` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `subject` (`subject`),
            KEY `user_id` (`user_id`),
            KEY `action` (`action`),
            KEY `date_pub` (`date_pub`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    } catch (\Throwable $e) {}

    // Миграция: добавляем недостающие колонки к старым таблицам логов
    try {
        $columns = [];
        $res = @$db->query("SHOW COLUMNS FROM `{#}ai_moderator_logs`");
        if ($res) {
            while ($row = $db->fetchAssoc($res)) {
                $columns[] = $row['Field'];
            }
            $db->freeResult($res);
        }
        if (!in_array('object_id', $columns, true)) {
            @$db->query("ALTER TABLE `{#}ai_moderator_logs` ADD `object_id` int(11) unsigned NOT NULL DEFAULT '0' AFTER `subject_id`");
        }
        if (!in_array('target_url', $columns, true)) {
            @$db->query("ALTER TABLE `{#}ai_moderator_logs` ADD `target_url` varchar(500) DEFAULT NULL AFTER `object_id`");
        }
    } catch (\Throwable $e) {}

    try {
        @$db->query("CREATE TABLE IF NOT EXISTS `{#}ai_moderator_queue` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `author_key` varchar(64) NOT NULL DEFAULT '',
            `subject` varchar(32) NOT NULL DEFAULT '',
            `subject_id` int(11) unsigned NOT NULL DEFAULT '0',
            `ctype` varchar(32) DEFAULT NULL,
            `user_id` int(11) unsigned NOT NULL DEFAULT '0',
            `text_hash` varchar(32) NOT NULL DEFAULT '',
            `text_preview` varchar(255) DEFAULT NULL,
            `apply` tinyint(1) unsigned NOT NULL DEFAULT '0',
            `status` enum('pending','running','done','error') NOT NULL DEFAULT 'pending',
            `result_action` varchar(16) DEFAULT NULL,
            `result_score` decimal(4,2) DEFAULT NULL,
            `result_category` varchar(16) DEFAULT NULL,
            `result_reason` varchar(500) DEFAULT NULL,
            `error_message` varchar(500) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `checked_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `author_key` (`author_key`),
            KEY `status` (`status`),
            KEY `subject` (`subject`,`subject_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Throwable $e) {}

    try {
        @$db->query("CREATE TABLE IF NOT EXISTS `{#}ai_moderator_updates` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `latest_version` varchar(32) DEFAULT NULL,
            `release_url` varchar(255) DEFAULT NULL,
            `notified_version` varchar(32) DEFAULT NULL,
            `checked_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    } catch (\Throwable $e) {}

    try {
        @$db->query("CREATE TABLE IF NOT EXISTS `{#}ai_moderator_checked` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `subject` varchar(32) NOT NULL DEFAULT '',
            `subject_id` int(11) unsigned NOT NULL DEFAULT '0',
            `text_hash` varchar(32) NOT NULL DEFAULT '',
            `result_action` varchar(16) DEFAULT NULL,
            `result_score` decimal(4,2) DEFAULT NULL,
            `result_category` varchar(16) DEFAULT NULL,
            `result_reason` varchar(500) DEFAULT NULL,
            `checked_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `subject_hash` (`subject`,`subject_id`,`text_hash`),
            KEY `subject` (`subject`,`subject_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Throwable $e) {}

    return true;
}

function after_install_package() {

    $db = \cmsDatabase::getInstance();

    // Default options.
    // При обновлении мёржим новые ключи, чтобы старые настройки не ломали логику
    // (например, отсутствие moderation_mode или comment_spam_action).
    $options_yaml = "---\nenabled: 0\nmoderation_mode: post\ncontent_spam_action: moderate\ncomment_spam_action: hide\nhide_comment_text: \"Сообщение скрыто модератором.\"\nspam_threshold: 0.85\nmin_length: 5\nskip_moderators: 1\nflag_urls: 0\nblacklist: \"\"\nnotify_admin: 1\nlogging_enabled: 1\nsanctions_enabled: 0\nsanction_scope: both\nsanction_karma: 0\nsanction_karma_points: -5\nsanction_ban: 0\nsanction_ban_days: 1\nsanction_warning: 0\nsanction_warning_text: \"Ваше сообщение было отклонено модератором за нарушение правил.\\nКатегория: [category]\\nПричина: [reason]\"\nbackend: ollama\nollama_host: \"http://127.0.0.1:11434\"\nollama_model: llama3.1\nyandex_api_key: \"\"\nyandex_folder_id: \"\"\nyandex_model: yandexgpt/latest\nopenai_url: \"\"\nopenai_api_key: \"\"\nopenai_model: gpt-4o-mini\ntimeout: 15\nbg_preload_enabled: 0\nbg_preload_interval: 15\n";

    try {
        $default_options = \cmsModel::yamlToArray($options_yaml);
        $row = $db->getRow('controllers', "`name` = 'ai_moderator'", 'options');
        $existing_options = [];
        if ($row && !empty($row['options'])) {
            $existing_options = \cmsModel::yamlToArray($row['options']);
        }
        $merged = array_replace_recursive($default_options, $existing_options ?: []);
        $merged_yaml = \cmsModel::arrayToYaml($merged);
        @$db->query("UPDATE `{#}controllers` SET `options` = '" . $db->escape($merged_yaml) . "' WHERE `name` = 'ai_moderator'");
    } catch (\Throwable $e) {}

    // Регистрируем событие для хука after_save_options, если его ещё нет.
    // (Хук создаёт задачу планировщика для «Фонового прогрева по таймеру».)
    try {
        $exists = $db->getRow('events', "`listener` = 'ai_moderator' AND `event` = 'controller_ai_moderator_after_save_options'");
        if (!$exists) {
            @$db->query("INSERT INTO `{#}events` (`event`, `listener`, `ordering`, `is_enabled`)
                SELECT 'controller_ai_moderator_after_save_options', 'ai_moderator', COALESCE(MAX(`ordering`), 0) + 1, 1
                FROM `{#}events`");
        }
    } catch (\Throwable $e) {}

    // Сбрасываем кеш опций/контроллеров, чтобы изменения применились сразу
    try {
        if (class_exists('cmsCache')) {
            $cache = \cmsCache::getInstance();
            if (method_exists($cache, 'clean')) {
                $cache->clean();
            }
        }
    } catch (\Throwable $e) {}

    return true;
}
