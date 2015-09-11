<?php 
	
	/*
	**
	**	WebSocket server that receives commands (example)
	**
	**	Server gets commands and arguments using ; separator, and returns JSON\text to client
	**
	*/
	
	require_once('../src/websocket.class.php');
	
	error_reporting(E_ERROR);
	set_time_limit(0);
	ob_implicit_flush();
	
	$socket = new WebSocket('tcp://', '127.0.0.1', '7777');
	$socket->setOutput('ws-log.txt');
	
	$socket->handler = function($connection, $data)
	{
		$answer = '{}';
		$data = explode(';', $data);
		
		switch($data[0])
		{
		
			case 'test':
				$answer = 'Test OK';
				break;
				
			case 'echo':
				$answer = $data[1];
				break;
				
			case 'server':
				$answer = json_encode($_SERVER);
				break;
			
		}
		
		fwrite($connection, WebSocket::encode($answer));
		
	};
	
	$socket->runServer();