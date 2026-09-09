<?php

class modelAiModerator extends cmsModel {

    public $table = 'ai_moderator_logs';

    /**
     * Точка входа: проверка текста по всем правилам.
     *
     * @param string $text  Проверяемый текст
     * @param array  $ctx   Контекст: тип ('comment'|'content'), id, автор и т.п.
     * @return array {
     *   'is_spam'    bool,
     *   'action'     'none'|'log'|'moderate'|'hide'|'delete'|'hard_delete',
     *   'score'      float,
     *   'category'   string,
     *   'reason'     string,
     *   'source'     'prefilter'|'llm'|'error'|'disabled'
     * }
     */
    public function checkText(string $text, array $ctx = []) {

        // Подключаем универсальный транспорт (вне стандартной автозагрузки)
        require_once __DIR__ . '/lib/LLMTransport.php';

        $options = cmsController::loadOptions('ai_moderator');

        // Выключено — ничего не делаем
        if (empty($options['enabled'])) {
            return $this->result('disabled', 'none', false, 0, 'normal', '');
        }

        $text = trim($text);
        $min_len = (int)($options['min_length'] ?? 5);
        if (mb_strlen($text) < $min_len) {
            return $this->result('prefilter', 'none', false, 0, 'normal', 'too_short');
        }

        // Пропуск админов/модераторов (опция)
        $skip_role = !empty($options['skip_moderators']);
        $user_id   = (int)($ctx['user_id'] ?? 0);
        if ($skip_role && $user_id) {
            $user = cmsCore::getModel('users')->getUser($user_id);
            if ($user && (!empty($user['is_admin']) || !empty($user['is_moderator']))) {
                return $this->result('prefilter', 'none', false, 0, 'normal', 'moderator_skipped');
            }
        }

        // Быстрый pre-фильтр по правилам
        $pre = $this->prefilterRules($text, $options);
        if ($pre !== null) {
            if (!$this->isCategoryEnabled($pre['category'], $options)) {
                return $this->result('prefilter', 'none', false, $pre['score'], $pre['category'], $pre['reason'] . ' (disabled)');
            }
            $action = $pre['action']; // 'block' -> отображается в delete
            if ($action === 'block') { $action = 'delete'; }
            $cat_action = $this->getCategoryAction($ctx, $pre['category'], $options);
            if ($cat_action !== null) { $action = $cat_action; }
            return $this->result('prefilter', $action, $pre['spam'], $pre['score'], $pre['category'], $pre['reason']);
        }

        // Основной LLM-вызов
        try {
            $transport = new LLMTransport($options);
            $llm = $transport->moderate($text);
        } catch (\Throwable $e) {
            // Ошибка LLM — безопасно не блокируем, но логируем
            $this->log($ctx, 'error', $e->getMessage(), compact('text'));
            return $this->result('error', 'none', false, 0, 'normal', $e->getMessage());
        }

        $threshold = (float)($options['spam_threshold'] ?? 0.85);
        $is_bad_cat = in_array($llm['category'], ['ad', 'insult', 'malicious', 'spam', 'hate', 'extremism', 'political'], true);
        $is_spam    = $llm['spam'] || $is_bad_cat || $llm['score'] >= $threshold;

        if (!$is_spam) {
            return $this->result('llm', 'none', false, $llm['score'], $llm['category'], $llm['reason']);
        }

        // Категория отключена — пропускаем
        if (!$this->isCategoryEnabled($llm['category'], $options)) {
            return $this->result('llm', 'none', false, $llm['score'], $llm['category'], $llm['reason'] . ' (disabled)');
        }

        $mode = $this->getActionFor($ctx, $options, $llm['category']);

        // Найден спам → действие по настройкам
        $action = $mode['spam'] ?? 'log';

        if (empty($ctx['recheck'])) {
            $this->log($ctx, $action, $llm['reason'], [
                'text'         => $text,
                'score'        => $llm['score'],
                'category'     => $llm['category'],
                'raw'          => $llm['raw'] ?? null,
                'author_token' => $this->getAuthorToken($ctx),
            ]);
        }

        if ($action === 'delete' || $action === 'hard_delete' || $action === 'moderate' || $action === 'hide') {
            if (empty($ctx['recheck'])) {
                $this->notifyAdmin($ctx, $action, $llm['reason'], $text);
                $this->applySanctions($ctx, $llm, $options);
            }
        }

        return $this->result('llm', $action, true, $llm['score'], $llm['category'], $llm['reason']);
    }

    /**
     * Возвращает действие для спама исходя из типа контента и категории.
     *
     * @return array {'spam': string}
     */
    public function getActionFor(array $ctx, array $options = [], ?string $category = null): array {

        if (!$options) {
            $options = cmsController::loadOptions('ai_moderator');
        }

        if ($category) {
            $cat_action = $this->getCategoryAction($ctx, $category, $options);
            if ($cat_action !== null) {
                return ['spam' => $cat_action];
            }
        }

        $subject = $ctx['subject'] ?? 'content';

        if ($subject === 'comment') {
            return ['spam' => $options['comment_spam_action'] ?? 'hide'];
        }

        return ['spam' => $options['content_spam_action'] ?? 'moderate'];
    }

    /**
     * Проверяет, включена ли категория нарушений в настройках.
     */
    protected function isCategoryEnabled(string $category, array $options): bool {
        $key = "cat_enabled_{$category}";
        return !array_key_exists($key, $options) || !empty($options[$key]);
    }

    /**
     * Возвращает действие для конкретной категории или null, если используется глобальное.
     */
    protected function getCategoryAction(array $ctx, string $category, array $options): ?string {

        $subject = $ctx['subject'] ?? 'content';
        $key     = $subject === 'comment' ? "cat_comment_action_{$category}" : "cat_content_action_{$category}";
        $action  = $options[$key] ?? 'default';

        if ($action === 'default' || $action === '' || $action === null) {
            return null;
        }

        return $action;
    }

    /**
     * Возвращает санкции для категории (переопределяют глобальные).
     */
    protected function getCategorySanctions(string $category, array $options): array {

        $karma   = !empty($options["cat_karma_{$category}"]);
        $ban     = !empty($options["cat_ban_{$category}"]);
        $warning = !empty($options["cat_warning_{$category}"]);

        if ($karma || $ban || $warning) {
            return [
                'karma'        => $karma,
                'karma_points' => (int)($options["cat_karma_points_{$category}"] ?? -5),
                'ban'          => $ban,
                'ban_days'     => (int)($options["cat_ban_days_{$category}"] ?? 1),
                'warning'      => $warning,
            ];
        }

        return [
            'karma'        => !empty($options['sanction_karma']),
            'karma_points' => (int)($options['sanction_karma_points'] ?? -5),
            'ban'          => !empty($options['sanction_ban']),
            'ban_days'     => (int)($options['sanction_ban_days'] ?? 1),
            'warning'      => !empty($options['sanction_warning']),
        ];
    }

    /**
     * Применяет санкции к автору за выявленное нарушение.
     */
    public function applySanctions(array $ctx, array $llm, array $options = []) {

        if (empty($options['sanctions_enabled'])) { return; }

        // Ограничение области применения санкций (комментарии / контент / оба)
        $scope = (string)($options['sanction_scope'] ?? 'both');
        $scope = $scope ?: 'both';
        $subject = (string)($ctx['subject'] ?? '');
        if ($scope !== 'both') {
            $is_comment = in_array($subject, ['comment', 'comments', ''], true);
            if ($scope === 'comment' && !$is_comment) { return; }
            if ($scope === 'content' && $is_comment) { return; }
        }

        $user_id = (int)($ctx['user_id'] ?? 0);
        if (!$user_id) { return; }

        $users = cmsCore::getModel('users');

        $user = $users->getUser($user_id);
        if (!$user || !empty($user['is_admin'])) { return; }

        $category  = $llm['category'] ?? 'spam';
        $sanctions = $this->getCategorySanctions($category, $options);

        if (empty($sanctions['karma']) && empty($sanctions['ban']) && empty($sanctions['warning'])) { return; }

        try {

            // Понижение кармы
            if (!empty($sanctions['karma'])) {
                $points = (int)($sanctions['karma_points'] ?? -5);
                $users->filterEqual('id', $user_id);
                $users->increment('{users}', 'karma', $points);
            }

            // Блокировка (временная или навсегда)
            if (!empty($sanctions['ban'])) {
                $days = (int)($sanctions['ban_days'] ?? 1);
                $until = $days > 0
                    ? date('Y-m-d H:i:s', time() + $days * 86400)
                    : '2099-12-31 23:59:59';
                $users->updateUser($user_id, [
                    'is_locked'   => 1,
                    'lock_until'  => $until,
                    'lock_reason' => 'AI-модератор: ' . mb_substr($llm['reason'] ?? '', 0, 150),
                ]);
            }

            // Предупреждение (ЛС)
            if (!empty($sanctions['warning'])) {
                $this->sendWarning($user_id, $ctx, $llm);
            }

        } catch (\Throwable $e) {}
    }

    /**
     * Отправляет автору ЛС-предупреждение (от системного бота).
     */
    protected function sendWarning(int $user_id, array $ctx, array $llm) {

        $rows = $this->db->getRows('users', 'is_admin = 1', 'id');
        if (!$rows) { return; }

        $admin_ids = [];
        foreach ($rows as $row) {
            $admin_ids[] = (int)$row['id'];
        }
        sort($admin_ids);
        $sender = (int)$admin_ids[0];

        $options = cmsController::loadOptions('ai_moderator');
        $text = (string)($options['sanction_warning_text'] ?? LANG_AIM_WARNING_TEXT);

        $message = str_replace(
            ['[category]', '[reason]', '[subject]', '[id]'],
            [
                (string)($llm['category'] ?? ''),
                (string)($llm['reason'] ?? ''),
                (string)($ctx['subject'] ?? ''),
                (string)(int)($ctx['subject_id'] ?? 0),
            ],
            $text
        );

        cmsCore::getModel('messages')->addMessage($sender, [$user_id], $message);
    }

    /**
     * Текст заглушки для скрытого комментария.
     */
    public function getHideCommentText(): string {
        $options = cmsController::loadOptions('ai_moderator');
        return (string)($options['hide_comment_text'] ?? LANG_AIM_HIDE_COMMENT_TEXT_DEFAULT);
    }

    /**
     * Гарантирует наличие колонки is_hidden в таблице комментариев.
     * Автоматическая миграция при первом использовании (идемпотентна).
     *
     * @return bool
     */
    public function ensureCommentHiddenColumn(): bool {

        static $done = null;

        if ($done !== null) { return true; }

        try {
            $fields = $this->db->getTableFields('comments');

            if (!in_array('is_hidden', $fields, true)) {
                $result = $this->db->query("ALTER TABLE `{#}comments` ADD COLUMN `is_hidden` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_approved`");
                if (!$result) { return false; }
            }

            $done = true;
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Быстрые правила до LLM. Вернёт null если неопределённо.
     */
    protected function prefilterRules(string $text, array $options): ?array {

        // Чёрный список слов: жёсткий блок
        $blacklist = $this->parseList($options['blacklist'] ?? '');
        if ($blacklist) {
            $found = $this->textHasAny($text, $blacklist);
            if ($found) {
                return [
                    'action'   => 'block',
                    'spam'     => true,
                    'score'    => 1.0,
                    'category' => LLMTransport::CAT_SPAM,
                    'reason'   => 'blacklist: ' . $found,
                ];
            }
        }

        // Ссылки (проверка по опции)
        if (!empty($options['flag_urls'])) {
            if (preg_match('#https?://#i', $text) || preg_match('#www\.#i', $text)) {
                return [
                    'action'   => 'block',
                    'spam'     => true,
                    'score'    => 0.9,
                    'category' => LLMTransport::CAT_AD,
                    'reason'   => 'urls_not_allowed',
                ];
            }
        }

        return null;
    }

    protected function textHasAny(string $text, array $words): string {
        $lower = mb_strtolower($text);
        foreach ($words as $w) {
            $w = mb_strtolower(trim($w));
            if ($w !== '' && mb_strpos($lower, $w) !== false) {
                return $w;
            }
        }
        return '';
    }

    protected function parseList(string $source): array {
        $source = str_replace(["\r\n", "\r"], "\n", $source);
        return array_values(array_filter(array_map('trim', explode("\n", $source))));
    }

    /**
     * Формирует нормализованный результат.
     */
    protected function result(string $source, string $action, bool $is_spam, float $score, string $category, string $reason): array {
        return [
            'is_spam'  => $is_spam,
            'action'   => $action,
            'score'    => $score,
            'category' => $category,
            'reason'   => $reason,
            'source'   => $source,
        ];
    }

    /**
     * Токен автора для группировки нарушителей.
     *  - авторизованный пользователь -> "user:ID"
     *  - гость -> "guest:IP"
     */
    protected function getAuthorToken(array $ctx): string {
        $user_id = (int)($ctx['user_id'] ?? 0);
        if ($user_id) {
            return 'user:' . $user_id;
        }
        // В фоновом воркере нет сессии, поэтому IP берём из контекста
        $ip = trim((string)($ctx['ip'] ?? ''));
        if (!$ip) {
            $ip = cmsUser::getIp() ?: '0.0.0.0';
        }
        return 'guest:' . $ip;
    }

    /**
     * Логирование проверки в таблицу ai_moderator_logs.
     */
    public function log(array $ctx, string $action, string $reason, array $data = []) {

        $opts = cmsController::loadOptions('ai_moderator');
        if (empty($opts['logging_enabled'])) { return; }

        try {
            $this->insert('ai_moderator_logs', [
                'subject'    => (string)($ctx['subject'] ?? '') ?: 'test',
                'subject_id' => (int)($ctx['subject_id'] ?? 0),
                'object_id'  => (int)($ctx['object_id'] ?? 0),
                'target_url' => (string)($ctx['target_url'] ?? ''),
                'user_id'    => (int)($ctx['user_id'] ?? 0),
                'action'     => $action,
                'reason'     => mb_substr($reason, 0, 500),
                'text_hash'  => md5((string)($data['text'] ?? '')),
                'score'      => (float)($data['score'] ?? 0),
                'category'   => (string)($data['category'] ?? ($action === 'error' ? 'error' : 'normal')) ?: ($action === 'error' ? 'error' : 'normal'),
                'data'       => json_encode($data, JSON_UNESCAPED_UNICODE),
                'date_pub'   => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {}
    }

    /**
     * Уведомление админа (ЛС) — если включено в опциях.
     */
    public function notifyAdmin(array $ctx, string $action, string $reason, string $text) {

        $opts = cmsController::loadOptions('ai_moderator');
        if (empty($opts['notify_admin'])) { return; }

        try {

            $rows = $this->db->getRows('users', 'is_admin = 1', 'id');
            if (!$rows) { return; }

            // Отправляем только одному главному админу и от его имени (системный бот)
            $admin_ids = [];
            foreach ($rows as $row) {
                $admin_ids[] = (int)$row['id'];
            }
            sort($admin_ids);

            $sender    = (int)$admin_ids[0];
            // Уведомляем всех администраторов (включая отправителя,
            // иначе при одном админе он никогда не получит оповещение)
            $recipients = $admin_ids;

            if ($action === 'delete') {
                $subject = 'AI-модератор: удалён спам';
            } elseif ($action === 'hard_delete') {
                $subject = 'AI-модератор: удалён спам физически';
            } elseif ($action === 'hide') {
                $subject = 'AI-модератор: скрыт комментарий';
            } else {
                $subject = 'AI-модератор: контент отправлен на проверку';
            }

            $message = 'Тип: ' . ($ctx['subject'] ?? '') . ' #' . ($ctx['subject_id'] ?? 0) . "\n"
                     . 'Причина: ' . $reason . "\n\n"
                     . 'Текст: ' . mb_substr($text, 0, 500);

            cmsCore::getModel('messages')->addMessage($sender, $recipients, $message);

        } catch (\Throwable $e) {}
    }

    /**
     * Применение решения к данным комментария (для хук comment_add_permissions / comment_before_add).
     * Возвращает [комментарий, permissions].
     */
    public function applyToComment(array $comment, array $permissions, array $check): array {

        $options = cmsController::loadOptions('ai_moderator');

        $mode = $options['moderation_mode'] ?? 'post';

        // Пост-модерация: публикуем сразу, действие применяется после добавления (хук comment_after_add)
        if ($mode === 'post') {
            return [$comment, $permissions];
        }

        // Пред-модерация: решаем ДО публикации
        switch ($check['action']) {
            case 'delete':
                // Отклоняем полностью (не сохраняем)
                $permissions['error']   = true;
                $permissions['message'] = LANG_AIM_COMMENT_BLOCKED;
                break;
            case 'moderate':
            case 'hide':
                // Не публикуем; hide в пред-режиме тоже уводит на модерацию
                $comment['is_approved'] = 0;
                break;
            case 'log':
            default:
                // Только лог
                break;
        }

        return [$comment, $permissions];
    }

    /**
     * Пост-модерация комментария: применяет действие после добавления.
     */
    public function applyToExistingComment(array $comment, array $check = []): array {

        $options = cmsController::loadOptions('ai_moderator');

        if (empty($options['moderation_mode']) || $options['moderation_mode'] !== 'post') {
            return $comment;
        }

        $action = $check['action'] ?? ($options['comment_spam_action'] ?? 'hide');

        return $this->applyCommentDecision($comment, $action);
    }

    /**
     * Применяет решение модерации к существующему комментарию.
     * Используется в пост-модерации и при массовой перепроверке.
     */
    public function applyCommentDecision(array $comment, string $action): array {

        if ($action === 'none' || $action === 'log') {
            return $comment;
        }

        $action = ($action === 'block') ? 'delete' : $action;

        try {

            $comments = cmsCore::getModel('comments');

            if ($action === 'hide') {
                $this->ensureCommentHiddenColumn();
                $text = $this->getHideCommentText();
                $text_html = html($text, false);
                $comments->updateCommentContent($comment['id'], $text, $text_html);
                // Признак скрытия пишем напрямую (в обход проверки полей модели)
                $this->db->query("UPDATE {#}comments SET is_hidden = 1 WHERE id = " . (int)$comment['id']);
                $comment['content']      = $text;
                $comment['content_html'] = $text_html;
                $comment['is_hidden']    = 1;
            } elseif ($action === 'moderate') {
                $comments->update('comments', $comment['id'], ['is_approved' => 0]);
                $comment['is_approved'] = 0;
            } elseif ($action === 'delete') {
                $comments->deleteComment($comment['id'], true);
                $comment['content']      = '';
                $comment['content_html'] = '';
                $comment['is_deleted']   = 1;
            }

        } catch (\Throwable $e) {}

        return $comment;
    }

    /**
     * Применяет решение модерации к существующей записи контента.
     */
    public function applyContentDecision(string $ctype_name, int $item_id, string $action): bool {

        if ($action === 'none' || $action === 'log') {
            return false;
        }

        if (!cmsCore::isControllerExists('content')) {
            return false;
        }

        $content = cmsCore::getModel('content');
        $table   = $content->getContentTypeTableName($ctype_name);
        if (!$table) { return false; }

        try {
            if ($action === 'moderate') {
                $content->update($table, $item_id, ['is_approved' => 0]);
                return true;
            }
            if ($action === 'delete' || $action === 'block') {
                $content->update($table, $item_id, ['is_approved' => 0, 'is_pub' => 0]);
                return true;
            }
            if ($action === 'hard_delete') {
                return $this->hardDeleteContent($content, $ctype_name, $item_id);
            }
        } catch (\Throwable $e) {}

        return false;
    }

    /**
     * Физически удаляет запись контента вместе со связями.
     */
    public function hardDeleteContent($content, string $ctype_name, int $item_id): bool {

        if (!$content) {
            $content = cmsCore::getModel('content');
        }

        // Общий языковой файл (LANG_PARSER_* и др.) в CLI-воркере не подключён
        // по умолчанию, а deleteContentItem полагается на него
        \cmsCore::loadLanguage();

        $table = $content->getContentTypeTableName($ctype_name);
        if (!$table) { return false; }

        // Снимаем ограничения фильтров: запись может быть уже скрытой/неодобренной
        $content->disableApprovedFilter()->disableDeleteFilter()->disablePrivacyFilter();

        $item = $content->getContentItem($ctype_name, $item_id);
        if (!$item) { return false; }

        return (bool)$content->deleteContentItem($ctype_name, $item_id);
    }

    // =========================================================================
    // Асинхронная (фоновая) модерация
    // =========================================================================

    /**
     * Запускает фоновый воркер модерации (php.exe) без блокировки текущего запроса.
     *
     * На Windows используется proc_open с bypass_shell; воркер стартует
     * отдельным процессом и живёт после завершения HTTP-запроса.
     *
     * @return bool true — воркер запущен; false — нужно проверить синхронно
     */
    public function spawnModerationWorker(string $job, array $payload = []): bool {

        if (!function_exists('proc_open')) { return false; }

        // В web-SAPI (cgi/fpm) PHP_BINARY указывает не на CLI. Подбираем CLI-интерпретатор.
        $php_binary = PHP_BINARY;
        $base_name  = strtolower(basename($php_binary));
        if (strpos($base_name, 'cgi') !== false) {
            $alt = dirname($php_binary) . DIRECTORY_SEPARATOR . 'php.exe';
            if (@is_file($alt)) { $php_binary = $alt; }
        }
        if (strpos($base_name, 'fpm') !== false || strpos($base_name, 'cgi') !== false || !@is_file($php_binary)) {
            $candidates = [
                dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'php',
                '/usr/bin/php',
                '/usr/bin/php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
                '/usr/local/bin/php',
                '/usr/local/bin/php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            ];
            foreach ($candidates as $candidate) {
                if (@is_file($candidate)) {
                    $php_binary = $candidate;
                    break;
                }
            }
        }

        if (!@is_file($php_binary)) { return false; }

        $worker = __DIR__ . DIRECTORY_SEPARATOR . 'worker.php';
        if (!is_file($worker)) { return false; }

        // Payload передаём через переменную окружения: при bypass_shell
        // Windows ломает кавычки JSON в командной строке (argv).
        $payload_json = json_encode(['job' => $job, 'payload' => $payload], JSON_UNESCAPED_UNICODE);

        $cmd = '"' . $php_binary . '" -f "' . $worker . '"';

        $env = array_merge((is_array(getenv())) ? getenv() : [], ['AIMOD_PAYLOAD' => $payload_json]);

        // В некоторых shared-хостингах /dev/null не входит в open_basedir,
        // поэтому используем временные файлы из разрешённой директории.
        $tmp_dir = sys_get_temp_dir();
        $stdin_file  = tempnam($tmp_dir, 'aimod_in_');
        $stdout_file = tempnam($tmp_dir, 'aimod_out_');
        $stderr_file = tempnam($tmp_dir, 'aimod_err_');
        $descriptors = [
            0 => ['file', $stdin_file, 'r'],
            1 => ['file', $stdout_file, 'a'],
            2 => ['file', $stderr_file, 'a'],
        ];

        $proc = @proc_open($cmd, $descriptors, $pipes, null, $env, ['bypass_shell' => true]);

        if (!is_resource($proc)) { return false; }

        // proc_close() не вызываем: он ждёт завершения процесса.
        // PHP освободит хендл сам при окончании запроса.

        return true;
    }

    /**
     * Фоновая модерация добавленного комментария (пост-модерация).
     */
    public function processCommentModeration(array $payload) {

        $comment_id = (int)($payload['comment_id'] ?? 0);
        if (!$comment_id) { return; }

        $comment = $this->getItemById('comments', $comment_id);
        if (!$comment || empty($comment['content'])) { return; }

        $ip = '';
        if (!empty($comment['author_ip'])) {
            $ip = @inet_ntop($comment['author_ip']);
            if ($ip === false) { $ip = ''; }
        }

        $ctx = [
            'subject'    => 'comment',
            'subject_id' => (int)($comment['target_id'] ?? 0),
            'object_id'  => (int)($payload['comment_id'] ?? 0),
            'target_url' => (string)($payload['target_url'] ?? ''),
            'user_id'    => (int)($comment['user_id'] ?? 0),
            'ip'         => $ip,
        ];

        $check = $this->checkText((string)$comment['content'], $ctx);

        if ($check['action'] === 'none' || $check['action'] === 'log') { return; }

        $this->applyToExistingComment($comment, $check);
    }

    /**
     * Фоновая модерация записи контента (пост-модерация).
     */
    public function processContentModeration(array $payload) {

        $item_id    = (int)($payload['item_id'] ?? 0);
        $ctype_name = (string)($payload['ctype_name'] ?? '');
        if (!$item_id || !$ctype_name) { return; }

        if (!cmsCore::isControllerExists('content')) { return; }

        $content = cmsCore::getModel('content');

        $table = $content->getContentTypeTableName($ctype_name);
        if (!$table) { return; }

        $item = $this->getItemById($table, $item_id);
        if (!$item) { return; }

        $text = trim(implode(' ', array_filter([
            $item['title']        ?? '',
            $item['content']      ?? '',
            $item['content_html'] ?? '',
        ], 'is_string')));

        $ctx = [
            'subject'    => 'content',
            'subject_id' => $item_id,
            'object_id'  => $item_id,
            'target_url' => (string)($payload['target_url'] ?? ''),
            'user_id'    => (int)($item['user_id'] ?? 0),
        ];

        $check = $this->checkText($text, $ctx);

        if ($check['action'] === 'none' || $check['action'] === 'log') { return; }

        try {
            if ($check['action'] === 'moderate') {
                $content->update($table, $item_id, ['is_approved' => 0]);
            }
            if ($check['action'] === 'delete') {
                $content->update($table, $item_id, ['is_approved' => 0, 'is_pub' => 0]);
            }
            if ($check['action'] === 'hard_delete') {
                $this->hardDeleteContent($content, $ctype_name, $item_id);
            }
        } catch (\Throwable $e) {}
    }

    public function getLogsCount() {
        return $this->getCount('ai_moderator_logs');
    }

    public function getLogs($page = 1, $perpage = 20) {
        return $this->
            joinLeft('users', 'u', 'u.id = i.user_id')->
            select('u.nickname')->
            orderBy('id', 'desc')->
            limitPage($page, $perpage)->
            get('ai_moderator_logs', function ($item, $model) {
                $item['text'] = '';
                $data = json_decode((string)($item['data'] ?? '""'), true);
                if (is_array($data)) {
                    $item['text'] = (string)($data['text'] ?? '');
                }
                return $item;
            });
    }

    public function clearLogs() {
        return $this->db->truncateTable('ai_moderator_logs');
    }

    public function deleteLog($id) {
        return $this->db->delete('ai_moderator_logs', 'id = ' . (int)$id);
    }

    // =========================================================================
    // Нарушители
    // =========================================================================

    /**
     * Список нарушителей (агрегация по author-токену из данных логов).
     *
     * Нарушитель = авторизованный пользователь (user_id > 0) либо гость
     * с экранированным author-токеном (ip, ник, email), у которого были
     * спам-события (action в delete/moderate/hide/block).
     *
     * @return array ['items' => [...], 'total' => int]
     */
    public function getViolators(int $page = 1, int $perpage = 20): array {

        $spam_actions = "'delete','hard_delete','moderate','hide','block'";

        $count_sql = "SELECT COUNT(DISTINCT v.author_key) AS cnt FROM (
            SELECT CASE
                WHEN l.user_id > 0 THEN CONCAT('user:', l.user_id)
                ELSE CONCAT('guest:', COALESCE(JSON_UNQUOTE(JSON_EXTRACT(l.data, '$.author_token')), ''))
            END AS author_key
            FROM {#}ai_moderator_logs l
            WHERE l.action IN ({$spam_actions})
        ) v";

        $count = 0;
        $res = $this->db->query($count_sql);
        if ($res && (($r = $this->db->fetchAssoc($res)))) {
            $count = (int)($r['cnt'] ?? 0);
            $this->db->freeResult($res);
        }

        $offset = ($page - 1) * $perpage;

        $list_sql = "SELECT
                v.author_key,
                MAX(v.user_id) AS user_id,
                MAX(v.last_date) AS last_date,
                COUNT(*) AS cnt,
                GROUP_CONCAT(DISTINCT v.category ORDER BY v.category SEPARATOR ', ') AS categories
            FROM (
                SELECT
                    l.category,
                    l.user_id,
                    l.date_pub AS last_date,
                    CASE
                        WHEN l.user_id > 0 THEN CONCAT('user:', l.user_id)
                        ELSE CONCAT('guest:', COALESCE(JSON_UNQUOTE(JSON_EXTRACT(l.data, '$.author_token')), ''))
                    END AS author_key
                FROM {#}ai_moderator_logs l
                WHERE l.action IN ({$spam_actions})
            ) v
            GROUP BY v.author_key
            ORDER BY last_date DESC
            LIMIT {$offset}, {$perpage}";

        $rows = [];
        $res = $this->db->query($list_sql);
        if ($res && $this->db->numRows($res)) {
            $rows = $this->db->fetchAll($res);
            $this->db->freeResult($res);
        }

        foreach ($rows as &$row) {
            $row['cnt']          = (int)$row['cnt'];
            $row['user_id']      = (int)$row['user_id'];
            $row['avatar']       = '';
            if ($row['user_id']) {
                $user = cmsCore::getModel('users')->getUser($row['user_id']);
                if ($user) {
                    $row['nickname'] = $user['nickname'];
                    $row['avatar']   = $user['avatar'];
                }
            }
        }

        return ['items' => $rows, 'total' => $count];
    }

    /**
     * Детализация нарушителя: все его спам-записи в журнале.
     */
    public function getViolatorLogs(string $author_key, int $page = 1, int $perpage = 20): array {

        $user_id   = 0;
        $condition = "l.action IN ('delete','hard_delete','moderate','hide','block')";

        if (strpos($author_key, 'user:') === 0) {
            $user_id   = (int)substr($author_key, 5);
            $condition .= " AND l.user_id = {$user_id}";
        } elseif (strpos($author_key, 'guest:') === 0) {
            $token = substr($author_key, 6);
            $token = $this->db->escape($token);
            $condition .= " AND l.user_id = 0 AND JSON_UNQUOTE(JSON_EXTRACT(l.data, '$.author_token')) = '{$token}'";
        } else {
            return ['items' => [], 'total' => 0];
        }

        $total = 0;
        $res = $this->db->query("SELECT COUNT(*) AS cnt FROM {#}ai_moderator_logs l WHERE {$condition}");
        if ($res && (($r = $this->db->fetchAssoc($res)))) {
            $total = (int)($r['cnt'] ?? 0);
            $this->db->freeResult($res);
        }

        $offset = ($page - 1) * $perpage;
        $rows   = [];

        $sql = "SELECT l.*,
                    u.nickname,
                    JSON_UNQUOTE(JSON_EXTRACT(l.data, '$.text')) AS raw_text
                FROM {#}ai_moderator_logs l
                LEFT JOIN {#}users u ON u.id = l.user_id
                WHERE {$condition}
                ORDER BY l.id DESC
                LIMIT {$offset}, {$perpage}";

        $result = $this->db->query($sql);
        if ($result && $this->db->numRows($result)) {
            $rows = $this->db->fetchAll($result);
            $this->db->freeResult($result);
            foreach ($rows as &$row) {
                $row['text'] = (string)($row['raw_text'] ?? '');
                unset($row['raw_text']);
            }
        }

        return ['items' => $rows, 'total' => $total];
    }

    /**
     * Возвращает пользователя по нарушителю (для деталей).
     */
    public function getViolatorUser(string $author_key): ?array {
        if (strpos($author_key, 'user:') !== 0) {
            return null;
        }
        $user_id = (int)substr($author_key, 5);
        if (!$user_id) { return null; }
        return cmsCore::getModel('users')->getUser($user_id);
    }

    // =========================================================================
    // Перепроверка записей нарушителя
    // =========================================================================

    /**
     * Все комментарии пользователя (включая скрытые/на модерации/удалённые).
     */
    public function getUserCommentsForCheck(int $user_id): array {
        if (!$user_id) { return []; }
        return $this->
            filterEqual('user_id', $user_id)->
            get('comments', function ($item, $model) {
                return [
                    'subject'    => 'comment',
                    'subject_id' => (int)$item['id'],
                    'user_id'    => (int)$item['user_id'],
                    'text'       => (string)($item['content'] ?? ''),
                    'date'       => (string)($item['date_pub'] ?? ''),
                    'is_approved'=> (int)($item['is_approved'] ?? 1),
                    'is_deleted' => !empty($item['is_deleted']),
                ];
            });
    }

    /**
     * Все записи пользователя во всех типах контента.
     * @return array [ ['ctype' => .., 'ctype_title' => .., 'items' => [..]] ]
     */
    public function getUserContentForCheck(int $user_id): array {
        if (!$user_id) { return []; }
        $result = [];

        $content = cmsCore::getModel('content');
        $content->loadAllCtypes();
        $ctypes  = $content->getContentTypes();
        if (!$ctypes) { return $result; }

        foreach ($ctypes as $ctype) {
            $table = $content->getContentTypeTableName($ctype['name']);
            try {
                $rows = $this->db->getRows($table, "user_id = {$user_id}", 'id, title, content, is_pub, is_approved, is_deleted, date_pub');
            } catch (\Throwable $e) {
                continue;
            }
            if (!$rows) { continue; }
            $items = [];
            foreach ($rows as $row) {
                $items[] = [
                    'subject'     => 'content',
                    'ctype'       => $ctype['name'],
                    'subject_id'  => (int)$row['id'],
                    'user_id'     => $user_id,
                    'text'        => trim(($row['title'] ?? '') . ' ' . ($row['content'] ?? '')),
                    'date'        => (string)($row['date_pub'] ?? ''),
                    'is_pub'      => (int)($row['is_pub'] ?? 1),
                    'is_approved' => (int)($row['is_approved'] ?? 1),
                    'is_deleted'  => !empty($row['is_deleted']),
                ];
            }
            $result[] = [
                'ctype'      => $ctype['name'],
                'ctype_title'=> (string)($ctype['title'] ?? $ctype['name']),
                'items'      => $items,
            ];
        }

        return $result;
    }

    /**
     * Итоговая проверка автора: собирает все комментарии и записи,
     * прогоняет через checkText и возвращает сводку по каждому объекту.
     *
     * @return array ['checked' => int, 'flagged' => int, 'by_type' => [comment=>N, content=>N], 'items' => [...]]
     */
    public function recheckUser(int $user_id, array $log_ids = [], bool $apply = false): array {
        $result = ['checked' => 0, 'flagged' => 0, 'applied' => 0, 'by_type' => ['comment' => 0, 'content' => 0], 'items' => []];

        $options = cmsController::loadOptions('ai_moderator');
        if (empty($options['enabled'])) {
            return $result;
        }

        $collections = $this->getUserCommentsForCheck($user_id);
        foreach ($collections as $c) {
            $result['by_type']['comment']++;
        }

        $contentGroups = $this->getUserContentForCheck($user_id);
        foreach ($contentGroups as $group) {
            foreach ($group['items'] as $c) {
                $result['by_type']['content']++;
            }
        }

        // Проверяем комментарии
        foreach ($collections as $c) {
            $check = $this->checkText($c['text'], ['user_id' => $user_id, 'recheck' => true, 'subject' => 'comment', 'subject_id' => (int)$c['subject_id']]);
            $result['checked']++;
            $is_bad = ($check['action'] !== 'none' && $check['action'] !== 'log');
            if ($is_bad) { $result['flagged']++; }

            $item = [
                'subject'    => 'comment',
                'subject_id' => $c['subject_id'],
                'type_label' => 'comment',
                'text'       => $c['text'],
                'date'       => $c['date'],
                'action'     => $check['action'],
                'score'      => $check['score'],
                'category'   => $check['category'],
                'reason'     => $check['reason'],
                'is_bad'     => $is_bad,
                'applied'    => false,
            ];

            if ($apply && $is_bad) {
                $comment = $this->getItemById('comments', (int)$c['subject_id']);
                if ($comment && !empty($comment['content'])) {
                    $ctx = [
                        'subject'    => 'comment',
                        'subject_id' => (int)($comment['target_id'] ?? 0),
                        'user_id'    => $user_id,
                        'ip'         => '',
                    ];
                    $this->log($ctx, $check['action'], $check['reason'], [
                        'text'         => $comment['content'],
                        'score'        => $check['score'],
                        'category'     => $check['category'],
                        'raw'          => $check['raw'] ?? null,
                        'author_token' => $this->getAuthorToken($ctx),
                    ]);
                    $this->applyCommentDecision($comment, $check['action']);
                    $item['applied'] = true;
                    $result['applied']++;
                }
            }

            $result['items'][] = $item;
        }

        // Проверяем записи контента
        foreach ($contentGroups as $group) {
            foreach ($group['items'] as $c) {
                $check = $this->checkText($c['text'], ['user_id' => $user_id, 'recheck' => true, 'subject' => 'content', 'subject_id' => (int)$c['subject_id']]);
                $result['checked']++;
                $is_bad = ($check['action'] !== 'none' && $check['action'] !== 'log');
                if ($is_bad) { $result['flagged']++; }

                $item = [
                    'subject'    => 'content',
                    'ctype'      => $group['ctype'],
                    'ctype_title'=> $group['ctype_title'],
                    'subject_id' => $c['subject_id'],
                    'type_label' => $group['ctype'],
                    'text'       => $c['text'],
                    'date'       => $c['date'],
                    'action'     => $check['action'],
                    'score'      => $check['score'],
                    'category'   => $check['category'],
                    'reason'     => $check['reason'],
                    'is_bad'     => $is_bad,
                    'applied'    => false,
                ];

                if ($apply && $is_bad) {
                    if ($this->applyContentDecision($group['ctype'], (int)$c['subject_id'], $check['action'])) {
                        $ctx = [
                            'subject'    => 'content',
                            'subject_id' => (int)$c['subject_id'],
                            'user_id'    => $user_id,
                        ];
                        $this->log($ctx, $check['action'], $check['reason'], [
                            'text'         => $c['text'],
                            'score'        => $check['score'],
                            'category'     => $check['category'],
                            'raw'          => $check['raw'] ?? null,
                            'author_token' => $this->getAuthorToken($ctx),
                        ]);
                        $item['applied'] = true;
                        $result['applied']++;
                    }
                }

                $result['items'][] = $item;
            }
        }

        return $result;
    }

    // =========================================================================
    // Очередь фоновой перепроверки
    // =========================================================================

    /**
     * Создаёт очередь перепроверки всех материалов автора.
     * Пропускает уже проверенные и неизменённые записи (cms_ai_moderator_checked).
     *
     * @return int Количество добавленных задач
     */
    public function createRecheckQueue(string $author_key, int $user_id, bool $apply = false): int {

        if (!$user_id) { return 0; }

        $options = cmsController::loadOptions('ai_moderator');
        if (empty($options['enabled'])) { return 0; }

        // Удаляем старые задачи этой проверки
        $this->db->query("DELETE FROM {#}ai_moderator_queue WHERE author_key = '" . $this->db->escape($author_key) . "'");

        $added = 0;

        // Комментарии
        $comments = $this->getUserCommentsForCheck($user_id);
        foreach ($comments as $c) {
            if (!empty($c['is_deleted'])) { continue; }
            if ($this->isChecked($c['subject'], (int)$c['subject_id'], md5($c['text']))) { continue; }
            $this->insert('ai_moderator_queue', [
                'author_key' => $author_key,
                'subject'    => $c['subject'],
                'subject_id'  => (int)$c['subject_id'],
                'ctype'       => null,
                'user_id'     => $user_id,
                'text_hash'   => md5($c['text']),
                'text_preview'=> mb_substr(strip_tags($c['text']), 0, 200),
                'apply'       => $apply ? 1 : 0,
                'status'      => 'pending',
            ]);
            $added++;
        }

        // Записи контента
        $contentGroups = $this->getUserContentForCheck($user_id);
        foreach ($contentGroups as $group) {
            foreach ($group['items'] as $c) {
                if (!empty($c['is_deleted'])) { continue; }
                if ($this->isChecked($c['subject'], (int)$c['subject_id'], md5($c['text']))) { continue; }
                $this->insert('ai_moderator_queue', [
                    'author_key'  => $author_key,
                    'subject'    => $c['subject'],
                    'subject_id'  => (int)$c['subject_id'],
                    'ctype'       => $group['ctype'],
                    'user_id'     => $user_id,
                    'text_hash'   => md5($c['text']),
                    'text_preview'=> mb_substr(strip_tags($c['text']), 0, 200),
                    'apply'       => $apply ? 1 : 0,
                    'status'      => 'pending',
                ]);
                $added++;
            }
        }

        return $added;
    }

    /**
     * Проверяет, есть ли кешированный результат проверки для неизменённого текста.
     */
    public function isChecked(string $subject, int $subject_id, string $text_hash): bool {
        $row = $this->db->getRow(
            'ai_moderator_checked',
            "subject = '" . $this->db->escape($subject) . "' AND subject_id = " . (int)$subject_id . " AND text_hash = '" . $this->db->escape($text_hash) . "'",
            'id'
        );
        return !empty($row);
    }

    /**
     * Статистика очереди для автора.
     */
    public function getQueueStats(string $author_key): array {
        $sql = "SELECT status, COUNT(*) AS cnt FROM {#}ai_moderator_queue WHERE author_key = '" . $this->db->escape($author_key) . "' GROUP BY status";
        $res = $this->db->query($sql);
        $stats = ['total' => 0, 'pending' => 0, 'running' => 0, 'done' => 0, 'error' => 0];
        if ($res) {
            while ($row = $this->db->fetchAssoc($res)) {
                $stats[$row['status']] = (int)$row['cnt'];
                $stats['total'] += (int)$row['cnt'];
            }
            $this->db->freeResult($res);
        }
        return $stats;
    }

    /**
     * Результаты проверки для автора.
     */
    public function getQueueResults(string $author_key): array {
        return $this->
            orderBy('id', 'asc')->
            get('ai_moderator_queue', function ($item, $model) {
                $item['is_bad'] = (!empty($item['result_action']) && $item['result_action'] !== 'none' && $item['result_action'] !== 'log');
                return $item;
            }, false, function ($model) use ($author_key) {
                $model->filterEqual('author_key', $author_key);
            });
    }

    /**
     * Есть ли активные (pending/running) задачи для автора.
     */
    public function isQueueActive(string $author_key): bool {
        $row = $this->db->getRow(
            'ai_moderator_queue',
            "author_key = '" . $this->db->escape($author_key) . "' AND status IN ('pending','running')",
            'id'
        );
        return !empty($row);
    }

    /**
     * Берёт пачку pending-задач и помечает их как running.
     */
    public function acquirePendingTasks(string $author_key, int $limit = 5): array {
        $ids = [];
        // Забираем pending и "зависшие" running (старше 5 минут — воркер мог упасть)
        $stale = date('Y-m-d H:i:s', time() - 300);
        $sql = "SELECT id FROM {#}ai_moderator_queue WHERE author_key = '" . $this->db->escape($author_key) . "' AND (status = 'pending' OR (status = 'running' AND created_at < '" . $this->db->escape($stale) . "')) ORDER BY id ASC LIMIT " . (int)$limit;
        $res = $this->db->query($sql);
        if ($res) {
            while ($row = $this->db->fetchAssoc($res)) {
                $ids[] = (int)$row['id'];
            }
            $this->db->freeResult($res);
        }
        if (!$ids) { return []; }

        $this->db->query("UPDATE {#}ai_moderator_queue SET status = 'running' WHERE id IN (" . implode(',', $ids) . ")");

        $tasks = [];
        $res2 = $this->db->query("SELECT * FROM {#}ai_moderator_queue WHERE id IN (" . implode(',', $ids) . ")");
        if ($res2) {
            while ($row = $this->db->fetchAssoc($res2)) {
                $tasks[] = $row;
            }
            $this->db->freeResult($res2);
        }
        return $tasks;
    }

    /**
     * Помечает задачу выполненной и сохраняет результат.
     */
    public function markTaskDone(int $task_id, array $check): void {
        $this->update('ai_moderator_queue', $task_id, [
            'status'           => 'done',
            'result_action'    => $check['action'],
            'result_score'     => $check['score'],
            'result_category'  => $check['category'],
            'result_reason'    => mb_substr($check['reason'], 0, 500),
            'checked_at'       => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Помечает задачу с ошибкой.
     */
    public function markTaskError(int $task_id, string $message): void {
        $this->update('ai_moderator_queue', $task_id, [
            'status'        => 'error',
            'error_message' => mb_substr($message, 0, 500),
            'checked_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Сохраняет факт проверки в кеш (чтобы в следующий раз пропускать неизменённый текст).
     */
    public function markChecked(array $task, array $check): void {
        try {
            $this->insert('ai_moderator_checked', [
                'subject'       => $task['subject'],
                'subject_id'    => (int)$task['subject_id'],
                'text_hash'     => $task['text_hash'],
                'result_action' => $check['action'],
                'result_score'  => $check['score'],
                'result_category' => $check['category'],
                'result_reason' => mb_substr($check['reason'], 0, 500),
            ]);
        } catch (\Throwable $e) {
            // UNIQUE-конфликт при параллельной обработке — игнорируем
        }
    }

    /**
     * Формирует абсолютный URL комментария из target_url записи cms_comments.
     */
    public function makeCommentTargetUrl(array $comment): string {

        $target_url = trim((string)($comment['target_url'] ?? ''));

        if ($target_url !== '' && !preg_match('~^(https?:)?//~i', $target_url) && strpos($target_url, '/') !== 0) {
            $target_url = '/' . $target_url;
        }

        if (!empty($comment['id'])) {
            $target_url .= '#comment_' . (int)$comment['id'];
        }

        return $target_url;
    }

    /**
     * Загружает актуальные данные задачи: текст и URL для перехода.
     */
    public function loadTaskData(array $task): array {
        $data = ['text' => '', 'target_url' => ''];

        if ($task['subject'] === 'comment') {
            $row = $this->getItemById('comments', (int)$task['subject_id']);
            if ($row) {
                $data['text']       = (string)($row['content'] ?? '');
                $data['target_url'] = $this->makeCommentTargetUrl($row);
            }
        } elseif ($task['subject'] === 'content' && $task['ctype']) {
            $content = cmsCore::getModel('content');
            $table   = $content->getContentTypeTableName($task['ctype']);
            if ($table) {
                $row = $this->getItemById($table, (int)$task['subject_id']);
                if ($row) {
                    $data['text'] = trim(implode(' ', array_filter([
                        $row['title']        ?? '',
                        $row['content']      ?? '',
                        $row['content_html'] ?? '',
                    ], 'is_string')));
                    $slug = ($row['slug'] ?? $row['id']);
                    if (!preg_match('/\.(html|htm)$/i', $slug)) { $slug .= '.html'; }
                    $data['target_url'] = href_to($task['ctype'], $slug);
                }
            }
        }

        return $data;
    }

    /**
     * Получает актуальный текст задачи из БД.
     */
    public function getTaskText(array $task): string {
        return $this->loadTaskData($task)['text'];
    }

    /**
     * Обрабатывает очередь задач для автора (вызывается из worker.php).
     */
    public function processQueue(string $author_key): void {

        while (true) {
            $tasks = $this->acquirePendingTasks($author_key, 5);
            if (!$tasks) { break; }

            foreach ($tasks as $task) {
                try {
                    $task_data = $this->loadTaskData($task);
                    $text      = $task_data['text'];
                    $target_url= $task_data['target_url'];

                    if ($text === '') {
                        $this->markTaskDone((int)$task['id'], $this->result('empty', 'none', false, 0, 'normal', ''));
                        continue;
                    }

                    $ctx = [
                        'subject'    => $task['subject'],
                        'subject_id' => (int)$task['subject_id'],
                        'object_id'  => (int)$task['subject_id'],
                        'target_url' => $target_url,
                        'user_id'    => (int)$task['user_id'],
                        'ip'         => '',
                    ];

                    $check = $this->checkText($text, $ctx);

                    // Применяем решение, если включено
                    if (!empty($task['apply']) && $check['action'] !== 'none' && $check['action'] !== 'log') {
                        if ($task['subject'] === 'comment') {
                            $comment = $this->getItemById('comments', (int)$task['subject_id']);
                            if ($comment && !empty($comment['content'])) {
                                $this->applyCommentDecision($comment, $check['action']);
                            }
                        } elseif ($task['subject'] === 'content' && $task['ctype']) {
                            $this->applyContentDecision((string)$task['ctype'], (int)$task['subject_id'], $check['action']);
                        }
                    }

                    // Пишем лог только если нашли нарушение
                    if ($check['action'] !== 'none' && $check['action'] !== 'log') {
                        $this->log($ctx, $check['action'], $check['reason'], [
                            'text'         => $text,
                            'score'        => $check['score'],
                            'category'     => $check['category'],
                            'raw'          => $check['raw'] ?? null,
                            'author_token' => $this->getAuthorToken($ctx),
                        ]);
                    }

                    $this->markChecked($task, $check);
                    $this->markTaskDone((int)$task['id'], $check);

                } catch (\Throwable $e) {
                    $this->markTaskError((int)$task['id'], $e->getMessage());
                }
            }
        }
    }

//============================================================================//
//  Проверка обновлений на GitHub
//============================================================================//

    const GITHUB_REPO           = 'Kupers/ai_moderator-instantcms';
    const UPDATE_CHECK_INTERVAL = 21600;

    public function getUpdateInfo() {
        if (!$this->db->isTableExists('ai_moderator_updates')) {
            return [];
        }
        return $this->db->getRow('ai_moderator_updates', 'id = 1', '*') ?: [];
    }

    public function saveUpdateInfo($latest_version, $release_url) {

        $latest_version = $latest_version ? "'" . $this->db->escape($latest_version) . "'" : 'NULL';
        $release_url    = $release_url    ? "'" . $this->db->escape($release_url)    . "'" : 'NULL';

        return $this->db->query(
            "INSERT INTO `{#}ai_moderator_updates` (`id`, `latest_version`, `release_url`, `checked_at`)
             VALUES (1, {$latest_version}, {$release_url}, NOW())
             ON DUPLICATE KEY UPDATE `latest_version` = VALUES(`latest_version`), `release_url` = VALUES(`release_url`), `checked_at` = NOW()"
        );
    }

    public function getInstalledComponentVersion() {
        $row = $this->db->getRow('controllers', "name = 'ai_moderator'", 'version');
        return isset($row['version']) ? (string)$row['version'] : '';
    }

    public function getAdminUserIds() {
        $rows = $this->db->getRows('users', 'is_admin = 1', 'id');
        if (!$rows) { return []; }
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int)$row['id'];
        }
        return $ids;
    }

    public function fetchLatestRelease() {

        if (!function_exists('curl_init')) {
            return [null, null];
        }

        $url = 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest';

        $data = file_get_contents_from_url($url, 5, true);

        if (!is_array($data) || empty($data['tag_name'])) {
            return [null, null];
        }

        $tag = ltrim((string)$data['tag_name'], 'vV');

        return [$tag, isset($data['html_url']) ? (string)$data['html_url'] : ''];
    }

    public function checkForUpdates() {

        $cache = $this->getUpdateInfo();

        $checked_at = !empty($cache['checked_at']) ? strtotime($cache['checked_at']) : 0;

        if (!$checked_at || (time() - $checked_at) >= self::UPDATE_CHECK_INTERVAL) {

            list($latest, $release_url) = $this->fetchLatestRelease();

            $this->saveUpdateInfo($latest, $release_url);

            $cache = [
                'latest_version' => $latest,
                'release_url'    => $release_url,
            ];
        }

        if (empty($cache['latest_version'])) {
            return null;
        }

        $installed = $this->getInstalledComponentVersion();
        $latest    = ltrim((string)$cache['latest_version'], 'vV');

        if (!$installed || version_compare($latest, $installed) <= 0) {
            return null;
        }

        return [
            'latest_version'    => $latest,
            'installed_version' => $installed,
            'release_url'       => isset($cache['release_url']) ? (string)$cache['release_url'] : '',
        ];
    }

    public function notifyAdminsAboutUpdate($latest_version, $release_url) {

        $cache = $this->getUpdateInfo();

        if (!empty($cache['notified_version']) && $cache['notified_version'] === $latest_version) {
            return false;
        }

        $admin_ids = $this->getAdminUserIds();
        if (!$admin_ids) {
            return false;
        }

        $text = sprintf(
            LANG_AIM_UPDATE_PM_TEXT,
            $latest_version,
            $release_url ?: 'https://github.com/' . self::GITHUB_REPO . '/releases'
        );

        $sender = (int)cmsUser::get('id');
        if (!$sender) { $sender = 1; }

        try {

            foreach ($admin_ids as $admin_id) {

                $contact = $this->db->getRow('{users}_contacts', 'user_id = ' . (int)$admin_id . ' AND contact_id = ' . (int)$sender, 'id');

                if (!$contact) {
                    $this->db->query(
                        "INSERT INTO `{users}_contacts` (`user_id`, `contact_id`) VALUES (" . (int)$admin_id . ", " . (int)$sender . ")"
                    );
                }

                $this->db->query(
                    "INSERT INTO `{users}_messages` (`from_id`, `to_id`, `content`) VALUES (" .
                    (int)$sender . ", " . (int)$admin_id . ", '" . $this->db->escape($text) . "')"
                );
            }

        } catch (\Throwable $e) {}

        return $this->db->query(
            "UPDATE `{#}ai_moderator_updates` SET `notified_version` = '" . $this->db->escape($latest_version) . "' WHERE `id` = 1"
        );
    }

}