<?php 
	
	/*
	**
	**	Anonymous chat (example)
	**
	*/
	
	require_once('../src/websocket.class.php');
	
	error_reporting(E_ERROR);
	set_time_limit(0);
	ob_implicit_flush();
	
	$GLOBALS['socket'] = new WebSocket('tcp://', '127.0.0.1', '7777');
	$GLOBALS['socket']->setOutput('ws-log.txt');
	
	$GLOBALS['socket']->handler = function($connection, $data)
	{
		$GLOBALS['socket']->sendAll('<b>Anonymous</b>: ' . $data);
	};
	
	$GLOBALS['socket']->runServer();