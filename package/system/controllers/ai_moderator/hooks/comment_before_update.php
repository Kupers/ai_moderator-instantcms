<?php

/**
 * Хук comment_before_update: вызывается в comments/actions/submit.php
 * при редактировании комментария.
 *
 * Данные: [$comment_id, $content, $content_html, $data]
 *
 * Если комментарий скрыт AI-модератором (заглушка), обычному автору
 * редактировать его нельзя — ответ с ошибкой прерывает сохранение.
 * Админ/модератор с правом 'edit all' может править, при сохранении
 * признак скрытия снимается.
 */
class onAiModeratorCommentBeforeUpdate extends cmsAction {

    public function run($data) {

        $options = cmsController::loadOptions('ai_moderator');
        if (empty($options['enabled'])) { return $data; }

        $comment_id = (int)($data[0] ?? 0);
        if (!$comment_id) { return $data; }

        $content = $data[1] ?? '';

        $model = cmsCore::getModel('ai_moderator');
        $model->ensureCommentHiddenColumn();

        // Текущий комментарий в БД
        $current = cmsCore::getModel('comments')->getComment($comment_id);
        if (!$current) { return $data; }

        $is_hidden = !empty($current['is_hidden']);

        // Страховка для комментариев, скрытых до появления колонки is_hidden:
        // считаем скрытым, если текст совпадает с текущей заглушкой
        if (!$is_hidden && trim((string)$current['content']) === trim($model->getHideCommentText())) {
            $is_hidden = true;
        }

        if ($is_hidden) {

            // Админ/модератор с правом 'edit all' может править скрытый комментарий —
            // при сохранении снимаем признак скрытия
            if (cmsUser::isAllowed('comments', 'edit', 'all')) {
                $data[3]['is_hidden'] = 0;
                return $data;
            }

            // Обычному автору редактировать скрытый комментарий нельзя
            $this->cms_template->renderJSON([
                'error'   => true,
                'message' => LANG_AIM_COMMENT_CANT_EDIT_HIDDEN,
            ]);

            return false;
        }

        if (empty($content)) { return $data; }

        $ctx = [
            'subject'    => 'comment',
            'subject_id' => $comment_id,
            'user_id'    => (int)(cmsUser::get('id') ?: 0),
        ];

        $check = $model->checkText((string)$content, $ctx);

        if ($check['action'] !== 'none') {
            $data[3]['ai_moderator'] = [
                'action'   => $check['action'],
                'score'    => $check['score'],
                'category' => $check['category'],
                'reason'   => $check['reason'],
            ];
        }

        return $data;
    }

}