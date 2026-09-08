<?php $this->setPageTitle(LANG_AIM_VIOLATORS); ?>

<h1><?php echo LANG_AIM_VIOLATORS; ?></h1>

<?php if ($total > 0) { ?>

    <table class="table table-striped">
        <thead>
            <tr>
                <th><?php echo LANG_AIM_VIOLATORS_COL_USER; ?></th>
                <th><?php echo LANG_AIM_VIOLATORS_COL_COUNT; ?></th>
                <th><?php echo LANG_AIM_VIOLATORS_COL_CATEGORIES; ?></th>
                <th><?php echo LANG_AIM_VIOLATORS_COL_LAST; ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item) {

                $is_user = !empty($item['user_id']);
                $name    = $is_user ? ($item['nickname'] ?? '#' . (int)$item['user_id']) : LANG_AIM_VIOLATORS_GUEST;
            ?>
                <tr>
                    <td>
                        <?php if ($is_user) { ?>
                            <a href="<?php echo href_to_profile((int)$item['user_id']); ?>" target="_blank"><?php html($name); ?></a>
                        <?php } else { ?>
                            <?php html($name); ?>
                        <?php } ?>
                    </td>
                    <td><span class="badge bg-danger"><?php echo (int)$item['cnt']; ?></span></td>
                    <td><?php html($item['categories'] ?: '-'); ?></td>
                    <td><?php echo html_date($item['last_date'], true); ?></td>
                    <td class="text-end">
                        <a class="btn btn-primary btn-sm" href="<?php echo href_to('admin', 'controllers', ['edit', 'ai_moderator', 'violator', urlencode($item['author_key'])]); ?>">
                            <?php echo LANG_AIM_VIOLATORS_ACT_OPEN; ?>
                        </a>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <?php echo html_pagebar($page, $perpage, $total, href_to('admin', 'controllers', ['edit', 'ai_moderator', 'violators', '%s'])); ?>

<?php } else { ?>
    <p><?php echo LANG_AIM_VIOLATORS_EMPTY; ?></p>
    <p class="text-muted"><?php echo LANG_AIM_VIOLATORS_EMPTY_HINT; ?></p>
<?php } ?>