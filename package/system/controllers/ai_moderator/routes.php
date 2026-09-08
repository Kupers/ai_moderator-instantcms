<?php

function routes_ai_moderator() {
    return [
        [
            'pattern' => '/^ai_moderator\/test$/i',
            'action'  => 'test',
        ],
        [
            'pattern' => '/^ai_moderator\/status$/i',
            'action'  => 'status',
        ],
    ];
}