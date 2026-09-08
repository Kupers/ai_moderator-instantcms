<?php

require_once __DIR__ . '/../lib/AiModeratorContentHelper.php';

/**
 * Хук content_before_update: вызывается в content/actions/item_edit.php
 * ДО сохранения изменений записи. Может изменить $item.
 *
 * Данные может быть в двух формах:
 *  - массив [$item, $ctype]
 *  - массив ['item' => $item, 'ctype' => $ctype]
 */
class onAiModeratorContentBeforeUpdate extends cmsAction {

    public function run($data) {

        if (!is_array($data)) { return $data; }

        if (isset($data['item']) && is_array($data['item'])) {
            $data['item'] = AiModeratorContentHelper::apply($data['item']);
            return $data;
        }

        if (isset($data[0]) && is_array($data[0])) {
            $data[0] = AiModeratorContentHelper::apply($data[0]);
            return $data;
        }

        return $data;
    }

}