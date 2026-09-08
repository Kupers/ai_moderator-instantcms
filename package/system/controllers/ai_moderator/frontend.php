<?php

class ai_moderator extends cmsFrontend {

    protected $useOptions = true;

    public function getOptions() {
        return cmsController::loadOptions('ai_moderator');
    }

    public function actionIndex() {
        return cmsCore::error404();
    }

    /**
     * Быстрый статус: показывает включён ли модуль и какой бэкенд выбран.
     * Доступно только админам.
     */
    public function actionStatus() {

        if (!$this->cms_user->is_admin) {
            return cmsCore::error404();
        }

        $options = $this->options;

        return $this->cms_template->renderJSON([
            'error'     => false,
            'enabled'   => !empty($options['enabled']),
            'backend'   => $options['backend'] ?? 'none',
            'threshold' => (float)($options['spam_threshold'] ?? 0.85),
            'mode'      => $options['moderation_mode'] ?? 'post',
        ]);
    }

    /**
     * Тестовая проверка текста через выбранный бэкенд (админ).
     */
    public function actionTest() {

        if (!$this->cms_user->is_admin) {
            return cmsCore::error404();
        }

        $text = $this->request->get('text', '');
        if (!$text) {
            return $this->cms_template->renderJSON(['error' => true, 'message' => 'empty text']);
        }

        try {
            $result = $this->model->checkText($text, ['test' => true, 'subject' => 'test']);
            return $this->cms_template->renderJSON([
                'error'  => false,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            return $this->cms_template->renderJSON([
                'error'   => true,
                'message' => $e->getMessage(),
            ]);
        }
    }

}