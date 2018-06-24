<?php

/*
	php-socket-simple-bootstrap-app
	 - Uses WebSocket for client and server communication
	 - Works with: ws_api.php
	
	Starting the server
	 - Open any browser and visit server.php once (this file). For example: http://127.0.0.1/chatbox/server.php
	   The page should appear to "never load", this is supposed to happen and means the server is running.
	
*/

date_default_timezone_set("UTC");

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

require 'mysql.php';
App::init();

// include the web sockets server script (the server is started at the far bottom of this file)
require 'ws_api.php';

// https://stackoverflow.com/a/15875555/1590519
function guidv4() {
	$data = openssl_random_pseudo_bytes(16);
	assert(strlen($data) == 16);
	$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
	$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
	return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

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
							'respond' => 'info',
							'info' => 'someone accessed the service'
						];
					} else {

						$respond = (object) [
							'respond' => 'init',
							'info' => long2ip($wsClients[$clientID][6])
						];

					}
					// echo $_SERVER['REMOTE_ADDR'];
					$respond = json_encode($respond);
					wsSend($k, $respond);
				}
				break;
			case 'init-cookie-user':

				// validate client given uuidv4 session string:
				// https://stackoverflow.com/questions/19989481/how-to-determine-if-a-string-is-a-valid-v4-uuid
				if (preg_match('/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i', $message->session) == false) {
					$respond = (object) [
						'respond' => 'join-attempt',
						'status' => 4,
						'info' => "Something went wrong"
					];
					$respond = json_encode($respond);
					wsSend($clientID, $respond);
					return;
				}

				$userID = MySql::select('o','
				SELECT user_id FROM user_session WHERE session LIKE %s LIMIT 1',
				$message->session)[0]->user_id;

				if ($userID == null) {
					$respond = (object) [
						'respond' => 'join-attempt',
						'status' => 5,
						'info' => "Something went wrong"
					];
					$respond = json_encode($respond);
					wsSend($clientID, $respond);
					return;
				}

				// necessary for logout:
				$wsClients[$clientID][12] = $message->session;

				// handle session expiration:
				$sessionMaxTime = MySql::select('o','
				SELECT session_expires FROM user_session WHERE session LIKE %s LIMIT 1',
				$message->session)[0]->session_expires;
				if (time() > $sessionMaxTime) { // expires after a day.
					App::logout($clientID, 7);
					return;
				}

				$respond = (object) [
					'respond' => 'join-attempt',
					'status' => 3,
					'info' => 'Welcome back'
				];
				$respond = json_encode($respond);
				wsSend($clientID, $respond);

				break;
			case 'logout':
				App::logout($clientID, 6);
				break;
			case 'join':

				// status:
				// 0 = error
				// 1 = registration successfull
				// 2 = password wrong
				// 3 = login successfull
				// 4 = invalid session string
				// 5 = unknown session string
				// 6 = logout requested
				// 7 = session expired
				// 9 = new login requested
				$status = 0;

				$infoString = '';

				$errors = array();
				$existingUserName = false;
				$passwordRight    = false;
				$inputValid       = true;

				$inputPassword = $message->pw;
				$inputUsername = $message->username;

				$userID = 0;

				$session = null;

				// if we receive a session:
				if ($message->session != null) {
					App::logout($clientID, 8);
					return;
				}

				// check username length:
				if ($inputUsername == '') {
					$errors['username'] = 'Invalid';
				} else if (strlen($inputUsername) > 255) {
					$errors['username'] = 'Max 255 characters';
				}

				// check username:
				$userID = MySql::select('o','
				SELECT id FROM user WHERE name LIKE %s LIMIT 1',
				$inputUsername)[0]->id;
				if ($userID != null) {
					$existingUserName = true;
				}

				// check password:
				if ($existingUserName) {
					$dbPassword = MySql::select('o', "SELECT pw FROM user WHERE name LIKE %s LIMIT 1", $inputUsername)[0]->pw;
					if (password_verify($inputPassword, $dbPassword)) {
						$passwordRight = true;
					}
				}

				// handle incorrect input:
				if (count($errors)) {
					foreach ($errors as $k => $val) {
						$infoString .= "$k: $val; ";
					}
					$inputValid = false;
				}

				// handle registration:
				if ($inputValid && $existingUserName == false) {
					$password = password_hash($inputPassword, PASSWORD_DEFAULT);
					$ip       = long2ip($wsClients[$clientID][6]);

					MySql::update("INSERT INTO user (id, name, pw, last_login, login_count, ip) VALUES (NULL, %s, %s, NOW(), 1, %s)", $inputUsername, $password, $ip);
					$status = 1;

					$existingUserName = true;
					$passwordRight = true;

					$userID = MySql::select('o','
					SELECT id FROM user WHERE name LIKE %s LIMIT 1',
					$inputUsername)[0]->id;
				}

				// handle login:
				if ($inputValid && $existingUserName == true) {
					if ($passwordRight == false) {
						$status = 2;
						$infoString = "Wrong password";
					} else {
						if ($status == 1) {
							$infoString = "User '$inputUsername' registrated";
						} else {
							$status = 3;
							$infoString = "Login successfull";
						}

						$session = guidv4();

						MySql::update("INSERT INTO user_session (id, session, session_expires, user_id) VALUES (NULL, %s, %s, %i)",
							$session,
							time() + (60 * 60 * 24), // expires after a day.
							$userID
						);

						$wsClients[$clientID][12] = $session;
					}
				}

				$respond = (object) [
					'respond' => 'join-attempt',
					'status' => $status,
					'info' => $infoString
				];
				if ($session != null) {
					$respond->session = $session;
				}
				$respond = json_encode($respond);
				wsSend($clientID, $respond);

				break;
			default:
				return;
		}
	}

}

class App {
	public static function init() {
		MySql::update("TRUNCATE TABLE user_session");
	}
	public static function logout($clientID, $status = 6) {
		global $wsClients;

		MySql::update("DELETE FROM user_session WHERE session = %s", $wsClients[$clientID][12]);

		$thisUsersConnections = array();
		foreach ($wsClients as $k => $client) {
			if ($wsClients[$clientID][12] == $wsClients[$k][12]) { // compare session.
				$thisUsersConnections[] = $k;
			}
		}
		foreach ($thisUsersConnections as $k => $con) {
			$wsClients[$con][12] = '';
			
			$respond = (object) [
				'respond' => 'logout',
				'status' => $status
			];
			$respond = json_encode($respond);
			wsSend($con, $respond);
		}
	}
}



// start the server
wsStartServer(CB_SERVER_BIND_HOST, CB_SERVER_BIND_PORT);

?>