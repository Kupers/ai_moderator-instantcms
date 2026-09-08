<?php

require_once __DIR__ . '/../lib/AiModeratorContentHelper.php';

/**
 * Хук content_after_update: вызывается в content/actions/item_edit.php
 * ПОСЛЕ сохранения правок записи.
 *
 * Используется для пост-модерации: применяет решение к уже опубликованной записи.
 *
 * Данные: $item
 */
class onAiModeratorContentAfterUpdate extends cmsAction {

    public function run($data) {
        return AiModeratorContentHelper::applyAfterAdd($data);
    }

}