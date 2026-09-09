<?php

/**
 * Хук controller_ai_moderator_after_save_options: автоматически создаёт
 * задачу планировщика «Фоновый прогрев Ollama», если включена опция
 * bg_preload_enabled, и выключает её, если опция выключена.
 *
 * Период задачи синхронизируется с bg_preload_interval.
 */
class onAiModeratorControllerAiModeratorAfterSaveOptions extends cmsAction {

    public function run($data) {

        $options = (is_array($data) ? $data : []);

        $enabled  = !empty($options['bg_preload_enabled']) ? 1 : 0;
        $interval = max(1, (int)($options['bg_preload_interval'] ?? 15));

        $model = new cmsModel();

        $model->filterEqual('controller', 'ai_moderator');
        $model->filterEqual('hook', 'warmup');
        $task = $model->getItem('scheduler_tasks');

        $title = 'AI-модератор: фоновый прогрев Ollama';

        if ($enabled) {

            $task_data = [
                'title'            => $title,
                'controller'       => 'ai_moderator',
                'hook'             => 'warmup',
                'period'           => $interval,
                'is_strict_period' => 1,
                'consistent_run'   => 1,
                'ordering'         => 0,
                'is_active'        => 1,
                'is_new'           => 1,
            ];

            if ($task) {
                $model->update('scheduler_tasks', $task['id'], $task_data);
            } else {
                $model->insert('scheduler_tasks', $task_data);
            }

        } elseif ($task) {

            // Выключаем задачу (не удаляем, чтобы сохранить историю выполнения)
            $model->update('scheduler_tasks', $task['id'], [
                'is_active' => 0,
            ]);
        }
    }

}