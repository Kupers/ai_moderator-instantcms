<?php

class backendAi_moderator extends cmsBackend {

    public $useDefaultOptionsAction = true;

    public function __construct($request) {
        parent::__construct($request);
        $this->backend_menu[] = [
            'title' => LANG_AIM_LOGS,
            'url'   => href_to($this->root_url, 'logs'),
            'options' => ['icon' => 'clipboard-list']
        ];
        $this->backend_menu[] = [
            'title' => LANG_AIM_VIOLATORS,
            'url'   => href_to($this->root_url, 'violators'),
            'options' => ['icon' => 'gavel']
        ];
        $this->backend_menu[] = [
            'title' => LANG_AIM_TEST,
            'url'   => href_to($this->root_url, 'test'),
            'options' => ['icon' => 'flask']
        ];

        // Копируем пункты компонента в верхнюю панель
        foreach ($this->backend_menu as $item) {
            $this->cms_template->addMenuItem('admin_toolbar', $item);
        }

        // Если есть обновление — показываем его перед кнопкой помощи
        $this->maybeShowUpdateNotice();

        // Кнопка помощи всегда в правом углу
        $this->cms_template->addMenuItem('admin_toolbar', [
            'title'   => LANG_AIM_HELP,
            'url'     => href_to($this->root_url, 'help'),
            'options' => ['icon' => 'question-circle', 'class' => 'ml-auto'],
        ]);
    }

    private function maybeShowUpdateNotice() {

        try {

            $update = $this->model->checkForUpdates();

            if (!$update) {
                return;
            }

            $this->cms_template->addMenuItem('admin_toolbar', [
                'title' => sprintf(LANG_AIM_UPDATE_AVAILABLE, $update['latest_version']),
                'url'   => $update['release_url'] ?: '#',
                'options' => [
                    'icon'   => 'arrow-alt-circle-up',
                    'target' => '_blank',
                    'class'  => 'text-success font-weight-bold'
                ]
            ]);

            $this->model->notifyAdminsAboutUpdate($update['latest_version'], $update['release_url']);

        } catch (\Throwable $e) {}
    }

    public function actionIndex() {
        $this->redirectToAction('options');
    }

    /**
     * Страница помощи по настройке провайдеров LLM.
     */
    public function actionHelp() {
        return $this->cms_template->render('backend/help', []);
    }

    /**
     * Просмотр журнала проверок.
     */
    public function actionLogs() {

        $perpage = 20;
        $page    = (int)$this->request->get('page', 1);
        if ($page < 1) { $page = 1; }

        $total = $this->model->getLogsCount();
        $logs  = $this->model->getLogs($page, $perpage);

        return $this->cms_template->render('backend/logs', [
            'logs'    => $logs,
            'total'   => $total,
            'page'    => $page,
            'perpage' => $perpage,
        ]);
    }

    /**
     * Удалить все логи.
     */
    public function actionLogClear() {

        if (!$this->request->has('submit')) {
            return $this->redirectToAction('logs');
        }

        $csrf_token = $this->request->get('csrf_token', '');
        if (!cmsForm::validateCSRFToken($csrf_token)) {
            cmsUser::addSessionMessage(LANG_FORM_ERRORS, 'error');
            return $this->redirectToAction('logs');
        }

        $this->model->clearLogs();

        cmsUser::addSessionMessage(LANG_SUCCESS_MSG, 'success');
        return $this->redirectToAction('logs');
    }

    /**
     * AJAX: проверка соединения и возврат списка моделей для выбранного бэкенда.
     * Параметры подключения берутся из POST (поле = значение формы), либо из сохранённых опций.
     */
    public function actionCheckConnection() {

        if (!$this->request->isAjax()) {
            return cmsCore::error404();
        }

        $options = cmsController::loadOptions('ai_moderator');
        foreach (['backend', 'ollama_host', 'ollama_model', 'yandex_api_key', 'yandex_folder_id', 'yandex_model',
                  'openai_url', 'openai_api_key', 'openai_model', 'timeout'] as $k) {
            $val = $this->request->get($k, null);
            if ($val !== null) {
                $options[$k] = $val;
            }
        }

        require_once $this->cms_config->root_path . 'system/controllers/ai_moderator/lib/LLMTransport.php';

        $transport = new LLMTransport($options);

        $test  = $transport->testConnection();
        $list  = $test['ok'] ? $transport->listModels() : ['ok' => false, 'models' => [], 'message' => ''];

        return $this->cms_template->renderJSON([
            'ok'      => $test['ok'],
            'message' => $test['message'],
            'version' => $test['version'],
            'models'  => $list['models'] ?? [],
        ]);
    }

    /**
     * Тестовая проверка текста.
     */
    public function actionTest() {

        $result = null;
        $test_text = '';

        if ($this->request->has('submit')) {

            $csrf_token = $this->request->get('csrf_token', '');
            if (!cmsForm::validateCSRFToken($csrf_token)) {
                cmsUser::addSessionMessage(LANG_FORM_ERRORS, 'error');
                return $this->redirectToAction('test');
            }

            $test_text = $this->request->get('text', '');

            if ($test_text !== '') {
                try {
                    $result = $this->model->checkText($test_text, [
                        'subject' => 'test',
                        'subject_id' => 0,
                        'user_id'    => $this->cms_user->id,
                    ]);
                } catch (\Throwable $e) {
                    $result = ['is_spam' => false, 'action' => 'error', 'source' => 'error', 'reason' => $e->getMessage()];
                }
            }
        }

        return $this->cms_template->render('backend/test', [
            'result'    => $result,
            'test_text' => $test_text,
        ]);
    }

    /**
     * Список нарушителей.
     */
    public function actionViolators($page = 1) {

        $perpage = 20;

        $data = $this->model->getViolators((int)$page, $perpage);

        return $this->cms_template->render('backend/violators', [
            'items'   => $data['items'],
            'total'   => $data['total'],
            'page'    => (int)$page,
            'perpage' => $perpage,
        ]);
    }

    /**
     * Детали нарушителя: профиль + его записи.
     */
    public function actionViolator($author_key) {

        $author_key = (string)$author_key;
        $page       = (int)$this->request->get('page', 1);
        if ($page < 1) { $page = 1; }

        $user   = $this->model->getViolatorUser($author_key);
        $data   = $this->model->getViolatorLogs($author_key, $page, 20);

        $queue_active = $this->model->isQueueActive($author_key);
        $queue_stats  = $this->model->getQueueStats($author_key);
        $queue_results = $queue_stats['total'] ? $this->model->getQueueResults($author_key) : [];

        // Если очередь зависла (есть pending, но воркер мог упасть), пробуем перезапустить
        if ($queue_active) {
            $this->model->spawnModerationWorker('queue', ['author_key' => $author_key]);
        }

        return $this->cms_template->render('backend/violator', [
            'author_key'    => $author_key,
            'user'          => $user,
            'items'         => $data['items'],
            'total'         => $data['total'],
            'page'          => (int)$page,
            'perpage'       => 20,
            'queue_active'  => $queue_active,
            'queue_stats'   => $queue_stats,
            'queue_results' => $queue_results,
        ]);
    }

    /**
     * AJAX: статус очереди перепроверки + результаты.
     */
    public function actionViolatorQueueStatus($author_key) {

        if (!$this->request->isAjax()) {
            return cmsCore::error404();
        }

        $author_key = (string)$author_key;

        $queue_active  = $this->model->isQueueActive($author_key);
        $queue_stats   = $this->model->getQueueStats($author_key);
        $queue_results = $queue_stats['total'] ? $this->model->getQueueResults($author_key) : [];

        $html = '';
        if ($queue_stats['total']) {
            $html = $this->cms_template->render('backend/violator_queue_results', [
                'queue_stats'   => $queue_stats,
                'queue_results' => $queue_results,
            ], new cmsRequest([], cmsRequest::CTX_INTERNAL));
        }

        return $this->cms_template->renderJSON([
            'active' => $queue_active,
            'stats'  => $queue_stats,
            'html'   => $html,
        ]);
    }

    /**
     * Перепроверка всех постов и публикаций нарушителя.
     * Создаёт фоновую очередь и запускает воркер.
     */
    public function actionViolatorRecheck() {

        $author_key = (string)$this->request->get('author_key', '');

        $user = $this->model->getViolatorUser($author_key);
        if (!$user) {
            cmsUser::addSessionMessage(LANG_AIM_NOT_FOUND, 'error');
            return $this->redirectToAction('violators');
        }

        $csrf_token = $this->request->get('csrf_token', '');
        if (!cmsForm::validateCSRFToken($csrf_token)) {
            cmsUser::addSessionMessage(LANG_FORM_ERRORS, 'error');
            return $this->redirectToAction('violator', [$author_key]);
        }

        // Если проверка уже идёт — не создаём новую очередь
        if ($this->model->isQueueActive($author_key)) {
            cmsUser::addSessionMessage(LANG_AIM_RECHECK_ALREADY_RUNNING, 'info');
            return $this->redirectToAction('violator', [$author_key]);
        }

        $apply = (bool)$this->request->get('apply', 0);

        try {
            $added = $this->model->createRecheckQueue($author_key, (int)$user['id'], $apply);
            if ($added > 0) {
                $this->model->spawnModerationWorker('queue', ['author_key' => $author_key]);
                cmsUser::addSessionMessage(
                    sprintf(LANG_AIM_RECHECK_LAUNCHED, $added),
                    'info'
                );
            } else {
                cmsUser::addSessionMessage(LANG_AIM_RECHECK_NOTHING_NEW, 'success');
            }
        } catch (\Throwable $e) {
            cmsUser::addSessionMessage($e->getMessage(), 'error');
        }

        return $this->redirectToAction('violator', [$author_key]);
    }

}