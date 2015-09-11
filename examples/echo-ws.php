<?php 
	
	/*
	**	Echo websocket server (example)
	**
	**	This server returns the same answer as a query
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
		fwrite($connection, WebSocket::encode($data));
	};
	
	$socket->runServer();