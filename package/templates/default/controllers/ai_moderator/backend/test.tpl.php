<?php $this->setPageTitle(LANG_AIM_TEST); ?>

<h1><?php echo LANG_AIM_TEST; ?></h1>

<p class="text-muted"><?php echo LANG_AIM_TEST_HINT; ?></p>

<form method="post" class="form-horizontal">
    <?php echo html_csrf_token(); ?>

    <div class="form-group">
        <label class="control-label"><?php echo LANG_AIM_TEST_LABEL; ?></label>
        <textarea name="text" class="form-control" rows="5" required><?php echo htmlspecialchars($test_text); ?></textarea>
    </div>

    <button type="submit" name="submit" class="btn btn-primary"><?php echo LANG_AIM_TEST_BUTTON; ?></button>
</form>

<?php if ($result) { ?>

    <hr>

    <?php if (!empty($result['is_spam'])) { ?>
        <div class="alert alert-danger">
            <strong><?php echo LANG_AIM_TEST_IS_SPAM; ?></strong>
        </div>
    <?php } else { ?>
        <div class="alert alert-success">
            <strong><?php echo LANG_AIM_TEST_NOT_SPAM; ?></strong>
        </div>
    <?php } ?>

    <table class="table table-bordered">
        <tr>
            <th><?php echo LANG_AIM_TEST_ACTION; ?></th>
            <td><?php echo htmlspecialchars($result['action'] ?? '-'); ?></td>
        </tr>
        <tr>
            <th><?php echo LANG_AIM_TEST_SOURCE; ?></th>
            <td><?php echo htmlspecialchars($result['source'] ?? '-'); ?></td>
        </tr>
        <tr>
            <th><?php echo LANG_AIM_TEST_CATEGORY; ?></th>
            <td>
                <?php
                    $cat = (string)($result['category'] ?? '-');
                    $cat_const = 'LANG_AIM_CAT_' . strtoupper($cat);
                    echo defined($cat_const) ? htmlspecialchars(constant($cat_const)) : htmlspecialchars($cat);
                ?>
            </td>
        </tr>
        <tr>
            <th><?php echo LANG_AIM_TEST_SCORE; ?></th>
            <td><?php echo round((float)($result['score'] ?? 0), 3); ?></td>
        </tr>
        <tr>
            <th><?php echo LANG_AIM_TEST_REASON; ?></th>
            <td><?php echo htmlspecialchars($result['reason'] ?? '-'); ?></td>
        </tr>
    </table>

<?php } ?>