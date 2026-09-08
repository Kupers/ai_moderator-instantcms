<?php $this->setPageTitle(LANG_AIM_LOGS); ?>

<h1><?php echo LANG_AIM_LOGS; ?></h1>

<?php if ($total > 0) { ?>

    <form action="<?php echo href_to('admin', 'controllers', ['edit', 'ai_moderator', 'log_clear']); ?>" method="post" class="mb-3">
        <?php echo html_csrf_token(); ?>
        <button type="submit" name="submit" class="btn btn-danger" onclick="return confirm('<?php echo LANG_AIM_LOGS_DELETE_ALL_CONFIRM; ?>')">
            <?php echo LANG_AIM_LOGS_DELETE_ALL; ?>
        </button>
    </form>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th><?php echo LANG_AIM_LOGS_COL_CREATED; ?></th>
                <th><?php echo LANG_AIM_LOGS_COL_SUBJECT; ?></th>
                <th><?php echo LANG_AIM_LOGS_COL_AUTHOR; ?></th>
                <th><?php echo LANG_AIM_LOGS_COL_ACTION; ?></th>
                <th><?php echo LANG_AIM_LOGS_COL_SCORE; ?></th>
                <th><?php echo LANG_AIM_LOGS_COL_REASON; ?></th>
                <th><?php echo LANG_AIM_LOGS_COL_TEXT; ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log) {

                $cls = 'text-success';
                switch ($log['action']) {
                    case 'block':
                    case 'delete':  $cls = 'text-danger';  $label = LANG_AIM_LOGS_ACT_DELETE;   break;
                    case 'moderate': $cls = 'text-warning'; $label = LANG_AIM_LOGS_ACT_MODERATE; break;
                    case 'hide':    $cls = 'text-secondary';$label = LANG_AIM_LOGS_ACT_HIDE;     break;
                    case 'log':     $cls = 'text-muted';   $label = LANG_AIM_LOGS_ACT_LOG;      break;
                    case 'error':   $cls = 'text-danger';  $label = LANG_AIM_LOGS_ACT_ERROR;    break;
                    default:        $label = htmlspecialchars($log['action']);
                }
            ?>
                <tr>
                    <td><?php echo (int)$log['id']; ?></td>
                    <td><?php echo html_date($log['date_pub'], true); ?></td>
                    <td><?php html($log['subject'] ?: '-'); ?><?php if ($log['subject_id']) { ?> #<?php echo (int)$log['subject_id']; ?><?php } ?></td>
                    <td><?php if ($log['user_id']) { ?><a href="<?php echo href_to_profile($log['user_id']); ?>"><?php html($log['nickname'] ?: '#' . (int)$log['user_id']); ?></a><?php } else { ?>-<?php } ?></td>
                    <td><span class="<?php echo $cls; ?>"><?php echo $label; ?></span></td>
                    <td><?php echo round((float)$log['score'], 2); ?></td>
                    <td><?php html($log['reason'] ?: '-'); ?></td>
                    <td><?php html(mb_substr($log['text'] ?: '-', 0, 80)); ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <?php echo html_pagebar($page, $perpage, $total, href_to('admin', 'controllers', ['edit', 'ai_moderator', 'logs'])); ?>

<?php } else { ?>
    <p><?php echo LANG_AIM_LOGS_EMPTY; ?></p>
<?php } ?>