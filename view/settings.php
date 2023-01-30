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

            <h1 class="title">Plugin Settings</h1>

            <div class="form">

                <form method="post" class="settingsForm" enctype="multipart/form-data">


                    <table class="form-table" role="presentation">
                        <tbody>

                        <tr>
                            <td scope="row">
                                <b>Plugin Status</b> <br>
                                <small>Important choice for the plugin work</small>
                            </td>
                            <td>
                                <select name="fast_index_options[status]">
                                    <?php foreach ($pluginStatus as $key => $value) { ?>
                                        <option <?php echo $key==$options['status']?"selected":""?> value="<?php echo $key?>"><?php echo $value?></option>
                                    <?}?>
                                </select>

                            </td>
                        </tr>

                        <tr>
                            <td scope="row">
                                <b>Post Types</b> <br>
                                <small>Select minimum one option</small>
                            </td>
                            <td>
                                <?php foreach ($this->postTypes() as $value) {

                                    $canSelectable = true;
                                    if($this->canI ==false) {
                                    if($value['name'] =="post") {
                                        $canSelectable = true;
                                    } else {
                                        $canSelectable = false;
                                    }
                                    }
                                    ?>
                                    <label style="margin-right: 25px; margin-bottom: 15px;">
                                        <input <?php echo $canSelectable==false?'readonly="true"':""?>
                                            name="fast_index_options[post_type][<?php echo $value['name'] ?>]" <?php echo $options['post_type'][$value['name']] == "1" ? "checked" : "" ?>
                                            type="checkbox" value="1"/> <?php echo $value['label'] ?>
                                    </label>
                                <?php } ?>
                            </td>
                        </tr>


                        <tr>
                            <td scope="row">
                                <b>Daily Old Content Post?</b> <br>
                                <small>How many old contents should be sent per day?</small>
                            </td>
                            <td>
                                <input <?php echo $this->canI ==false?'readonly="true" disabled':""?> class="regular-text" name="<?php echo $this->canI ==false?"":"fast_index_options[old_post_number]"?>" type="text"
                                       value="<?php echo intval($options['old_post_number']) ?>"/>
                            </td>
                        </tr>


                        <tr>
                            <td scope="row">
                                <b>Post Status</b> <br>
                                <small>Which status happen should content be sent?</small>
                            </td>
                            <td>
                                <?php foreach ($postStatus as $value) {

                                    $canSelectable = true;
                                    if($this->canI ==false) {
                                        if($value =="publish") {
                                            $canSelectable = true;
                                        } else {
                                            $canSelectable = false;
                                        }
                                    }

                                    ?>
                                    <label style="margin-right: 25px; margin-bottom: 15px;">
                                        <input <?php echo $canSelectable==false?'readonly="true"':""?>
                                            name="fast_index_options[post_status][<?php echo $value ?>]" <?php echo $options['post_status'][$value] == "1" ? "checked" : "" ?>
                                            type="checkbox" value="1"/> <?php echo $value ?>
                                    </label>
                                <?php } ?>
                            </td>
                        </tr>


                        <tr>

                            <td colspan="2">
                                &nbsp;
                            </td>
                        </tr>

                        <tr>
                            <td colspan="2">
                                <h1 class="title">Upload Google Service Account Json</h1>
                            </td>
                        </tr>

                        <?php
                        if(is_array($jsonFiles)) { ?>

                       <?php foreach($jsonFiles as $key => $item) {?>
                        <tr class="trBorder">
                            <td scope="row" class="insideTd">
                                <small><b><a target="_blank" href="<?php echo $item['file'];?>"><?php echo $item['mail'];?></a></b></small>
                            </td>
                            <td class="insideTd">
                                <table width="100%" class="subTable">
                                    <td class="insideTd" width="60%"><?php echo $item['status']." : ".$httpStatusCodes[$item['status']];?></td>
                                    <td class="insideTd" width="40%"><a href="#" onclick=" jQuery('.deleteJson').val('<?php echo $key;?>'); jQuery('.settingsSubmitButton').click(); return false;">Delete</a></td>
                                </table>

                            </td>
                        </tr>
                        <?php }?>

                            <tr>
                                <td colspan="2">
                                    &nbsp;
                                </td>
                            </tr>

                        <?php }?>

                        <tr>
                            <td scope="row"><b>Choose Json File/s</b></td>
                            <td>
                                <input class="jsonFileUpload" accept=".json" type="file" name="jsons[]"  <?php echo $this->canI ==false?"":"multiple"?>
                                       value="Choose Json File/s"/>
                            </td>
                        </tr>

                        <tr>
                            <td scope="row">&nbsp; </td>
                            <td>
                                <?php echo $this->canI ==false?"If you wanna upload multiple and more service account<br><b>please upgrade to premium</b>":""?>
                            </td>
                        </tr>


                        <tr>
                            <td scope="row">&nbsp;</td>
                            <td>
                                <input name="submit" class="button button-primary settingsSubmitButton" type="submit"
                                       value="<?php esc_attr_e('Save'); ?>"/>
                            </td>
                        </tr>


                        </tbody>
                    </table>

                    <input type="hidden" class="deleteJson" name="fast_index_options[delete_json]"/>


                </form>

            </div>


        </div>

        <div class="pw50">

            <h1 class="title">Guideline</h1>

            <div>

                <h3>1- What means 200, 429 and 401 codes?</h3>
                <p><b>Code 200</b>; It's means working and ready<br/>
                   <b>Code 429</b>; Too many requests are thrown and when it's 24 hours it starts again <br/>
                   <b>Code 401</b>; It means that the service account you have installed is not authorized or authorized as "owner" in your webmaster tools account</p>
                <p><b>Note : If you see 200 or 429 don't do anything. If you see 401 or 4xx codes, check your webmaster tools owners </b></p>
                <hr/>
                <br/>
                <h3>2- Settings</h3>
                <p>
                    <b>Plugin Status</b>: If you don't use set as passive <br/>
                    <b>Post Types</b>: Define the when you make post action which one post types will send to google. If you add new post type or added from plugin it will be shown in here
                    <br/>
                    <b>Daily Old Content Post</b>: If you wanna sent to google your old posts type the your daily limit. Every service account has daily 200 limit and you have to split your limits daily new post and old posts <br>
                    <b>Post Status</b>: It's means which post status trigger the this plugin
                </p>
                <hr/>
                <br>
                <h3>3- Is it legal?</h3>
                <p>Totally is legal. It's google service and working with google API. If you upload too much service account it's can be defined a spam. Just watch out for this</p>
                <hr/>
                <br/>
                <h3>4- How work wordpress Cron Job ( Daily Old Content Post )?</h3>
                <p>The task list is triggered when someone logs into the site at or after the specified hours. These tasks will never be triggered if no one accesses your site. If no one visits your site during the day, log in to your site for once and the task list will be triggered automatically.</p>
                <hr/>
                <br/>
                <h3>4- Mass Service Account creating and upload</h3>
                <p>
                    <b>Not</b>: 1 Google account can create minimum 12 google cloud projects. Every google cloud project can enable Indexing API Service and every services has 200 daily limit. It's means you can send 2400 url to google. If you do same steps with your another google account you will get more 2400 limit and you 4800 url to google daily.
                </p>
                <p>
                    <b>Step 1</b>: Go Link <a target="_blank" href="https://console.cloud.google.com/">https://console.cloud.google.com/</a> <br>
                    <b>Step 2</b> : Create Project and Select<br>
                    <b>Step 3</b> : Create Service Account and make authorized you created email on service account<br>
                    <b>Step 4</b> : Add as owner on your webmaster tools<br>
                    <b>Step 5</b> : Go your wordpress admin dashboard and open Fast Index settings page and upload your service account JSON<br>
                </p>
                <p>Watch Video : <a target="_blank" href="https://youtu.be/RsJA66b5884">https://youtu.be/RsJA66b5884</a></p>
                <p><iframe width="560" height="315" src="https://www.youtube.com/embed/RsJA66b5884" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></p>

            </div>

        </div>

        <div class="fiClear"></div>

    </div>

</div>


