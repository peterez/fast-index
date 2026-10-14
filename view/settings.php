<?php
$httpStatusCodes = array(
    100 => 'Continue',
    101 => 'Switching Protocols',
    102 => 'Processing',
    200 => 'OK',
    201 => 'Created',
    202 => 'Accepted',
    203 => 'Non-Authoritative Information',
    204 => 'No Content',
    205 => 'Reset Content',
    206 => 'Partial Content',
    207 => 'Multi-Status',
    208 => 'Already Reported',
    226 => 'IM Used',
    249 => 'Timeout',
    300 => 'Multiple Choices',
    301 => 'Moved Permanently',
    302 => 'Found',
    303 => 'See Other',
    304 => 'Not Modified',
    305 => 'Use Proxy',
    306 => 'Switch Proxy',
    307 => 'Temporary Redirect',
    308 => 'Permanent Redirect',
    400 => 'Bad Request',
    401 => 'Unauthorized',
    402 => 'Payment Required',
    403 => 'Forbidden',
    404 => 'Not Found',
    405 => 'Method Not Allowed',
    406 => 'Not Acceptable',
    407 => 'Proxy Authentication Required',
    408 => 'Request Timeout',
    409 => 'Conflict',
    410 => 'Gone',
    411 => 'Length Required',
    412 => 'Precondition Failed',
    413 => 'Request Entity Too Large',
    414 => 'Request-URI Too Long',
    415 => 'Unsupported Media Type',
    416 => 'Requested Range Not Satisfiable',
    417 => 'Expectation Failed',
    418 => 'I\'m a teapot',
    419 => 'Authentication Timeout',
    420 => 'Enhance Your Calm',
    420 => 'Method Failure',
    422 => 'Unprocessable Entity',
    423 => 'Locked',
    424 => 'Failed Dependency',
    424 => 'Method Failure',
    425 => 'Unordered Collection',
    426 => 'Upgrade Required',
    428 => 'Precondition Required',
    429 => 'Too Many Requests',
    431 => 'Request Header Fields Too Large',
    444 => 'No Response',
    449 => 'Retry With',
    450 => 'Blocked by Windows Parental Controls',
    451 => 'Redirect',
    451 => 'Unavailable For Legal Reasons',
    494 => 'Request Header Too Large',
    495 => 'Cert Error',
    496 => 'No Cert',
    497 => 'HTTP to HTTPS',
    499 => 'Client Closed Request',
    500 => 'Server Error: Internal Server Error',
    501 => 'Server Error: Not Implemented',
    502 => 'Server Error: Bad Gateway',
    503 => 'Server Error: Service Unavailable',
    504 => 'Server Error: Gateway Timeout',
    505 => 'Server Error: HTTP Version Not Supported',
    506 => 'Server Error: Variant Also Negotiates',
    507 => 'Server Error: Insufficient Storage',
    508 => 'Server Error: Loop Detected',
    509 => 'Server Error: Bandwidth Limit Exceeded',
    510 => 'Server Error: Not Extended',
    511 => 'Server Error: Network Authentication Required',
    598 => 'Server Error: Network read timeout error',
    599 => 'Server Error: Network connect timeout error',
);

$pluginStatus = array("1" => "Active", "2" => "Passive");
$postStatus = array("publish", "edit", "trash");

?>
<div id="fi" class="content-area">

    <div class="pc">

        <div class="pw50">

            <h1 class="title"><?php echo esc_attr_e("Settings", "fast-index") ?></h1>

            <div class="form">

                <form method="post" class="settingsForm" enctype="multipart/form-data">

                    <table class="form-table" role="presentation">
                        <tbody>

                        <tr>
                            <td scope="row">
                                <b><?php echo esc_attr_e("Status", "fast-index") ?></b> <br>
                                <small><?php echo esc_attr_e("Important choice for the plugin work", "fast-index") ?></small>
                            </td>
                            <td>
                                <select name="fast_index_options[status]">
                                    <?php foreach ($pluginStatus as $key => $value) { ?>
                                        <option <?php echo esc_attr($key) == esc_attr($options['status']) ? "selected" : "" ?>
                                                value="<?php echo esc_attr($key) ?>"><?php echo esc_attr_e($value) ?></option>
                                    <?php } ?>
                                </select>

                            </td>
                        </tr>

                        <tr>
                            <td scope="row">
                                <b><?php echo esc_attr_e("Post Types", "fast-index") ?></b> <br>
                                <small><?php echo esc_attr_e("Select minimum one option", "fast-index") ?></small>
                            </td>
                            <td>
                                <?php foreach ($this->postTypes() as $value) {

                                    $canSelectable = true;
                                    if ($this->canI == false) {
                                        if (esc_attr($value['name']) == "post") {
                                            $canSelectable = true;
                                            $options['post_type'][esc_attr($value['name'])] = "1";
                                        } else {
                                            $canSelectable = false;
                                            $options['post_type'][esc_attr($value['name'])] = "";
                                        }
                                    }

                                    if (is_array($options['post_status'])) {
                                        $tmpOpt = @$options['post_type'][esc_attr($value['name'])];
                                    } else {
                                        $tmpOpt = "";
                                    }

                                    ?>
                                    <label <?php echo $canSelectable == false ? 'class="licenceAlert"' : "" ?>
                                            style="margin-right: 25px; margin-bottom: 15px; display: inline-block;">
                                        <input <?php echo $canSelectable == false ? 'readonly="true"' : "" ?>
                                                name="fast_index_options[post_type][<?php echo esc_attr($value['name']) ?>]" <?php echo esc_attr($tmpOpt) == "1" ? "checked" : "" ?>
                                                type="checkbox" value="1"/> <?php echo esc_attr($value['label']) ?>
                                    </label>
                                <?php } ?>
                            </td>
                        </tr>


                        <tr>
                            <td scope="row">
                                <b><?php echo esc_attr_e("Daily Old Content Post?", "fast-index") ?></b> <br>
                                <small><?php echo esc_attr_e("How many old contents should be sent per day?", "fast-index") ?></small>
                            </td>
                            <td>
                                <input <?php echo $this->canI == false ? 'readonly="true"' : "" ?>
                                        class="regular-text <?php echo $this->canI == false ? 'licenceAlert" disabled' : "" ?>"
                                        name="<?php echo $this->canI == false ? "" : "fast_index_options[old_post_number]" ?>"
                                        type="text"
                                        value="<?php echo intval(esc_attr($options['old_post_number'])) ?>"/>
                            </td>
                        </tr>


                        <tr>
                            <td scope="row">
                                <b><?php echo esc_attr_e("Post Status", "fast-index") ?></b> <br>
                                <small><?php echo esc_attr_e("Which status happen should content be sent?", "fast-index") ?></small>
                            </td>
                            <td>
                                <?php

                                foreach ($postStatus as $value) {
                                    $value = esc_attr($value);

                                    $canSelectable = true;
                                    if ($this->canI == false) {
                                        if (esc_attr($value) == "publish") {
                                            $canSelectable = true;
                                            $options['post_status'][esc_attr($value)] = "1";
                                        } else {
                                            $canSelectable = false;
                                            $options['post_status'][esc_attr($value)] = "";
                                        }
                                    }

                                    if (is_array($options['post_status'])) {
                                        $tmpOpt = @$options['post_status'][esc_attr($value)];
                                    } else {
                                        $tmpOpt = "";
                                    }
                                    ?>
                                    <label <?php echo $canSelectable == false ? 'class="licenceAlert"' : "" ?>
                                            style="margin-right: 25px; margin-bottom: 15px; display: inline-block;">
                                        <input <?php echo $canSelectable == false ? 'readonly class="licenceAlert"' : "" ?>
                                                name="fast_index_options[post_status][<?php echo esc_attr($value) ?>]" <?php echo esc_attr($tmpOpt) == "1" ? "checked" : "" ?>
                                                type="checkbox" value="1"/> <?php echo esc_attr($value) ?>
                                    </label>
                                <?php } ?>
                            </td>
                        </tr>


                        <tr>
                            <td scope="row" colspan="2">
                                <b><?php echo esc_attr_e("Exclude Categories", "fast-index") ?></b> <br>
                                <small><?php echo esc_attr_e("Select which you want to exclude", "fast-index") ?></small>
                            </td>

                        </tr>

                        <tr>
                            <td colspan="2">
                                <?php
                                foreach ($categories as $value) {
                                    $termId = $value->term_id;
                                    $label = $value->name;

                                    if ($this->canI == false) {
                                        $canSelectable = false;
                                    } else {
                                        $canSelectable = true;
                                    }

                                    if (is_array($options['post_status'])) {
                                        $tmpOpt = @$options['exclude_category'][esc_attr($termId)];
                                    } else {
                                        $tmpOpt = "";
                                    }

                                    ?>
                                    <label <?php echo $canSelectable == false ? 'class="licenceAlert"' : "" ?>
                                            style="margin-right: 25px; margin-bottom: 15px;">
                                        <input <?php echo $canSelectable == false ? 'readonly class="licenceAlert"' : "" ?>
                                                name="fast_index_options[exclude_category][<?php echo esc_attr($termId) ?>]" <?php echo esc_attr($tmpOpt) != "" ? "checked" : "" ?>
                                                type="checkbox" value="<?= $termId ?>"/> <?php echo esc_attr($label) ?>
                                    </label>
                                <?php } ?>
                            </td>
                        </tr>

                        <tr>

                            <td colspan="2">
                                <a target="_blank"
                                   href="https://wordpress.org/support/plugin/fast-index/reviews/?filter=5#postform"><?php echo esc_attr_e('Rate Us on WORDPRESS', "fast-index") ?></a>
                                &nbsp;
                            </td>
                        </tr>


                        <tr>

                            <td colspan="2">
                                &nbsp;
                            </td>
                        </tr>

                        <tr>
                            <td colspan="2">
                                <h1 class="title"><?php echo esc_attr_e("Upload Google Service Account Json", "fast-index") ?></h1>
                            </td>
                        </tr>

                        <?php
                        if (is_array($jsonFiles)) { ?>

                            <?php foreach ($jsonFiles as $key => $item) {
                                ?>
                                <tr class="trBorder">
                                    <td scope="row" class="insideTd">
                                        <small><b><a href="<?= $item['file'] ?>"><?php echo esc_attr($item['mail']); ?></a></b></small>
                                    </td>
                                    <td class="insideTd">
                                        <table width="100%" class="subTable">
                                            <td class="insideTd"
                                                width="60%"><?php echo esc_attr($item['status']) . " : " . esc_attr($httpStatusCodes[esc_attr(esc_attr($item['status']))]); ?></td>
                                            <td class="insideTd" width="40%"><a href="#"
                                                                                onclick=" jQuery('.deleteJson').val('<?php echo esc_attr($key); ?>'); jQuery('.settingsSubmitButton').click(); return false;"><?php echo esc_attr_e("Delete", "fast-index") ?></a>
                                            </td>
                                        </table>

                                    </td>
                                </tr>
                            <?php } ?>

                            <tr>
                                <td colspan="2">
                                    &nbsp;
                                </td>
                            </tr>

                        <?php } ?>

                        <tr>
                            <td scope="row"><b><?php echo esc_attr_e("Choose Json File/s", "fast-index") ?></b></td>
                            <td>
                                <input class="jsonFileUpload" accept=".json" type="file"
                                       name="jsons[]" <?php echo $this->canI == false ? "" : "multiple" ?>
                                       value="<?php echo esc_attr_e("Choose Json File/s", "fast-index") ?>"/>
                            </td>
                        </tr>

                        <?php if ($this->canI == false) { ?>
                            <tr>

                                <td colspan="2">
                                    <div class="licenceAlert laBg">
                                        <?php
                                        echo esc_attr_e("If you wanna upload multiple and more service account", "fast-index");
                                        echo "<br>";
                                        echo "<b>";
                                        echo esc_attr_e("Please upgrade to premium", "fast-index");
                                        echo "</b>";
                                        ?>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>


                        <tr>
                            <td scope="row">&nbsp;</td>
                            <td>
                                <input name="submit" class="button button-primary settingsSubmitButton" type="submit"
                                       value="<?php esc_attr_e('Save', "fast-index"); ?>"/>
                            </td>
                        </tr>


                        </tbody>
                    </table>

                    <input type="hidden" class="deleteJson" name="fast_index_options[delete_json]"/>


                </form>

            </div>


        </div>

        <div class="pw50">

            <h1 class="title"><?php echo esc_attr_e("Guideline", "fast-index") ?></h1>

            <div>

                <h3>1- <?php echo esc_attr_e("What means 200, 429, 403 and 401 codes?", "fast-index") ?></h3>
                <p><b>Code 200</b>; <?php echo esc_attr_e("It's means working and ready", "fast-index") ?><br/>
                    <b>Code
                        429</b>; <?php echo esc_attr_e("Too many requests are thrown and when it's 24 hours it starts again", "fast-index") ?>
                    <br/>
                    <b>Code
                        401</b>; <?php echo esc_attr_e("It means that the service account you have installed is not authorized or authorized as 'owner' in your webmaster tools account", "fast-index") ?>
                    <b>Code
                        403</b>; <?php echo esc_attr_e("It means the request page is forbidden. Check your site url. Htpps or Http it's important.", "fast-index") ?>
                </p>
                <p>
                    <b><?php echo esc_attr_e("Note : If you see 200 or 429 don't do anything. If you see 401 or 4xx codes, check your webmaster tools owners", "fast-index") ?> </b>
                </p>
                <hr/>
                <br/>
                <h3>2- <?php echo esc_attr_e("Settings", "fast-index") ?></h3>
                <p>
                    <b><?php echo esc_attr_e("Status", "fast-index") ?></b>: <?php echo esc_attr_e("If you don't use set as passive", "fast-index") ?>
                    <br/>
                    <b><?php echo esc_attr_e("Post Types", "fast-index") ?></b>: <?php echo esc_attr_e("Define the when you make post action which one post types will send to google. If you add new post type or added from plugin it will be shown in here", "fast-index") ?>
                    <br/>
                    <b><?php echo esc_attr_e("Daily Old Content Post", "fast-index") ?></b>: <?php echo esc_attr_e("If you wanna sent to google your old posts type the your daily limit. Every service account has daily 200 limit and you have to split your limits daily new post and old posts", "fast-index") ?>
                    <br>
                    <b><?php echo esc_attr_e("Post Status", "fast-index") ?></b>: <?php echo esc_attr_e("It's means which post status trigger the this plugin", "fast-index") ?>
                </p>
                <hr/>
                <br>
                <h3>3- <?php echo esc_attr_e("Is it legal?", "fast-index") ?></h3>
                <p><?php echo esc_attr_e("Totally is legal. It's google service and working with google API. If you upload too much service account it's can be defined a spam. Just watch out for this", "fast-index") ?></p>
                <hr/>
                <br/>
                <h3>
                    4- <?php echo esc_attr_e("How work wordpress Cron Job ( Daily Old Content Post )?", "fast-index") ?></h3>
                <p><?php echo esc_attr_e("The task list is triggered when someone logs into the site at or after the specified hours. These tasks will never be triggered if no one accesses your site. If no one visits your site during the day, log in to your site for once and the task list will be triggered automatically", "fast-index") ?></p>
                <hr/>
                <br/>
                <h3>4- <?php echo esc_attr_e("Mass Service Account creating and upload", "fast-index") ?></h3>
                <p>
                    <b>Not</b>: <?php echo esc_attr_e("1 Google account can create minimum 12 google cloud projects. Every google cloud project can enable Indexing API Service and every services has 200 daily limit. It's means you can send 2400 url to google. If you do same steps with your another google account you will get more 2400 limit and you 4800 url to google daily", "fast-index") ?>
                </p>
                <p>
                    <b>Step 1</b>: <?php echo esc_attr_e("Go Link", "fast-index") ?> <a target="_blank"
                                                                                        href="https://console.cloud.google.com/">https://console.cloud.google.com/</a>
                    <br>
                    <b>Step 2</b> : <?php echo esc_attr_e("Create Project and Select", "fast-index") ?><br>
                    <b>Step 3</b>
                    : <?php echo esc_attr_e("Create Service Account and make authorized you created email on service account", "fast-index") ?>
                    <br>
                    <b>Step 4</b> : <?php echo esc_attr_e("Add as owner on your webmaster tools", "fast-index") ?><br>
                    <b>Step 5</b>
                    : <?php echo esc_attr_e("Go your wordpress admin dashboard and open Fast Index settings page and upload your service account JSON", "fast-index") ?>
                    <br>
                </p>
                <p><?php echo esc_attr_e("Watch Video", "fast-index") ?> : <a target="_blank"
                                                                              href="https://youtu.be/RsJA66b5884">https://youtu.be/RsJA66b5884</a>
                </p>
                <p>
                    <iframe style="padding: 10px; border: 1px dotted #666; border-radius: 3px;" width="560" height="315"
                            src="https://www.youtube.com/embed/RsJA66b5884" title="YouTube video player" frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            allowfullscreen></iframe>
                </p>

            </div>

        </div>

        <div class="fiClear"></div>

    </div>

</div>

