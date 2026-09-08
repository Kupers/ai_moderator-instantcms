<?php

/**
 * Фоновый воркер AI-модератора.
 *
 * Запускается отдельным процессом php.exe (см. model->spawnModerationWorker)
 * и выполняет отложенную пост-модерацию без блокировки HTTP-ответа.
 *
 * Использование:
 *   php worker.php '{"job":"comment","payload":{...}}'
 *   php worker.php '{"job":"queue","payload":{"author_key":"user:2"}}'
 *
 * Аргументы передаются через переменную окружения AIMOD_PAYLOAD
 * (из proc_open) или argv[1].
 */

define('SESSION_START', 0);

// Корень сайта: .../system/controllers/ai_moderator/worker.php -> 3 уровня вверх
$root = dirname(__DIR__, 3);
$_SERVER['DOCUMENT_ROOT'] = $root;

require $root . '/bootstrap.php';

// Аргументы задачи. Приоритет: переменная окружения (из proc_open),
// фолбэк — argv[1] (ручной запуск из командной строки).
$raw = (string)getenv('AIMOD_PAYLOAD');
if ($raw === '') {
    $raw = (string)($argv[1] ?? '');
}

$args = json_decode($raw, true);

if (!is_array($args) || empty($args['job'])) {
    exit(1);
}

try {

    $model = cmsCore::getModel('ai_moderator');

    $payload = (is_array($args['payload'] ?? null)) ? $args['payload'] : [];

    switch ($args['job']) {
        case 'comment':
            $model->processCommentModeration($payload);
            break;
        case 'content':
            $model->processContentModeration($payload);
            break;
        case 'queue':
            $author_key = (string)($payload['author_key'] ?? '');
            if ($author_key) {
                $model->processQueue($author_key);
            }
            break;
    }

} catch (\Throwable $e) {

    try {
        $p       = (is_array($args['payload'] ?? null)) ? $args['payload'] : [];
        $subject = (string)($p['subject'] ?? 'async');
        $model->log([
            'subject'    => $subject,
            'subject_id' => (int)($p['subject_id'] ?? 0),
            'user_id'    => (int)($p['user_id'] ?? 0),
        ], 'error', mb_substr($e->getMessage(), 0, 500), ['text' => 'worker_error']);
    } catch (\Throwable $ee) {}

    exit(2);
}

exit(0);