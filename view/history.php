<div id="fi" class="content-area">

    <h1 class="title"><?php esc_attr_e('Simple Report',"fast-index"); ?></h1>

    <table class="table table-striped wp-list-table widefat fixed striped table-view-list posts"
           style="width:100%">
        <thead>
        <tr>
            <th><?php esc_attr_e('Total Sent',"fast-index"); ?></th>
            <th><?php esc_attr_e('Total Waiting',"fast-index"); ?></th>
            <th><?php esc_attr_e('Sent Today',"fast-index"); ?></th>
        </tr>
        </thead>
        <tbody class="the-list">
        <tr>
            <td><?php echo esc_attr($totalSent);?></td>
            <td><?php echo esc_attr($totalWaitingSubmit);?></td>
            <td><?php echo esc_attr($totalSubmitToday);?></td>
        </tr>

        </tbody>

    </table>


    <br>
    <h1 class="title"><?php esc_attr_e('History',"fast-index"); ?></h1>
    <br>

    <table class="table table-striped wp-list-table widefat fixed striped table-view-list posts"
           style="width:100%">
        <thead>
        <tr>
            <th><?php esc_attr_e('Title',"fast-index"); ?></th>
            <th><?php esc_attr_e('Date',"fast-index"); ?></th>
            <th><?php esc_attr_e('Send Status',"fast-index"); ?></th>
            <th><?php esc_attr_e('Post',"fast-index"); ?></th>
        </tr>
        </thead>
        <tbody class="the-list">
        <?php foreach ($logs as $item) {
            ?>
            <tr >
                <td class="title column-title has-row-actions column-primary page-title">
                    <strong><a class="row-title"
                               href="post.php?post=<?php echo esc_attr($item['post_parent']) ?>&action=edit"
                            ><?php echo esc_attr($item['post_title']) ?></a></strong>

                </td>
                <td class="date column-date" data-colname="Date">Sended<br><?php echo esc_attr($item['post_date']) ?></td>
                <td><?php esc_attr_e('OK',"fast-index"); ?></td>
                <td><a class="row-title" href="<?php echo esc_attr($item['post_content']) ?>" target="_blank"><?php esc_attr_e('GO POST',"fast-index"); ?></a></td>
            </tr>

        <?php } ?>

        </tbody>
    </table>


    <?php
    $page = intval(sanitize_text_field($_REQUEST['page']));
    $pn = intval(sanitize_text_field($_REQUEST['pn']));

    $pureUrl = "admin.php?page=" . $page;
    $oldPn = $pn - 1;
    $newPn = $pn + 1;
    ?>
    <nav aria-label="<?php echo _("Pagination") ?>">
        <ul class="pagination mt-3">
            <?php if ($pn >= 1) { ?>
                <li class="page-item"><a class="page-link"
                                         href="<?php echo $pureUrl . "&pn=" . $oldPn ?>"><?php _e("Previous") ?></a>
                </li>
            <?php } ?>
            <?php if (count($logs) >= 20) { ?>
                <li class="page-item"><a class="page-link"
                                         href="<?php echo $pureUrl . "&pn=" . $newPn ?>"><?php _e("Next") ?></a>
                </li>
            <?php } ?>
        </ul>
    </nav>


</div>

