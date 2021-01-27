<?php

namespace INTEGRANET_COTIZACION;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');
include_once( __DIR__.'/SuperRest.php');

use \akou\Utils;
use \akou\DBTable;

class LoginController extends SuperRest
{
	function get()
	{
        App::connect();

		if( $_GET['type'] === 'facebook' )
		{
			header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
			$fb = new Facebook\Facebook([
				'app_id' => '{app-id}', // Replace {app-id} with your app id
				'app_secret' => '{app-secret}',
				'default_graph_version' => 'v3.2',
			]);

			$loginUrl = $helper->getLoginUrl('https://example.com/fb-callback.php', $permissions);
			return $this->sendStatus(200)->json( array('login_url'=>$login_url) );
		}

		return $team->load()
			? $this->sendStatus(200)->json( $team->toArray() )
			: $this->sendStatus(404)->json( Array('error'=>'Not Found') );
	}

	function post()
	{
		$this->setAllowHeader();
        app::connect();
		session_start();

		$usuario = new usuario();
		$params = $this->getMethodParams();

		$usuario->assignFromArray($params,'usuario','contrasena');
		$usuario->setWhereString();

		if( !$usuario->load() )
		{
			 return $this->sendStatus(404)->json( Array('error'=>'El Usuario no existe o la contraseña es incorrecta','query'=>$usuario->getLastQuery()) );
		}

		$sesion				= new sesion();
		$sesion->id			= app::getRandomString(16);
		$sesion->id_usuario = $usuario->id;
		$sesion->estatus	= 'SESION_ACTIVA';
		$sesion->fecha_creacion = date('Y-m-d h:s:i');


		if( !$sesion->insertDb() )
		{
			return $this->sendStatus(400)->json(array("error"=>"Ocurrio un error por favor intente de nuevo",'debug'=>$sesion->getLastQuery()));
		}

		$response = array( "usuario"=> $usuario->toArrayExclude('password'),"sesion"=>$sesion->toArray());
		// $pacientes = array();

		// if( $usuario->tipo == 'PACIENTE' )
		// {
		// 	$pacientes = DBTable::getArrayFromQuery('SELECT * FROM paciente WHERE id_usuario = '.$sesion->id_usuario );
		// 	$response['pacientes'] = $pacientes;
		// }


		return $this->sendStatus(200)->json( $response );
	}
}

$l = new LoginController();
$l->execute();
