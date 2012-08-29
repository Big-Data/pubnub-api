<?php

require_once('Pubnub.php');

## ---------------------------------------------------------------------------
## USAGE:
## ---------------------------------------------------------------------------
#
# php ./Pubnub-Unit-Test.php
# php ./Pubnub-Unit-Test.php [PUB-KEY] [SUB-KEY] [SECRET-KEY] [CIPHER-KEY] [USE SSL]
#
	
$publish_key   = isset($argv[1]) ? $argv[1] : 'demo';
$subscribe_key = isset($argv[2]) ? $argv[2] : 'demo';
$secret_key    = isset($argv[3]) ? $argv[3] : false;
$cipher_key	   = isset($argv[4]) ? $argv[4] : false;
$ssl_on        = isset($argv[4]);

## ---------------------------------------------------------------------------
## Create Pubnub Object
## ---------------------------------------------------------------------------
$pubnub = new Pubnub( $publish_key, $subscribe_key, $secret_key, $cipher_key, $ssl_on );

## ---------------------------------------------------------------------------
## Define Messaging Channel
## ---------------------------------------------------------------------------
$channel = "hello_world";

## ---------------------------------------------------------------------------
## PUBLISH TEST
## ---------------------------------------------------------------------------
$pubish_success = $pubnub->publish(array(
    'channel' => $channel,
    'message' => 'Pubnub Publish Test'
));
test( $pubish_success[0], 1, 'Published First Message' );

## ---------------------------------------------------------------------------
## HISTORY TEST
## ---------------------------------------------------------------------------
$history = $pubnub->history(array(
    'channel' => $channel,
    'limit'   => 1
));
test( count($history), 1, 'History With First Published Message' );
test( $history, '["Pubnub Publish Test"]', 'History Message Text == "Pubnub Publish Test"' );

## ---------------------------------------------------------------------------
## HERE_NOW TEST
## ---------------------------------------------------------------------------
$here_now = $pubnub->here_now(array(
    'channel' => $channel
));
test( count($here_now), 2, 'Here Now With Presence');

## ---------------------------------------------------------------------------
## TIMESTAMP TEST
## ---------------------------------------------------------------------------
$timestamp = $pubnub->time();
test( $timestamp, true, 'Timestamp API Test: ' . $timestamp );

## ---------------------------------------------------------------------------
## Test Presence
## ---------------------------------------------------------------------------
echo("\nWaiting for Presence message... Hit CTRL+C to finish.\n");

$pubnub->presence(array(
    'channel'  => $channel,
    'callback' => function($message) {
		echo('PASS: ');
		echo($message);
		echo "\r\n";
        return false;
    }
));

## ---------------------------------------------------------------------------
## Test Subscribe
## ---------------------------------------------------------------------------
echo("\nWaiting for Publish message... Hit CTRL+C to finish.\n");

$pubnub->subscribe(array(
    'channel'  => $channel,
    'callback' => function($message) {
        echo('PASS: ');
		echo($message);
		echo "\r\n";
        return true;
    }
));


## ---------------------------------------------------------------------------
## Unit Test Function
## ---------------------------------------------------------------------------
function test( $val1, $val2, $name ) {
    if ($val1 == $val2) echo('PASS: ');
    else                echo('FAIL: ');
    echo("$name\n");
}
?>