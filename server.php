<?php

/*
	php-socket-simple-bootstrap-app
	 - Uses WebSocket for client and server communication
	 - Works with: ws_api.php
	
	Starting the server
	 - Open any browser and visit server.php once (this file). For example: http://127.0.0.1/chatbox/server.php
	   The page should appear to "never load", this is supposed to happen and means the server is running.
	
*/

// settings

// for other computers to connect, you will probably need to change this to your LAN IP or external IP,
// alternatively use: gethostbyaddr(gethostbyname($_SERVER['SERVER_NAME']))
define('CB_SERVER_BIND_HOST', '127.0.0.1');

// also change at top of main.js
define('CB_SERVER_BIND_PORT', 9300);

// also change at top of main.js
define('CB_MAX_USERNAME_LENGTH', 18);


// prevent the server from timing out
set_time_limit(0);

// include the web sockets server script (the server is started at the far bottom of this file)
require 'ws_api.php';

// when a client sends data to the server
function wsOnMessage($clientID, $message, $messageLength, $binary) {
	// check if message length is 0
	if ($messageLength == 0) {
		wsClose($clientID);
		return;
	}

	$message = json_decode($message);
	global $wsClients;

	if (isset($message->task)) {
		switch($message->task) {
			case 'init-user':

				foreach ($wsClients as $k => $client) {
					if ($k != $clientID) {
						$respond = (object) [
							'respond' => 'someone joined'
						];
					} else {
						$respond = (object) [
							'respond' => 'init',
							'info' => $_SERVER['REMOTE_ADDR']
						];
					}
					$respond = json_encode($respond);
					wsSend($k, $respond);
				}
				break;
			case 'join':

				$respond = (object) [
					'respond' => 'You would like to login? I see...'
				];
				$respond = json_encode($respond);
				wsSend($clientID, $respond);

				break;
			default:
				return;
		}
	}

}





// start the server
wsStartServer(CB_SERVER_BIND_HOST, CB_SERVER_BIND_PORT);

?>