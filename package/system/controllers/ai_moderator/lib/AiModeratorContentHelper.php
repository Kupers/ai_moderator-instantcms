<?php

/**
 * Общий помощник для хуков контента (content_before_add / content_before_update).
 */
class AiModeratorContentHelper {

    /**
     * Проверяет текст записи и применяет режим модерации/блока.
     *
     * В пред-модерации (pre) решение применяется до сохранения.
     * В пост-модерации (post) запись публикуется, а решение применяется
     * в хуке content_after_add (см. AiModeratorContentHelper::applyAfterAdd).
     *
     * @param array $item Данные записи ($item из content-хука)
     * @return array
     */
    public static function apply($item) {

        if (!is_array($item)) { return $item; }

        $options = cmsController::loadOptions('ai_moderator');
        if (empty($options['enabled'])) { return $item; }

        // Пост-модерация: публикуем сразу, ничего не меняем до сохранения
        if (!empty($options['moderation_mode']) && $options['moderation_mode'] === 'post') {
            return $item;
        }

        $check = self::checkItem($item);

        if ($check['action'] === 'moderate') {
            $item['is_approved'] = 0;
        }

        if ($check['action'] === 'delete') {
            $item['is_approved'] = 0;
            $item['is_pub']      = 0;
        }

        if ($check['action'] === 'hard_delete') {
            // В предмодерации записи ещё нет в БД — блокируем сохранение
            $item['is_approved'] = 0;
            $item['is_pub']      = 0;
            if (array_key_exists('is_deleted', $item)) {
                $item['is_deleted'] = 1;
            }
        }

        return $item;
    }

    /**
     * Пост-модерация: применяет решение после сохранения записи.
     *
     * Вызывается из хука content_after_add. Запись уже в базе.
     */
    public static function applyAfterAdd($item) {

        if (!is_array($item)) { return $item; }

        $options = cmsController::loadOptions('ai_moderator');
        if (empty($options['enabled'])) { return $item; }

        if (empty($options['moderation_mode']) || $options['moderation_mode'] !== 'post') {
            return $item;
        }

        $model = cmsCore::getModel('ai_moderator');

        // Пытаемся отложить проверку в фоновый воркер (не блокируем ответ)
        if (!empty($item['ctype_name']) && !empty($item['id'])) {

            if ($model->spawnModerationWorker('content', [
                'item_id'    => (int)$item['id'],
                'ctype_name' => (string)$item['ctype_name'],
                'subject'    => 'content',
                'subject_id' => (int)$item['id'],
                'target_url' => !empty($item['ctype_name']) ? href_to($item['ctype_name'], ($item['slug'] ?? $item['id']) . '.html') : '',
                'user_id'    => (int)($item['user_id'] ?? 0),
            ])) {
                return $item;
            }
        }

        // Фолбэк: если фоновый запуск невозможен, проверяем синхронно
        $check = self::checkItem($item);

        if ($check['action'] === 'none' || $check['action'] === 'log') {
            return $item;
        }

        self::applyDecision($item, $check);

        return $item;
    }

    /**
     * Применяет решение модерации к записи контента.
     */
    private static function applyDecision($item, $check) {

        if (empty($item['ctype_name']) || empty($item['id'])) { return $item; }

        try {

            $content = cmsCore::getModel('content');
            $table   = $content->getContentTypeTableName($item['ctype_name']);

            if ($check['action'] === 'moderate') {
                $content->update($table, $item['id'], ['is_approved' => 0]);
                $item['is_approved'] = 0;
            }

            if ($check['action'] === 'delete') {
                $content->update($table, $item['id'], ['is_approved' => 0, 'is_pub' => 0]);
                $item['is_approved'] = 0;
                $item['is_pub']      = 0;
            }

            if ($check['action'] === 'hard_delete') {
                $model = cmsCore::getModel('ai_moderator');
                $model->hardDeleteContent($content, $item['ctype_name'], (int)$item['id']);
            }

        } catch (\Throwable $e) {}

        return $item;
    }

    /**
     * Собирает текст и запускает проверку.
     */
    private static function checkItem($item) {

        // Собираем текст из основных полей
        $text = trim(implode(' ', array_filter([
            $item['title']        ?? '',
            $item['content']      ?? '',
            $item['content_html'] ?? '',
        ], 'is_string')));

        $model = cmsCore::getModel('ai_moderator');

        $ctx = [
            'subject'    => 'content',
            'subject_id' => (int)($item['id'] ?? 0),
            'object_id'  => (int)($item['id'] ?? 0),
            'target_url' => !empty($item['ctype_name']) ? href_to($item['ctype_name'], ($item['slug'] ?? $item['id']) . '.html') : '',
            'user_id'    => (int)($item['user_id'] ?? (cmsUser::get('id') ?: 0)),
        ];

        return $model->checkText($text, $ctx);
    }

}