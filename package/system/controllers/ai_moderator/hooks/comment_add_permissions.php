<?php

/**
 * Хук comment_add_permissions: вызывается в comments/actions/submit.php
 * ДО сохранения комментария. Если вернуть 'error' => true — комментарий
 * отклоняется с сообщением (жёсткий блок).
 *
 * В пост-модерации не блокирует: комментарий публикуется, а проверка
 * применяется в хуке comment_after_add.
 *
 * Данные: [$comment, $permissions]
 */
class onAiModeratorCommentAddPermissions extends cmsAction {

    public function run($data) {

        list($comment, $permissions) = $data;

        if ($permissions['error']) { return $data; }

        if (empty($comment['content'])) { return $data; }

        $options = cmsController::loadOptions('ai_moderator');

        // Пост-модерация: не задерживаем публикацию, проверка в comment_after_add
        if (!empty($options['moderation_mode']) && $options['moderation_mode'] === 'post') {
            return [$comment, $permissions];
        }

        $model = cmsCore::getModel('ai_moderator');

        $text = $comment['content'];
        $ctx  = [
            'subject'    => 'comment',
            'subject_id' => (int)($comment['target_id'] ?? 0),
            'user_id'    => (int)($comment['user_id'] ?? (cmsUser::get('id') ?: 0)),
        ];

        $check = $model->checkText($text, $ctx);

        return $model->applyToComment($comment, $permissions, $check);
    }

}