<?php
	
	/*
	**	PHP universal web-socket server 
	**	
	**	Written by Boris Stelmah (aka Mike Chip), 2015
	**  License: MIT
	**	
	**	To learn how to work with server, visit wiki: https://github.com/mikechip/php-websockets/wiki
	**
	** Before starting, I recommend to set system timelimit to 0 and not to use OB:
	**	error_reporting(E_ALL);
	**	set_time_limit(0);
	**	ob_implicit_flush();
	**	ignore_user_abort(true);
	**
	**		Notice: functions named handshake, encode and decode are not written by myself and I cant say exactly whose are they...
	**
	*/
	
	class WebSocket
	{
		
		protected $timelimit = 0;
		protected $starttime = 0;
		protected $protocol = 'tcp://';
		protected $ip;
		protected $port;
		protected $connection;
		protected $connects;
		
		var $handler = null;
		var $output = null;
		var $hearthbeat = null;
		
		function __construct($protocol = 'tcp://', $ip = '127.0.0.1', $port = '7777')
		{
			$this->protocol = $protocol;
			$this->ip = $ip;
			$this->port = $port;
		}
		
		function __destruct()
		{
			$this->close();
		}
		
		function close()
		{
			fclose($this->connection);
		}
		
		function log($message)
		{
			$message = '[' . date('r') . '] ' . $message . '
			';
			print($message);
			if($this->output != null)
			{
				return fwrite($this->output, $message);
			}
			return $message;
		}
		
		function setOutput($file)
		{
			$this->output = fopen($file, 'a');
		}
		
		function runServer()
		{
			$this->connection = stream_socket_server($this->protocol . $this->ip . ':' . $this->port, $errno, $errstr);
			
			if ( !$this->connection ) {
				$this->log("Cannot run server: " .$errstr. "(" .$errno. ")");
				return false;
			}
			
			$this->connects = array();
			$this->starttime = time();
			
			while (true) 
			{
				$this->log("Waiting for connections");

				$read = $this->connects;
				$read[] = $this->connection;
				$write = $except = null;

				if (!stream_select($read, $write, $except, null)) 
				{
					break;
				}
				
				if(is_callable($this->hearthbeat))
				{
					$this->hearthbeat($this->connects);
				}
				
				if (in_array($this->connection, $read)) 
				{
			
					if (($connect = stream_socket_accept($this->connection, -1)) && $info = $this->handshake($connect)) 
					{
				
						$this->log("New connection: ".$connect.", info=".$info.". Accepted.");            

						$this->connects[] = $connect;
						
					}
					
					unset($read[ array_search($this->connection, $read) ]);
					
				}

				foreach($read as $connect) 
				{
				
					$data = fread($connect, 100000);

					if (!$data) 
					{ 
						$this->log("Connection closed");    
						fclose($connect);
						unset($connects[ array_search($connect, $connects) ]);
						$this->log("Closed successfully");    
						continue;
					}

					$this->getMessage($connect, $data);

				}

				if($timelimit > 0 && $starttime + $timelimit < time()) 
				{
						$this->log("Time limit. Closing server..."); 
						$this->close();
						exit();		
				}
			}
		}
		
		public static function decode($data){
			$unmaskedPayload = '';
			$decodedData = array();

			// estimate frame type:
			$firstByteBinary = sprintf('%08b', ord($data[0]));
			$secondByteBinary = sprintf('%08b', ord($data[1]));
			$opcode = bindec(substr($firstByteBinary, 4, 4));
			$isMasked = ($secondByteBinary[0] == '1') ? true : false;
			$payloadLength = ord($data[1]) & 127;

			// unmasked frame is received:
			if (!$isMasked) {
				return array('type' => '', 'payload' => '', 'error' => 'protocol error (1002)');
			}

			switch ($opcode) {
				// text frame:
				case 1:
					$decodedData['type'] = 'text';
					break;

				case 2:
					$decodedData['type'] = 'binary';
					break;

				// connection close frame:
				case 8:
					$decodedData['type'] = 'close';
					break;

				// ping frame:
				case 9:
					$decodedData['type'] = 'ping';
					break;

				// pong frame:
				case 10:
					$decodedData['type'] = 'pong';
					break;

				default:
					return array('type' => '', 'payload' => '', 'error' => 'unknown opcode (1003)');
			}

			if ($payloadLength === 126) {
				$mask = substr($data, 4, 4);
				$payloadOffset = 8;
				$dataLength = bindec(sprintf('%08b', ord($data[2])) . sprintf('%08b', ord($data[3]))) + $payloadOffset;
			} elseif ($payloadLength === 127) {
				$mask = substr($data, 10, 4);
				$payloadOffset = 14;
				$tmp = '';
				for ($i = 0; $i < 8; $i++) {
					$tmp .= sprintf('%08b', ord($data[$i + 2]));
				}
				$dataLength = bindec($tmp) + $payloadOffset;
				unset($tmp);
			} else {
				$mask = substr($data, 2, 4);
				$payloadOffset = 6;
				$dataLength = $payloadLength + $payloadOffset;
			}

			/**
			 * We have to check for large frames here. socket_recv cuts at 1024 bytes
			 * so if websocket-frame is > 1024 bytes we have to wait until whole
			 * data is transferd.
			 */
			if (strlen($data) < $dataLength) {
				return false;
			}

			if ($isMasked) {
				for ($i = $payloadOffset; $i < $dataLength; $i++) {
					$j = $i - $payloadOffset;
					if (isset($data[$i])) {
						$unmaskedPayload .= $data[$i] ^ $mask[$j % 4];
					}
				}
				$decodedData['payload'] = $unmaskedPayload;
			} else {
				$payloadOffset = $payloadOffset - 4;
				$decodedData['payload'] = substr($data, $payloadOffset);
			}

			return $decodedData;
		}
		
		public static function encode($payload, $type = 'text', $masked = false) {
			$frameHead = array();
			$payloadLength = strlen($payload);

			switch ($type) {
				case 'text':
					// first byte indicates FIN, Text-Frame (10000001):
					$frameHead[0] = 129;
					break;

				case 'close':
					// first byte indicates FIN, Close Frame(10001000):
					$frameHead[0] = 136;
					break;

				case 'ping':
					// first byte indicates FIN, Ping frame (10001001):
					$frameHead[0] = 137;
					break;

				case 'pong':
					// first byte indicates FIN, Pong frame (10001010):
					$frameHead[0] = 138;
					break;
			}

			// set mask and payload length (using 1, 3 or 9 bytes)
			if ($payloadLength > 65535) {
				$payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
				$frameHead[1] = ($masked === true) ? 255 : 127;
				for ($i = 0; $i < 8; $i++) {
					$frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
				}
				// most significant bit MUST be 0
				if ($frameHead[2] > 127) {
					return array('type' => '', 'payload' => '', 'error' => 'frame too large (1004)');
				}
			} elseif ($payloadLength > 125) {
				$payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
				$frameHead[1] = ($masked === true) ? 254 : 126;
				$frameHead[2] = bindec($payloadLengthBin[0]);
				$frameHead[3] = bindec($payloadLengthBin[1]);
			} else {
				$frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
			}

			// convert frame-head to string:
			foreach (array_keys($frameHead) as $i) {
				$frameHead[$i] = chr($frameHead[$i]);
			}
			if ($masked === true) {
				// generate a random mask:
				$mask = array();
				for ($i = 0; $i < 4; $i++) {
					$mask[$i] = chr(rand(0, 255));
				}

				$frameHead = array_merge($frameHead, $mask);
			}
			$frame = implode('', $frameHead);

			// append payload to frame:
			for ($i = 0; $i < $payloadLength; $i++) {
				$frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
			}

			return $frame;
		}
		
		function handshake($connect) {
			$info = array();

			$line = fgets($connect);
			$header = explode(' ', $line);
			$info['method'] = $header[0];
			$info['uri'] = $header[1];

			while ($line = rtrim(fgets($connect))) {
				if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
					$info[$matches[1]] = $matches[2];
				} else {
					break;
				}
			}

			$address = explode(':', stream_socket_get_name($connect, true));
			$info['ip'] = $address[0];
			$info['port'] = $address[1];

			if (empty($info['Sec-WebSocket-Key'])) {
				return false;
			}

			//отправляем заголовок согласно протоколу вебсокета
			$SecWebSocketAccept = base64_encode(pack('H*', sha1($info['Sec-WebSocket-Key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
			$upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
				"Upgrade: websocket\r\n" .
				"Connection: Upgrade\r\n" .
				"Sec-WebSocket-Accept:".$SecWebSocketAccept."\r\n\r\n";
			fwrite($connect, $upgrade);

			return $info;
		}
		
		function send($connection, $data)
		{
			fwrite($connection, self::encode($data));
		}
		
		function sendAll($data)
		{
			foreach($this->connects as $connection)
			{
				fwrite($connection, self::encode($data));
			}
		}
		
		protected function getMessage($connect, $data)
		{
			if(is_callable($this->handler))
			{
				return call_user_func($this->handler, $connect, self::decode($data)['payload']);
			}
			else
			{
				return null;
			}
		}
	}