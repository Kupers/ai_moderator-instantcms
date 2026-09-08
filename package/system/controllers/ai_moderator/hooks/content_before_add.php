<?php

require_once __DIR__ . '/../lib/AiModeratorContentHelper.php';

/**
 * Хук content_before_add: вызывается в content/actions/item_add.php
 * ДО сохранения записи. Может изменить $item.
 *
 * Данные: $item
 */
class onAiModeratorContentBeforeAdd extends cmsAction {

    public function run($data) {
        return AiModeratorContentHelper::apply($data);
    }

}