<?php

function uninstall_package() {

    $db = \cmsDatabase::getInstance();

    try {
        @$db->query("DROP TABLE IF EXISTS `{#}ai_moderator_logs`");
    } catch (\Throwable $e) {}

    return true;
}