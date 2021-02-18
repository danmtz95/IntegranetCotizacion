<?php

namespace APP;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');
include_once( __DIR__.'/SuperRest.php');
include_once( __DIR__.'/akou/src/Image.php');

use \akou\Utils;
use \akou\DBTable;
use \akou\ArrayUtils;
use \akou\ValidationException;
use \akou\LoggableException;
use \akou\SystemException;

class Service extends SuperRest
{
	//const DIRECTORY = '/var/www/html/sold/thepickzone.com/api/user_images';
	//const DIRECTORY = '/srv/http/api/user_images';

	function get()
	{
		//Validation then connect
		App::connect();

		if( empty( $_GET['id'] ) )
		{
			$this->sendStatus(404)->json( Array('error'=>'Not Found') );
			return;
		}

		$usuario = app::getUserFromSession();

		$image = new imagen();
		$image->id = $_GET['id'];

		if( $image->load())
		{

			if( isset($_GET['format']) && $_GET['format'] == 'json' )
			{
				return $this->sendStatus(200)->json( $image->toArray() );
			}
			else
			{
				$is_authorized = !$image->es_privada;

				if( $image->es_privada && !empty( $usuario) && $usuario->id == $image->uploader_user_id )
				{
					$is_authorized = false;

					$image_user = new imagen_usuario();
					$image_user->user_id = $
					$image_user->image_id = $image->id;
					$image_user->setWhereString();

					if( $image_user->load() )
					{
						$is_authorized = true;
					}
				}

				if( !empty( $_GET['download'] ) )
				{
					header('Content-Disposition: attachment; filename="' . $image->file_name . '"');
					header('Content-Transfer-Encoding: binary');
				}

				if( $is_authorized )
				{
					header('Content-type: '.$image->content_type);
					header('Content-length: '.$image->size);
		  			header('Cache-Control: max-age=259200');
					echo file_get_contents(app::$image_directory.'/'.$image->filename, true);
				}
				else
				{
					header('Content-type: image/png');
					echo file_get_contents(app::$image_directory."/unauthorized.png");
					header('Cache-Control: max-age=0');
					//header('Location: '.$protocol.$site."/login" );
				}
			}
		}
		else
		{
			$this->sendStatus(404);
		}
	}

	function post()
	{
		include_once( __DIR__.'/akou/src/Image.php');
		if( !isset( $_FILES['image']) )
		{
			$this->sendStatus(400)->json( Array('error'=>'File `image_file` cant be empty') );
			//$this->sendStatus( 400 )->json(Array('error'=>'bad request '));
		}

		app::connect();

		$user = app::getUserFromSession();

		if( empty( $user ) )
			$this->sendStatus(401)->json( Array('error'=>'Login required') );

		$obj_file_image = $_FILES['image'];
		$image = new \akou\Image();
		$max_height = 1200;
		$max_width	= 1920;
		$max_weight = 1024*1024*5;
		$min_width	= 1;
		$min_height = 1;
		$params = $this->getMethodParams();

		try{

			$result = $image->formImageSaveToPath(
					$obj_file_image //obj_FILE
					,app::$image_directory // ,$to_save_dirname
					,$max_height
					,$max_width
					,555242880 //$max_weight=
					,$min_width
					,$min_height
			);
		}
		catch(SystemException $ex)
		{
			error_log("Store Exception");
		}

		$image = new imagen();
		$image->assignFromArray( $result );
		$image->es_privada = empty( $params['es_privada'] ) ? 0 : 1;
		$image->uploader_user_id = $user->id;

		if( $image->insertDb() )
		{
			$this->sendStatus(200)->json( $image->toArray() );
		}
		else
		{
			$this->sendStatus(400)->json( $image->toArray() );
		}
	}
}

$l = new Service();
$l->execute();
