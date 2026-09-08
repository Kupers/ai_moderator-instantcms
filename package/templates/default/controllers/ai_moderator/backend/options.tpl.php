<?php

    $this->addBreadcrumb(LANG_OPTIONS);

    $this->addToolButton([
        'class' => 'save process-save',
        'title' => LANG_SAVE,
        'href'  => '#',
        'icon'  => 'save'
    ]);

    $check_url = $this->href_to('check_connection');

?>
<div id="<?php echo $this->controller->name; ?>_options_form">
<?php
    $this->renderForm($form, $options, [
        'action' => '',
        'method' => 'post'
    ], $errors);
?>
</div>

<script>
    $(function () {

        const checkUrl = '<?php echo $check_url; ?>';

        const $sanctions = $('input[name="sanctions_enabled"]');
        if ($sanctions.length) {
            const $sanctionFields = $(
                'input[name="sanction_karma"], input[name="sanction_karma_points"], ' +
                'input[name="sanction_ban"], input[name="sanction_ban_days"], ' +
                'input[name="sanction_warning"]'
            );
            const toggleSanctions = function () {
                $sanctionFields.prop('disabled', !$sanctions.prop('checked'));
            };
            $sanctions.on('change', toggleSanctions);
            toggleSanctions();
        }

        const $backend   = $('select[name="backend"]');
        const $host      = $('input[name="ollama_host"]');
        const $openaiUrl = $('input[name="openai_url"]');
        const $openaiKey = $('input[name="openai_api_key"]');
        const $timeout   = $('input[name="timeout"]');

        if (!$backend.length) { return; }

        const currentBackend = () => $backend.val();

        const modelSelectors = {
            ollama:    'input[name="ollama_model"]',
            openai:    'input[name="openai_model"]',
            yandexgpt: 'input[name="yandex_model"]'
        };

        function fillModels(list) {
            const sel = modelSelectors[currentBackend()];
            const $model = $(sel);
            if (!$model.length) { return; }

            const name = $model.attr('name');
            const currentVal = $model.val();

            let options = '';
            $.each(list, function (i, m) {
                const n = String(m).replace(/"/g, '&quot;');
                const selected = (n === currentVal) ? ' selected' : '';
                options += '<option value="' + n + '"' + selected + '>' + n + '</option>';
            });

            const $select = $('<select class="form-control" name="' + name + '">' + options + '</select>');
            $model.replaceWith($select);
        }

        // Контейнер поля "Провайдер"
        const $fieldWrap = $backend.closest('.field, .form-group');

        const $status = $('<span class="ml-2 small"></span>');
        const $checkBtn = $(
            '<button type="button" class="btn btn-secondary btn-sm">' +
            '<?php echo LANG_AIM_CHECK_CONNECTION; ?>' +
            '</button>'
        );

        const $row = $('<div class="mb-2"></div>');
        $row.append($checkBtn).append($status);

        // Кнопка над полем "Провайдер", слева
        $fieldWrap.before($row);

        $checkBtn.on('click', function () {

            const btn = this;
            $(btn).attr('disabled', true);
            $status.removeClass('text-success text-danger').addClass('text-muted');
            $status.text('<?php echo LANG_AIM_CHECK_PENDING; ?>');

            const b = currentBackend();
            let payload = { backend: b, timeout: $timeout.val() || 60 };

            if (b === 'ollama') {
                payload.ollama_host   = $host.val();
                payload.ollama_model  = $('[name="ollama_model"]').val();
            } else if (b === 'openai') {
                payload.openai_url      = $openaiUrl.val();
                payload.openai_api_key  = $openaiKey.val();
                payload.openai_model    = $('[name="openai_model"]').val();
            } else if (b === 'yandexgpt') {
                payload.yandex_api_key    = $('input[name="yandex_api_key"]').val();
                payload.yandex_folder_id  = $('input[name="yandex_folder_id"]').val();
                payload.yandex_model      = $('[name="yandex_model"]').val();
            }

            $.get(checkUrl, payload)
                .done(function (res) {
                    if (res.ok) {
                        $status.removeClass('text-muted').addClass('text-success');
                        const ver = res.version ? ' (' + res.version + ')' : '';
                        if (res.models && res.models.length) {
                            fillModels(res.models);
                            $status.text('<?php echo LANG_AIM_CHECK_OK . ': ' . LANG_AIM_MODELS_LOADED; ?> (' + res.models.length + ')' + ver);
                        } else {
                            $status.text('<?php echo LANG_AIM_CHECK_OK; ?>' + ver);
                        }
                    } else {
                        $status.removeClass('text-muted').addClass('text-danger');
                        $status.text('<?php echo LANG_AIM_CHECK_FAIL; ?> ' + (res.message || ''));
                    }
                })
                .fail(function () {
                    $status.removeClass('text-muted').addClass('text-danger');
                    $status.text('<?php echo LANG_AIM_CHECK_ERROR; ?>');
                })
                .always(function () {
                    $(btn).attr('disabled', false);
                });
        });
    });
</script>