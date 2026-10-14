<?php
require_once 'indexingApi/vendor/autoload.php';

class FastIndex_IndexingApi
{

    public $serviceAccounts = array();
    private $fastIndex;

    function __construct()
    {
        $this->fastIndex = new FastIndex();
        $this->serviceAccounts = $this->fastIndex->getServiceAccounts();
        $this->checkAndFilterServiceAccount();
    }


    function checkAndFilterServiceAccount()
    {

        foreach ($this->serviceAccounts as $key => $value) {
            $lastStatus = $this->fastIndex->getServiceAccountStatus($key);
            if($lastStatus ==200) { continue; }
            if ($lastStatus != "") {
               unset($this->serviceAccounts[$key]);
            }
        }

    }

    function setPassive($account, $status)
    {

        /* if we in loop, we have to delete the account from memory data */
        foreach ($this->serviceAccounts as $key => $val) {
            if ($key == $account) {
                unset($this->serviceAccounts[$key]);
            }
        }

        $this->fastIndex->setServiceAccountStatus($account, $status);

    }


    function getRandomServiceAccount()
    {

        if(count($this->serviceAccounts)<=0) {
            return false;
        }

        shuffle($this->serviceAccounts);

        $current = current($this->serviceAccounts);
        $current['key'] = md5($current['mail']);
        return $current;
    }

    function sendRequest($url)
    {

        $json = $this->getRandomServiceAccount();

        if ($json == false) {
            return false;
        }

        try {
            $client = new Google_Client();

            $phpModules = get_loaded_extensions();

            if(!in_array('openssl',$phpModules)) {

            $guzzleClient = new \GuzzleHttp\Client(array('curl' =>
                array(
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,

                )
            ));

            $client->setHttpClient($guzzleClient);

            }

            $client->setAuthConfig($json['file']);
            $client->addScope('https://www.googleapis.com/auth/indexing');

            $httpClient = $client->authorize();
            $endpoint = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
            $content = '{  "url": "' . $url . '",  "type": "URL_UPDATED"}';

            $response = $httpClient->post($endpoint, ['body' => $content]);
		
            $statusCode = $response->getStatusCode();
        } catch(Exception $e) {
           $statusCode = $e->getCode();
        }


        if ($statusCode != 200) {
            $this->setPassive($json['key'], $statusCode);
           /* return $this->sendRequest($url); */
        } else {
            $this->fastIndex->setServiceAccountStatus($json['key'], 200);
        }

        return $statusCode;

    }




}


?>