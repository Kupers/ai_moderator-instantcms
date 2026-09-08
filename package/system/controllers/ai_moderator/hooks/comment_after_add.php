<?php

/**
 * Хук comment_after_add: вызывается в comments/actions/submit.php
 * ПОСЛЕ сохранения комментария (только если комментарий одобрен).
 *
 * В пост-модерации здесь применяется решение: скрыть текст,
 * отправить на модерацию или удалить.
 *
 * Данные: $comment
 */
class onAiModeratorCommentAfterAdd extends cmsAction {

    public function run($comment) {

        if (empty($comment['content'])) { return $comment; }

        $options = cmsController::loadOptions('ai_moderator');

        // Работает только в режиме пост-модерации
        if (empty($options['moderation_mode']) || $options['moderation_mode'] !== 'post') {
            return $comment;
        }

        $model = cmsCore::getModel('ai_moderator');

        $comment_target_url = $model->makeCommentTargetUrl($comment);

        // Пытаемся отложить проверку в фоновый воркер, чтобы не блокировать
        // AJAX-ответ (кнопка «Отправить» не крутится, пока отвечает LLM)
        if ($model->spawnModerationWorker('comment', [
            'comment_id' => (int)($comment['id'] ?? 0),
            'subject'    => 'comment',
            'subject_id' => (int)($comment['target_id'] ?? 0),
            'target_url' => $comment_target_url,
            'user_id'    => (int)($comment['user_id'] ?? 0),
        ])) {
            return $comment;
        }

        // Фолбэк: если фоновый запуск невозможен, проверяем синхронно
        $ctx = [
            'subject'    => 'comment',
            'subject_id' => (int)($comment['target_id'] ?? 0),
            'object_id'  => (int)($comment['id'] ?? 0),
            'target_url' => $comment_target_url,
            'user_id'    => (int)($comment['user_id'] ?? 0),
        ];

        $check = $model->checkText((string)$comment['content'], $ctx);

        if ($check['action'] === 'none' || $check['action'] === 'log') {
            return $comment;
        }

        return $model->applyToExistingComment($comment, $check);
    }

}