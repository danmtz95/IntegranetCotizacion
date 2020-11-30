<?php

namespace TIPS;

include_once( __DIR__.'/app.php' );

use \akou\Utils;
use \akou\DBTable;

class LoguoutController extends SuperRest
{
	function get()
	{
		session_start();
		$this->setAllowHeader();
        App::connect();
		$_SESSION['user_id'] = '';
		unset($_SESSION['user_id']);
	}
}

$l = new LoguoutController();
$l->execute();
