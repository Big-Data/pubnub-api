<?php
require_once('PubnubAES.php');
/**
 * PubNub 3.3 Real-time Push Cloud API
 * @package Pubnub
 */
class Pubnub
{
    private $ORIGIN = 'pubsub.pubnub.com';
    private $PUBLISH_KEY = 'demo';
    private $SUBSCRIBE_KEY = 'demo';
    private $SECRET_KEY = false;
    private $CIPHER_KEY = '';
    private $SSL = false;
    private $SESSION_UUID = '';

    /**
     * Pubnub
     *
     * Init the Pubnub Client API
     *
     * @param string $publish_key required key to send messages.
     * @param string $subscribe_key required key to receive messages.
     * @param string $secret_key optional key to sign messages.
     * @param string $origin optional setting for cloud origin.
     * @param boolean $ssl required for 2048 bit encrypted messages.
     */


    function Pubnub(
        $publish_key = 'demo',
        $subscribe_key = 'demo',
        $secret_key = false,
        $cipher_key = false,
        $ssl = false,
        $origin = false
    )
    {

        $this->SESSION_UUID = $this->uuid();

        $this->PUBLISH_KEY = $publish_key;
        $this->SUBSCRIBE_KEY = $subscribe_key;
        $this->SECRET_KEY = $secret_key;

        if (!isBlank($cipher_key)) {
            $this->CIPHER_KEY = $cipher_key;
        }

        $this->SSL = $ssl;

        if ($origin)
            $this->ORIGIN = $origin;

        if ($ssl)
            $this->ORIGIN = 'https://' . $this->ORIGIN;
        else
            $this->ORIGIN = 'http://' . $this->ORIGIN;
    }


    /**
     * Publish
     *
     * Send a message to a channel.
     *
     * @param array $args with channel and message.
     * @return array success information.
     */

    function publish($args)
    {
        ## Fail if bad input.
        if (!($args['channel'] && $args['message'])) {
            echo('Missing Channel or Message');
            return false;
        }

        ## Capture User Input
        $channel = $args['channel'];
        $message_org = $args['message'];

        $message = $this->sendMessage($message_org);

        ## Generate String to Sign
        $string_to_sign = implode('/', array(
            $this->PUBLISH_KEY,
            $this->SUBSCRIBE_KEY,
            $this->SECRET_KEY,
            $channel,
            $message
        ));

        ## Sign Message
        $signature = $this->SECRET_KEY ? md5($string_to_sign) : '0';

        ## Send Message
        $publishResponse = $this->_request(array(
            'publish',
            $this->PUBLISH_KEY,
            $this->SUBSCRIBE_KEY,
            $signature,
            $channel,
            '0',
            $message
        ));

        if ($publishResponse == null)
            return array(0, "Error during publish.");
        else
            return $publishResponse;

    }

      public function sendMessage($message_org)

    {
        if ($this->CIPHER_KEY != false) {
            $message = json_encode(encrypt($message_org, $this->CIPHER_KEY));
        } else {
            $message = json_encode($message_org);
        }
        return $message;
    }

    function here_now($args)
    {
        if (!($args['channel'])) {
            echo('Missing Channel');
            return false;
        }

        ## Capture User Input
        $channel = $args['channel'];

        return $this->_request(array(
            'v2',
            'presence',
            'sub_key',
            $this->SUBSCRIBE_KEY,
            'channel',
            $channel
        ));
    }

    /**
     * Subscribe
     *
     * This is BLOCKING.
     * Listen for a message on a channel.
     *
     * @param array $args with channel and message.
     * @return mixed false on fail, array on success.
     */
    function subscribe($args, $presence = false)
    {
        ## Capture User Input
        $channel = $args['channel'];
        $callback = $args['callback'];
        $timetoken = isset($args['timetoken']) ? $args['timetoken'] : '0';

        ## Fail if missing channel
        if (!$channel) {
            echo("Missing Channel.\n");
            return false;
        }

        ## Fail if missing callback
        if (!$callback) {
            echo("Missing Callback.\n");
            return false;
        }

        while (1) {

            try {
                ## Wait for Message
                $response = $this->_request(array(
                    'subscribe',
                    $this->SUBSCRIBE_KEY,
                    $channel,
                    '0',
                    $timetoken
                ));

                if ($response == null || $timetoken == null) {
                    $timetoken = $this->throwAndResetTimetoken($callback, "Bad server response.");
                    continue;
                }

                $messages = $response[0];
                $timetoken = $response[1];

                if (!count($messages)) {
                    continue;
                }

                $receivedMessages = $this->decodeAndDecrypt($messages, $presence);

                $returnArray = array($receivedMessages[0], $timetoken);

                if (!$callback($returnArray)) {
                    trigger_error("Callback error.", E_USER_ERROR);
                    $timetoken = $this->throwAndResetTimetoken($callback, "Callback is invalid.");
                    continue;
                }


            } catch (Exception $error) {
                $this->handleError($error, $args);
                $timetoken = $this->throwAndResetTimetoken($callback, "Unknown error.");
                continue;

            }
        }
    }

    public function throwAndResetTimetoken($callback, $errorMessage)
    {
        $callback(array(0, $errorMessage));
        $timetoken = "0";
        return $timetoken;
    }

    public function decodeAndDecrypt($messages, $presence = false)
    {
        $receivedMessages = array();

        foreach ($messages as $message) {
            if ($this->CIPHER_KEY && $presence == false) {
                $decryptedMessage = decrypt($message, $this->CIPHER_KEY);
                $message = urldecode($decryptedMessage);
            }
            array_push($receivedMessages, $message);
        }
        return $receivedMessages;
    }

    public function handleError($error, $args)
    {
        $errorMsg = 'Error on line ' . $error->getLine() . ' in ' . $error->getFile() . $error->getMessage();
        trigger_error($errorMsg, E_COMPILE_WARNING);


        sleep(1);
    }

    /**
     * Presence
     *
     * This is BLOCKING.
     * Listen for a message on a channel.
     *
     * @param array $args with channel and message.
     * @return mixed false on fail, array on success.
     */
    function presence($args)
    {
        ## Capture User Input
        $args['channel'] = ($args['channel'] . "-pnpres");
        $this->subscribe($args, true);
    }

    /**
     * History
     *
     * Load history from a channel.
     *
     * @param array $args with 'channel' and 'limit'.
     * @return mixed false on fail, array on success.
     */
    function history($args)
    {
        ## Capture User Input
        $limit = +$args['limit'] ? +$args['limit'] : 10;
        $channel = $args['channel'];

        ## Fail if bad input.
        if (!$channel) {
            echo('Missing Channel');
            return false;
        }

        ## Get History
        $response = $this->_request(array(
            'history',
            $this->SUBSCRIBE_KEY,
            $channel,
            '0',
            $limit
        ));
        ;

        $receivedMessages = $this->decodeAndDecrypt($response);

        return $receivedMessages;

    }

    /**
     * Time
     *
     * Timestamp from PubNub Cloud.
     *
     * @return int timestamp.
     */
    function time()
    {
        ## Get History
        $response = $this->_request(array(
            'time',
            '0'
        ));

        return $response[0];
    }

    /**
     * UUID
     *
     * UUID generator
     *
     * @return UUID
     */
    function uuid()
    {
        if (function_exists('com_create_guid') === true) {
            return trim(com_create_guid(), '{}');
        }

        return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
    }

    /**
     * Request URL
     *
     * @param array $request of url directories.
     * @return array from JSON response.
     */
    private function _request($request)
    {
        $request = array_map('Pubnub::_encode', $request);

        array_unshift($request, $this->ORIGIN);

        if (($request[1] === 'presence') || ($request[1] === 'subscribe')) {
            array_push($request, '?uuid=' . $this->SESSION_UUID);
        }

        $ctx = stream_context_create(array(
            'http' => array('timeout' => 300)
        ));

        $serverResponse = @file_get_contents(implode('/', $request), 0, $ctx);
        $decodedResponse = json_decode($serverResponse, true);

        return $decodedResponse;

    }

    /**
     * Encode
     *
     * @param string $part of url directories.
     * @return string encoded string.
     */
    private static function _encode($part)
    {
        $pieces = array_map('Pubnub::_encode_char', str_split($part));
        return implode('', $pieces);
    }

    /**
     * Encode Char
     *
     * @param string $char val.
     * @return string encoded char.
     */
    private static function _encode_char($char)
    {
        if (strpos(' ~`!@#$%^&*()+=[]\\{}|;\':",./<>?', $char) === false)
            return $char;
        else
            return rawurlencode($char);
    }
}

?>