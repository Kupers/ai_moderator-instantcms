<?php

require_once __DIR__ . '/../lib/AiModeratorContentHelper.php';

/**
 * Хук content_after_add: вызывается в content/actions/item_add.php
 * ПОСЛЕ сохранения записи.
 *
 * Используется для пост-модерации: запись уже опубликована,
 * решение (скрыть/на модерацию/снять с публикации) применяется здесь.
 *
 * Данные: $item
 */
class onAiModeratorContentAfterAdd extends cmsAction {

    public function run($data) {
        return AiModeratorContentHelper::applyAfterAdd($data);
    }

}