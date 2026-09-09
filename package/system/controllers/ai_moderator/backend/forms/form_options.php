<?php

class formAiModeratorOptions extends cmsForm {

    public $is_tabbed = true;

    public function init() {

        $category_fields = [];

        $categories = [
            'spam'      => LANG_AIM_CAT_SPAM,
            'ad'        => LANG_AIM_CAT_AD,
            'insult'    => LANG_AIM_CAT_INSULT,
            'malicious' => LANG_AIM_CAT_MALICIOUS,
            'hate'      => LANG_AIM_CAT_HATE,
            'extremism' => LANG_AIM_CAT_EXTREMISM,
            'political' => LANG_AIM_CAT_POLITICAL,
        ];

        $comment_actions = [
            'default'  => LANG_AIM_CAT_ACTION_DEFAULT,
            'hide'     => LANG_AIM_SPAM_ACTION_HIDE,
            'moderate' => LANG_AIM_SPAM_ACTION_MODERATE,
            'delete'   => LANG_AIM_SPAM_ACTION_DELETE_COMMENT,
            'log'      => LANG_AIM_SPAM_ACTION_LOG,
        ];

        $content_actions = [
            'default'    => LANG_AIM_CAT_ACTION_DEFAULT,
            'moderate'   => LANG_AIM_SPAM_ACTION_MODERATE,
            'delete'     => LANG_AIM_SPAM_ACTION_DELETE_CONTENT,
            'hard_delete'=> LANG_AIM_SPAM_ACTION_DELETE_HARD,
            'log'        => LANG_AIM_SPAM_ACTION_LOG,
        ];

        foreach ($categories as $cat => $title) {
            $category_fields[] = [
                'title'  => $title,
                'type'   => 'fieldset',
                'childs' => [

                    new fieldCheckbox("cat_enabled_{$cat}", [
                        'title'   => LANG_AIM_CAT_ENABLED,
                        'default' => 1,
                    ]),

                    new fieldList("cat_comment_action_{$cat}", [
                        'title'   => LANG_AIM_CAT_COMMENT_ACTION,
                        'hint'    => LANG_AIM_CAT_ACTION_HINT,
                        'default' => 'default',
                        'items'   => $comment_actions,
                    ]),

                    new fieldList("cat_content_action_{$cat}", [
                        'title'   => LANG_AIM_CAT_CONTENT_ACTION,
                        'hint'    => LANG_AIM_CAT_ACTION_HINT,
                        'default' => 'default',
                        'items'   => $content_actions,
                    ]),

                    new fieldCheckbox("cat_karma_{$cat}", [
                        'title'   => LANG_AIM_SANCTION_KARMA,
                        'default' => 0,
                    ]),

                    new fieldNumber("cat_karma_points_{$cat}", [
                        'title'   => LANG_AIM_SANCTION_KARMA_POINTS,
                        'hint'    => LANG_AIM_SANCTION_KARMA_POINTS_HINT,
                        'default' => -5,
                        'rules'   => [['min', -100], ['max', 0]],
                    ]),

                    new fieldCheckbox("cat_ban_{$cat}", [
                        'title'   => LANG_AIM_SANCTION_BAN,
                        'default' => 0,
                    ]),

                    new fieldNumber("cat_ban_days_{$cat}", [
                        'title'   => LANG_AIM_CAT_BAN_DAYS,
                        'hint'    => LANG_AIM_CAT_BAN_DAYS_HINT,
                        'default' => 1,
                        'rules'   => [['required'], ['min', 0]],
                    ]),

                    new fieldCheckbox("cat_warning_{$cat}", [
                        'title'   => LANG_AIM_SANCTION_WARNING,
                        'default' => 0,
                    ]),
                ]
            ];
        }

        $fieldsets = [

            [
                'title'  => LANG_AIM_MAIN,
                'type'   => 'fieldset',
                'childs' => [

                    new fieldCheckbox('enabled', [
                        'title'   => LANG_AIM_ENABLED,
                        'hint'    => LANG_AIM_ENABLED_HINT,
                        'default' => 0,
                    ]),

                    new fieldList('moderation_mode', [
                        'title'   => LANG_AIM_MODERATION_MODE,
                        'hint'    => LANG_AIM_MODERATION_MODE_HINT,
                        'default' => 'post',
                        'items'   => [
                            'pre'  => LANG_AIM_MODERATION_MODE_PRE,
                            'post' => LANG_AIM_MODERATION_MODE_POST,
                        ],
                    ]),

                    new fieldList('content_spam_action', [
                        'title'   => LANG_AIM_SPAM_ACTION_CONTENT,
                        'hint'    => LANG_AIM_SPAM_ACTION_CONTENT_HINT,
                        'default' => 'moderate',
                        'items'   => [
                            'moderate'   => LANG_AIM_SPAM_ACTION_MODERATE,
                            'delete'     => LANG_AIM_SPAM_ACTION_DELETE_CONTENT,
                            'hard_delete'=> LANG_AIM_SPAM_ACTION_DELETE_HARD,
                            'log'        => LANG_AIM_SPAM_ACTION_LOG,
                        ],
                    ]),

                    new fieldList('comment_spam_action', [
                        'title'   => LANG_AIM_SPAM_ACTION_COMMENT,
                        'hint'    => LANG_AIM_SPAM_ACTION_COMMENT_HINT,
                        'default' => 'hide',
                        'items'   => [
                            'hide'     => LANG_AIM_SPAM_ACTION_HIDE,
                            'moderate' => LANG_AIM_SPAM_ACTION_MODERATE,
                            'delete'   => LANG_AIM_SPAM_ACTION_DELETE_COMMENT,
                            'log'      => LANG_AIM_SPAM_ACTION_LOG,
                        ],
                    ]),

                    new fieldString('hide_comment_text', [
                        'title'   => LANG_AIM_HIDE_COMMENT_TEXT,
                        'hint'    => LANG_AIM_HIDE_COMMENT_TEXT_HINT,
                        'default' => LANG_AIM_HIDE_COMMENT_TEXT_DEFAULT,
                    ]),

                    new fieldNumber('spam_threshold', [
                        'title'   => LANG_AIM_THRESHOLD,
                        'hint'    => LANG_AIM_THRESHOLD_HINT,
                        'default' => 0.85,
                        'rules'   => [['required'], ['min', 0], ['max', 1]],
                    ]),

                    new fieldNumber('min_length', [
                        'title'   => LANG_AIM_MIN_LENGTH,
                        'hint'    => LANG_AIM_MIN_LENGTH_HINT,
                        'default' => 5,
                        'rules'   => [['required'], ['min', 1]],
                    ]),

                    new fieldCheckbox('skip_moderators', [
                        'title'   => LANG_AIM_SKIP_MODERATORS,
                        'hint'    => LANG_AIM_SKIP_MODERATORS_HINT,
                        'default' => 1,
                    ]),

                    new fieldCheckbox('flag_urls', [
                        'title'   => LANG_AIM_FLAG_URLS,
                        'hint'    => LANG_AIM_FLAG_URLS_HINT,
                        'default' => 0,
                    ]),

                    new fieldText('blacklist', [
                        'title'   => LANG_AIM_BLACKLIST,
                        'hint'    => LANG_AIM_BLACKLIST_HINT,
                        'default' => '',
                        'size'    => 5,
                        'rules'   => [['max', 5000]],
                    ]),

                    new fieldCheckbox('notify_admin', [
                        'title'   => LANG_AIM_NOTIFY_ADMIN,
                        'hint'    => LANG_AIM_NOTIFY_ADMIN_HINT,
                        'default' => 1,
                    ]),

                    new fieldCheckbox('logging_enabled', [
                        'title'   => LANG_AIM_LOGGING,
                        'hint'    => LANG_AIM_LOGGING_HINT,
                        'default' => 1,
                    ]),

                    new fieldCheckbox('sanctions_enabled', [
                        'title'   => LANG_AIM_SANCTIONS_ENABLED,
                        'hint'    => LANG_AIM_SANCTIONS_ENABLED_HINT,
                        'default' => 0,
                    ]),

                    new fieldList('sanction_scope', [
                        'title'   => LANG_AIM_SANCTION_SCOPE,
                        'hint'    => LANG_AIM_SANCTION_SCOPE_HINT,
                        'default' => 'both',
                        'items'   => [
                            'both'    => LANG_AIM_SANCTION_SCOPE_BOTH,
                            'comment' => LANG_AIM_SANCTION_SCOPE_COMMENT,
                            'content' => LANG_AIM_SANCTION_SCOPE_CONTENT,
                        ],
                    ]),

                    new fieldCheckbox('sanction_karma', [
                        'title'   => LANG_AIM_SANCTION_KARMA,
                        'hint'    => LANG_AIM_SANCTION_KARMA_HINT,
                        'default' => 0,
                    ]),

                    new fieldNumber('sanction_karma_points', [
                        'title'   => LANG_AIM_SANCTION_KARMA_POINTS,
                        'hint'    => LANG_AIM_SANCTION_KARMA_POINTS_HINT,
                        'default' => -5,
                        'rules'   => [['min', -100], ['max', 0]],
                    ]),

                    new fieldCheckbox('sanction_ban', [
                        'title'   => LANG_AIM_SANCTION_BAN,
                        'hint'    => LANG_AIM_SANCTION_BAN_HINT,
                        'default' => 0,
                    ]),

                    new fieldNumber('sanction_ban_days', [
                        'title'   => LANG_AIM_SANCTION_BAN_DAYS,
                        'hint'    => LANG_AIM_SANCTION_BAN_DAYS_HINT,
                        'default' => 1,
                        'rules'   => [['required'], ['min', 0]],
                    ]),

                    new fieldCheckbox('sanction_warning', [
                        'title'   => LANG_AIM_SANCTION_WARNING,
                        'hint'    => LANG_AIM_SANCTION_WARNING_HINT,
                        'default' => 0,
                    ]),

                    new fieldText('sanction_warning_text', [
                        'title'   => LANG_AIM_SANCTION_WARNING_TEXT,
                        'hint'    => LANG_AIM_SANCTION_WARNING_TEXT_HINT,
                        'default' => LANG_AIM_WARNING_TEXT,
                        'rules'   => [['required']],
                    ]),
                ]
            ],

            [
                'title'  => LANG_AIM_BACKEND,
                'type'   => 'fieldset',
                'childs' => [

                    new fieldList('backend', [
                        'title'   => LANG_AIM_BACKEND_DRIVER,
                        'hint'    => LANG_AIM_BACKEND_DRIVER_HINT,
                        'default' => 'ollama',
                        'items'   => [
                            'ollama'    => 'Ollama',
                            'yandexgpt' => 'YandexGPT',
                            'openai'    => 'OpenAI / совместимый',
                        ],
                    ]),

                    new fieldString('ollama_host', [
                        'title'   => LANG_AIM_OLLAMA_HOST,
                        'hint'    => LANG_AIM_OLLAMA_HOST_HINT,
                        'default' => 'http://127.0.0.1:11434',
                    ]),

                    new fieldString('ollama_model', [
                        'title'   => LANG_AIM_OLLAMA_MODEL,
                        'hint'    => LANG_AIM_OLLAMA_MODEL_HINT,
                        'default' => 'llama3.1',
                    ]),

                    new fieldCheckbox('preload_model', [
                        'title'   => LANG_AIM_PRELOAD_MODEL,
                        'hint'    => LANG_AIM_PRELOAD_MODEL_HINT,
                        'default' => 1,
                    ]),

                    new fieldString('ollama_keep_alive', [
                        'title'   => LANG_AIM_OLLAMA_KEEP_ALIVE,
                        'hint'    => LANG_AIM_OLLAMA_KEEP_ALIVE_HINT,
                        'default' => '30m',
                    ]),

                    new fieldCheckbox('bg_preload_enabled', [
                        'title'   => LANG_AIM_BG_PRELOAD,
                        'hint'    => LANG_AIM_BG_PRELOAD_HINT,
                        'default' => 0,
                    ]),

                    new fieldNumber('bg_preload_interval', [
                        'title'   => LANG_AIM_BG_PRELOAD_INTERVAL,
                        'hint'    => LANG_AIM_BG_PRELOAD_INTERVAL_HINT,
                        'default' => 15,
                        'rules'   => [
                            ['min' => 1],
                        ],
                        'units'   => LANG_AIM_BG_PRELOAD_INTERVAL_UNITS,
                    ]),

                    new fieldString('yandex_api_key', [
                        'title'   => LANG_AIM_YANDEX_KEY,
                        'hint'    => LANG_AIM_YANDEX_KEY_HINT,
                        'default' => '',
                    ]),

                    new fieldString('yandex_folder_id', [
                        'title'   => LANG_AIM_YANDEX_FOLDER,
                        'default' => '',
                    ]),

                    new fieldString('yandex_model', [
                        'title'   => LANG_AIM_YANDEX_MODEL,
                        'default' => 'yandexgpt/latest',
                    ]),

                    new fieldString('openai_url', [
                        'title'   => LANG_AIM_OPENAI_URL,
                        'hint'    => LANG_AIM_OPENAI_URL_HINT,
                        'default' => '',
                    ]),

                    new fieldString('openai_api_key', [
                        'title'   => LANG_AIM_OPENAI_KEY,
                        'hint'    => LANG_AIM_OPENAI_KEY_HINT,
                        'default' => '',
                    ]),

                    new fieldString('openai_model', [
                        'title'   => LANG_AIM_OPENAI_MODEL,
                        'default' => 'gpt-4o-mini',
                    ]),

                    new fieldNumber('timeout', [
                        'title'   => LANG_AIM_TIMEOUT,
                        'hint'    => LANG_AIM_TIMEOUT_HINT,
                        'default' => 60,
                        'rules'   => [['required'], ['min', 1]],
                    ]),
                ]
            ],

        ];

        return array_merge($fieldsets, $category_fields);
    }

}
