<?php

namespace APP;

include_once( __DIR__.'/akou/src/LoggableException.php' );
include_once( __DIR__.'/akou/src/Utils.php' );
include_once( __DIR__.'/akou/src/DBTable.php' );
include_once( __DIR__.'/akou/src/RestController.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php' );
include_once( __DIR__.'/akou/src/Image.php' );
include_once( __DIR__.'/SuperRest.php');


use \akou\DBTable;
use \akou\Utils;
use \akou\LoggableException;
use \akou\SystemException;
use \akou\ValidationException;
use \akou\RestController;
use \akou\NotFoundException;
use \akou\SessionException;

date_default_timezone_set('UTC');
//error_reporting(E_ERROR | E_PARSE);
Utils::$DEBUG 				= TRUE;
Utils::$DEBUG_VIA_ERROR_LOG	= TRUE;
#Utils::$LOG_CLASS			= '\bitacora';
#Utils::$LOG_CLASS_KEY_ATTR	= 'titulo';
#Utils::$LOG_CLASS_DATA_ATTR	= 'descripcion';

class App
{
	const DEFAULT_EMAIL					= '';
	const LIVE_DOMAIN_PROTOCOL			= 'http://';
	const LIVE_DOMAIN					= '';
	const DEBUG							= FALSE;
	const APP_SUBSCRIPTION_COST			= '20.00';

	public static $GENERIC_MESSAGE_ERROR	= 'Please verify details and try again later';
	public static $image_directory 		= './user_images';
	public static $attachment_directory = './user_files';
	public static $is_debug				= false;

	public static function connect()
	{
		DBTable::$_parse_data_types = TRUE;

		 if( !isset( $_SERVER['SERVER_ADDR'])  || $_SERVER['SERVER_ADDR'] =='127.0.0.1' )
		{
				$__user		 = 'root';
				$__password	 = 'asdf';
				$__db		 = 'integranet_cotizacion';
				$__host		 = '127.0.0.1';
				$__port		 = '3306';
				app::$image_directory = './user_images';
				app::$attachment_directory = './user_files';
				app::$is_debug	= true;
		}
		else
		{
				Utils::$DEBUG_VIA_ERROR_LOG	= FALSE;
				Utils::$LOG_LEVEL			= Utils::LOG_LEVEL_ERROR;
				Utils::$DEBUG				= FALSE;
				Utils::$DB_MAX_LOG_LEVEL	= Utils::LOG_LEVEL_ERROR;
				app::$is_debug	= false;

				$__user          = 'dbuser';
				$__password      = 'Soluciones01';
                $__db            = 'integranet_cotizacion';
                $__host          = '127.0.0.1';
                $__port          = '3306';

				app::$image_directory = '/var/www/html/Integranet_Cotizacion/api/user_images';
				app::$attachment_directory = '/var/www/html/Integranet_Cotizacion/api/user_files';
		}

		$mysqli = new \mysqli($__host, $__user, $__password, $__db, $__port );
		if( $mysqli->connect_errno )
		{
			echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
			exit();
		}

		date_default_timezone_set('UTC');

		$mysqli->query("SET NAMES 'utf8';");
		$mysqli->query("SET time_zone = '+0:00'");
		$mysqli->set_charset('utf8');


		DBTable::$connection							= $mysqli;
		DBTable::importDbSchema('APP');



		//	error_log(print_r(DBTable::getArrayFromQuery('select NOW()'),true));
	}

	static function getPasswordHash( $password, $timestamp )
	{
		return sha1($timestamp.$password.'sdfasdlfkjasld');
	}

	/* https://stackoverflow.com/questions/40582161/how-to-properly-use-bearer-tokens */

	static function getAuthorizationHeader(){
		$headers = null;
		if (isset($_SERVER['Authorization'])) {
			$headers = trim($_SERVER["Authorization"]);
		}
		else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
			$headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
		} elseif (function_exists('apache_request_headers')) {
			$requestHeaders = apache_request_headers();
			// Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
			$requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
			//print_r($requestHeaders);
			if (isset($requestHeaders['Authorization'])) {
				$headers = trim($requestHeaders['Authorization']);
			}
		}
		return $headers;
	}
	/**
	 * get access token from header
	 * */
	static function getBearerToken() {
		$headers = App::getAuthorizationHeader();
		// HEADER: Get the access token from the header
		if (!empty($headers)) {
			if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
				return $matches[1];
			}
		}
		return null;
	}

	static function getUserFromSession()
	{

		if( !empty( $_SESSION['id_usuario'] ) )
		{
			$usuario = new usuario();
			$usuario->id = $_SESSION['id_usuario'];
			if( $usuario->load() )
				return $usuario;

		}

		$token = App::getBearerToken();
		if( $token == null )
			return null;

		return App::getUserFromToken( $token );
	}

	static function getRandomString($length)
	{
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);

		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}

	static function getUserFromToken($token)
	{
		if( $token == null )
			return null;

		$usuario	= new usuario();
		$sesion		= new sesion();
		$sesion->id	= $token;
		$sesion->estatus = 'SESION_ACTIVA';
		$sesion->setWhereString();


		if( $sesion->load() )
		{
			$usuario = new usuario();
			$usuario->id = $sesion->id_usuario;

			if( $usuario->load(true) )
			{
				return $usuario;
			}
		}
		return null;
	}

	function reducirInventario($id_flor,$id_color,$calidad,$grado, $cantidad_tallos)
	{
		$cantidad_reducida = 0;

		$sql =  'SELECT *
			FROM inventario
			WHERE id_flor ="'.DBTable::escape('id_flor').'" AND id_color="'.DBTable::escape($id_color).'" AND grado = "'.DBTable::escape($grado).'"
			AND cantidad_de_tallos_en_existencia > 0
			ORDER BY fecha_recibido DESC';

		$inventario_array = inventario::getArrayFromQuery( $sql );
		$cantidad_restante = $cantidad_tallos;

		while($cantidad_restante>0 && count( $inventario_array ) > 0 )
		{
			$inventario = array_pop( $inventario_array );

			if( $inventario->tallos_en_existencia >= $cantidad_restante )
			{
				$cantidad_reducida += $cantidad_restante;
				$inventario->tallos_en_existencia -= $cantidad_restante;
				$cantidad_restante = 0;
				$inventario->update('tallos_en_existencia');
			}
			else
			{
				$cantidad_reducida += $inventario->tallos_en_existencia;
				$cantidad_restante	-= $inventario->tallos_en_existencia;
				$inventario->tallos_en_existencia = 0;
				$inventario->update('tallos_en_existencia');
			}
		}

		return $cantidad_reducida;
	}




	static function getCustomHttpReferer()
	{
		$return_var	 = FALSE;

		if( isset( $_SERVER['HTTP_REFERER'] ) )
		{
			//error_log('REFERER');
			$return_var = $_SERVER['HTTP_REFERER'];
		}
		else if( isset( $_SERVER['HTTP_ORIGIN'] ) )
		{
			//error_log('ORIGIN');
			$return_var = $_SERVER['HTTP_ORIGIN'];
		}
		else if( isset( $_SERVER['HTTP_HOST'] ) )
		{
			//error_log('HOST');
			$return_var = $_SERVER['HTTP_HOST'];
		}
		else if( isset( $GLOBALS['domain'] ) )
		{

			//error_log('DOMAIN');
			if
			(
				isset( $GLOBALS['domain']['scheme'] )
				&&
				isset( $GLOBALS['domain']['host'] )
				&&
				isset( $GLOBALS['domain']['path'] )
			)
			{
				$return_var = $GLOBALS['domain']['scheme'] .
				'://' .
				$GLOBALS['domain'].
				$GLOBALS['domain']['path'];
			}
			else
			{
			}
		}

		if( empty( $return_var ) )
		{
			if( !empty( $_GET['domain'] ) )
			{
				//error_log('GET domain');
				$return_var = 'http://'.$_GET['domain'];
			}
		}

		if( !empty( $return_var ) )
		{
			$return_var = str_replace( 'www.', '', $return_var );
		}
		return $return_var;
	}


	static function reducirInventarioMaterial($inventario_material_movimiento)
	{
		if( $inventario_material_movimiento->tipo_movimiento !== 'NEGATIVO' )
		{
			throw new ValidationException('El tipo de movimiento no es negativo');
		}

		$inventario_material				= new inventario_material();
		$inventario_material->id_material	= $inventario_material_movimiento->id_material;
		$inventario_material->setWhereString();

		if( $inventario_material->load(false,true,true) )
		{

			$inventario_material_movimiento->cantidad_anterior	= $inventario_material->cantidad;
			$inventario_material_movimiento->cantidad_actual		= $inventario_material->cantidad-$inventario_material_movimiento->cantidad_movimiento;
			$inventario_material->cantidad						= $inventario_material->cantidad-$inventario_material_movimiento->cantidad_movimiento;

			if( !$inventario_material->update('cantidad') )
			{
				throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$inventario_material->_conn->error);
			}
			if( !$inventario_material_movimiento->insertDb() )
			{
				throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$inventario_material_movimiento->_conn->error);
			}
		}
		else
		{
			///XXX error aqui;

			$inventario_material->cantidad = -$inventario_material_movimiento->cantidad_movimiento;
			$inventario_material_movimiento->cantidad_anterior = 0;
			$inventario_material_movimiento->cantidad_actual = -$inventario_material_movimiento->cantidad_movimiento;

			if( !$inventario_material->insertDb() )
			{
				throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$inventario_material->_conn->error);
			}
			if( !$inventario_material_movimiento->insertDb() )
			{
				throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$inventario_material_movimiento->_conn->error);
			}
		}
		return $inventario_material_movimiento;
	}


	static function agregarInventarioMaterial($inventario_material_movimiento)
	{
		if( $inventario_material_movimiento->tipo_movimiento !== 'POSITIVO' )
		{
			throw new ValidationException('El tipo de movimiento no es positivo');
		}

		$inventario_material				= new inventario_material();
		$inventario_material->id_material	= $inventario_material_movimiento->id_material;
		$inventario_material->setWhereString();

		if( $inventario_material->load(false,true,true) )
		{
			$inventario_material_movimiento->cantidad_anterior	= $inventario_material->cantidad;
			$inventario_material_movimiento->cantidad_actual		= $inventario_material->cantidad+$inventario_material->cantidad_movimiento;
			$inventario_material->cantidad						= $inventario_material_movimiento->cantidad_actual;

			if( !$inventario_material->update('cantidad') )
			{
				throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$inventario_material->_conn->error);
			}
			if( !$inventario_material_movimiento->insertDb() )
			{
				throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$inventario_material_movimiento->_conn->error);
			}
		}
		else
		{
			$inventario_material->cantidad = $inventario_material_movimiento->cantidad_movimiento;
			$inventario_material_movimiento->cantidad_anterior = 0;
			$inventario_material_movimiento->cantidad_actual = $inventario_material_movimiento->cantidad_movimiento;

			if( !$inventario_material->insertDb() )
			{
				throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$inventario_material->_conn->error);
			}
			if( !$inventario_material_movimiento->insertDb() )
			{
				throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$inventario_material_movimiento->_conn->error);
			}

		}
	}


	static function ajustarInventarioMaterial($inventario_material_movimiento)
	{
		if( $inventario_material_movimiento->tipo_movimiento !== 'AJUSTE' )
		{
			throw new ValidationException('El tipo de movimiento no es un ajuste');
		}

		$inventario_material				= new inventario_material();
		$inventario_material->id_material	= $inventario_material_movimiento->id_material;
		$inventario_material->setWhereString();

		if( $inventario_material->load(false,true,true) )
		{
			$inventario_material_movimiento->cantidad_anterior	= $inventario_material->cantidad;
			$inventario_material_movimiento->cantidad_actual	= $inventario_material_movimiento->cantidad_movimiento;
			$inventario_material->cantidad 						= $inventario_material_movimiento->cantidad_movimiento;

			if( !$inventario_material->update('cantidad') )
			{
				throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$inventario_material->_conn->error);
			}
			if( !$inventario_material_movimiento->insertDb() )
			{
				throw new SystemException('Ocurrio un error por favor intentar mas tarde. '.$inventario_material_movimiento->_conn->error);
			}
		}
		else
		{
			$inventario_material->cantidad = $inventario_material_movimiento->cantidad_movimiento;
			$inventario_material_movimiento->cantidad_anterior 	= 0;
			$inventario_material_movimiento->cantidad_actual 	= $inventario_material_movimiento->cantidad_movimiento;

			if( !$inventario_material->insertDb())
			{
				throw new SystemException('Ocurrio un error por favor intentar mas tarde '.$inventario_material->_conn->error );
			}

			if(!$inventario_material_movimiento->insert() )
			{
				error_log('Error object'.print_r( $inventario_material_movimiento->toArray(), true ));
				throw new SystemException('Ocurrio un error por favor intentar mas tarde '.$inventario_material_movimiento->_conn->error );
			}
		}

		return $inventario_material;
	}
}

