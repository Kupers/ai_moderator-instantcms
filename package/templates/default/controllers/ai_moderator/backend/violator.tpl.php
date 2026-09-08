<?php $this->setPageTitle(LANG_AIM_VIOLATOR_TITLE); ?>

<?php if ($user) { ?>
    <h1><?php echo LANG_AIM_VIOLATOR_TITLE; ?>: <a href="<?php echo href_to_profile((int)$user['id']); ?>" target="_blank"><?php html($user['nickname']); ?></a></h1>
<?php } else { ?>
    <h1><?php echo LANG_AIM_VIOLATOR_TITLE; ?>: <?php echo LANG_AIM_VIOLATORS_GUEST; ?></h1>
<?php } ?>

<?php if ($user) { ?>

    <div class="mb-3">
        <?php if (!empty($user['avatar'])) { ?>
            <img src="<?php echo $user['avatar']; ?>" class="img-thumbnail me-2" style="height:64px;width:64px;object-fit:cover;vertical-align:middle;" alt="">
        <?php } ?>
        <span class="text-muted"><?php echo LANG_AIM_VIOLATOR_PROFILE; ?>: </span>
        <a href="<?php echo href_to_profile((int)$user['id']); ?>" target="_blank"><?php html($user['nickname']); ?></a>
    </div>

    <form action="<?php echo href_to('admin', 'controllers', ['edit', 'ai_moderator', 'violator_recheck']); ?>" method="post" class="mb-3">
        <?php echo html_csrf_token(); ?>
        <input type="hidden" name="author_key" value="<?php echo htmlspecialchars($author_key); ?>">
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="apply" value="1" id="recheck_apply" checked>
            <label class="form-check-label" for="recheck_apply">
                <?php echo LANG_AIM_RECHECK_APPLY; ?>
            </label>
        </div>
        <button type="submit" class="btn btn-warning" onclick="return confirm('<?php echo LANG_AIM_VIOLATOR_RECHECK_CONFIRM; ?>')">
            <?php echo LANG_AIM_VIOLATOR_RECHECK; ?>
        </button>
    </form>

<?php } ?>

<div id="ai-moderator-queue-results"
     data-url="<?php echo htmlspecialchars(href_to('admin', 'controllers', ['edit', 'ai_moderator', 'violator_queue_status', urlencode($author_key)])); ?>"
     data-active="<?php echo $queue_active ? '1' : '0'; ?>">
    <?php echo $this->render('backend/violator_queue_results', [
        'queue_stats'   => $queue_stats,
        'queue_results' => $queue_results,
        'queue_active'  => $queue_active,
    ], new cmsRequest([], cmsRequest::CTX_INTERNAL)); ?>
</div>

<script>
(function () {
    var container = document.getElementById('ai-moderator-queue-results');
    if (!container) return;

    var isActive  = container.getAttribute('data-active') === '1';
    var url       = container.getAttribute('data-url');
    var timer     = null;

    function update() {
        if (!isActive) return;
        $.getJSON(url, function (data) {
            if (data.html) {
                container.innerHTML = data.html;
            }
            isActive = data.active;
            if (!isActive && timer) {
                clearInterval(timer);
                timer = null;
            }
        });
    }

    if (isActive) {
        timer = setInterval(update, 5000);
    }
})();
</script>

<?php if ($total > 0) { ?>

    <table class="table table-striped">
        <thead>
            <tr>
                <th style="width:1px;"></th>
                <th>ID</th>
                <th><?php echo LANG_AIM_LOGS_COL_CREATED; ?></th>
                <th><?php echo LANG_AIM_LOGS_COL_SUBJECT; ?></th>
                <th><?php echo LANG_AIM_LOGS_COL_ACTION; ?></th>
                <th><?php echo LANG_AIM_LOGS_COL_SCORE; ?></th>
                <th><?php echo LANG_AIM_LOGS_COL_REASON; ?></th>
                <th><?php echo LANG_AIM_LOGS_COL_TEXT; ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $log) {

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

                $target_url = !empty($log['target_url']) ? (string)$log['target_url'] : '';
            ?>
                <tr>
                    <td>
                        <?php if ($target_url) { ?>
                            <a href="<?php echo htmlspecialchars($target_url); ?>" target="_blank" title="<?php echo LANG_AIM_OPEN_TARGET; ?>" class="text-decoration-none">
                                <?php echo html_svg_icon('solid', 'external-link-alt', 16, false); ?>
                            </a>
                        <?php } else { ?>
                            <span class="text-muted">—</span>
                        <?php } ?>
                    </td>
                    <td><?php echo (int)$log['id']; ?></td>
                    <td><?php echo html_date($log['date_pub'], true); ?></td>
                    <td><?php html($log['subject'] ?: '-'); ?><?php if ($log['subject_id']) { ?> #<?php echo (int)$log['subject_id']; ?><?php } ?></td>
                    <td><span class="<?php echo $cls; ?>"><?php echo $label; ?></span></td>
                    <td><?php echo round((float)$log['score'], 2); ?></td>
                    <td><?php html($log['reason'] ?: '-'); ?></td>
                    <td><?php html(mb_substr($log['text'] ?: '-', 0, 80)); ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <?php echo html_pagebar($page, $perpage, $total, href_to('admin', 'controllers', ['edit', 'ai_moderator', 'violator', urlencode($author_key)])); ?>

<?php } else { ?>
    <p><?php echo LANG_AIM_LOGS_EMPTY; ?></p>
<?php } ?>