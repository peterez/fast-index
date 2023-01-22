<div class="content-area">

    <h1 class="title">History</h1>

    <table class="table table-striped wp-list-table widefat fixed striped table-view-list posts"
           style="width:100%">
        <thead>
        <tr>
            <th>Title</th>
            <th>Date</th>
            <th>Send Status</th>
        </tr>
        </thead>
        <tbody class="the-list">
        <?php foreach ($logs as $item) {
            ?>
            <tr class="iedit author-self level-0 type-post status-publish format-standard hentry">
                <td class="title column-title has-row-actions column-primary page-title">
                    <strong><a class="row-title"
                               href="post.php?post=<?php echo $item['post_parent'] ?>&action=edit"
                            ><?php echo $item['post_title'] ?></a></strong>

                </td>

                <td class="date column-date" data-colname="Date">Sended<br><?php echo $item['post_date'] ?></td>
                <td>OK</td>
                <td><a class="row-title" href="<?php echo $item['post_content'] ?>" target="_blank">GO POST</a></td>
            </tr>

        <?php } ?>

        </tbody>
    </table>


    <?php
    $pureUrl = "admin.php?page=" . $_REQUEST['page'];
    $oldPn = $_REQUEST['pn'] - 1;
    $newPn = $_REQUEST['pn'] + 1;
    ?>
    <nav aria-label="<?php echo _("Pagination") ?>">
        <ul class="pagination mt-3">
            <?php if ($_REQUEST['pn'] >= 1) { ?>
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


<style>
    .content-area {
        padding: 15px;
        background: #f9f9f9;
        border-radius: 5px;
        border: 1px solid #cfcfcf;
        margin-top: 20px;
        margin-right: 20px;
    }

    .content-area h1.title {
        border-bottom: 1px solid #cfcfcf;
        font-size: 1.8em;
        line-height: 1.8em;
    }

</style>
