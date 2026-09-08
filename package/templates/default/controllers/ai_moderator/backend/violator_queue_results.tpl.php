<?php if (!empty($queue_stats['total'])) { ?>

    <div class="card mb-3" id="ai-moderator-queue-progress">
        <div class="card-body">
            <h5 class="card-title"><?php echo LANG_AIM_RECHECK_PROGRESS; ?></h5>
            <div class="progress mb-2" style="height: 24px;">
                <?php
                    $done_percent = $queue_stats['total'] ? round(($queue_stats['done'] + $queue_stats['error']) / $queue_stats['total'] * 100) : 0;
                    $error_percent = $queue_stats['total'] ? round($queue_stats['error'] / $queue_stats['total'] * 100) : 0;
                ?>
                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $done_percent; ?>%" aria-valuenow="<?php echo $done_percent; ?>" aria-valuemin="0" aria-valuemax="100">
                    <?php echo $done_percent; ?>%
                </div>
            </div>
            <p class="mb-0 text-muted">
                <?php echo sprintf(LANG_AIM_RECHECK_PROGRESS_TEXT,
                    (int)$queue_stats['done'],
                    (int)$queue_stats['running'],
                    (int)$queue_stats['pending'],
                    (int)$queue_stats['error'],
                    (int)$queue_stats['total']
                ); ?>
            </p>
            <?php if (!empty($queue_active)) { ?>
                <p class="mb-0 mt-2 text-info"><i class="spinner-border spinner-border-sm"></i> <?php echo LANG_AIM_RECHECK_IN_PROGRESS; ?></p>
            <?php } elseif ($queue_stats['pending'] == 0) { ?>
                <p class="mb-0 mt-2 text-success"><?php echo LANG_AIM_RECHECK_COMPLETED; ?></p>
            <?php } ?>
        </div>
    </div>

    <?php if (!empty($queue_results)) { ?>
        <h3><?php echo LANG_AIM_RECHECK_RESULTS; ?></h3>
        <table class="table table-striped table-sm">
            <thead>
                <tr>
                    <th><?php echo LANG_AIM_RECHECK_COL_TYPE; ?></th>
                    <th><?php echo LANG_AIM_RECHECK_COL_TEXT; ?></th>
                    <th><?php echo LANG_AIM_RECHECK_COL_RESULT; ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($queue_results as $it) {
                    $action = $it['result_action'] ?? 'none';
                    $rcls = 'text-success';
                    $rlabel = '—';
                    switch ($action) {
                        case 'moderate': $rcls = 'text-warning';  $rlabel = LANG_AIM_LOGS_ACT_MODERATE; break;
                        case 'hide':     $rcls = 'text-secondary'; $rlabel = LANG_AIM_LOGS_ACT_HIDE;     break;
                        case 'delete':
                        case 'block':    $rcls = 'text-danger';   $rlabel = LANG_AIM_LOGS_ACT_DELETE;   break;
                        case 'error':    $rcls = 'text-danger';   $rlabel = LANG_AIM_LOGS_ACT_ERROR;    break;
                    }
                    $type_label = $it['ctype'] ?: $it['subject'];
                ?>
                    <tr>
                        <td><?php html($type_label); ?> #<?php echo (int)$it['subject_id']; ?></td>
                        <td><?php html(mb_substr(strip_tags($it['text_preview'] ?: '-'), 0, 80)); ?></td>
                        <td>
                            <span class="<?php echo $rcls; ?>"><?php echo $rlabel; ?></span>
                            <?php if (!empty($it['result_category']) && $it['result_category'] !== 'normal') {
                                $rcat = (string)$it['result_category'];
                                $rcat_const = 'LANG_AIM_CAT_' . strtoupper($rcat);
                                $rcat_label = defined($rcat_const) ? constant($rcat_const) : $rcat;
                            ?>
                                <span class="badge bg-warning text-dark ms-1"><?php html($rcat_label); ?></span>
                            <?php } ?>
                            <?php if (!empty($it['error_message'])) { ?>
                                <span class="text-danger small d-block"><?php html($it['error_message']); ?></span>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>

<?php } ?>