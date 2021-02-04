<?php
namespace APP;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');
include_once( __DIR__.'/SuperRest.php');

use \akou\Utils;
use \akou\DBTable;
use \akou\RestController;
use \akou\ArrayUtils;
use \akou\ValidationException;
use \akou\LoggableException;
use \akou\SystemException;

class Service extends SuperRest
{
	function get()
	{
		session_start();
		App::connect();
		$this->setAllowHeader();

		return $this->genericGet("servicio");
	}

	function getInfo($servicio_array)
	{
		$this->debug('servicio_array',$servicio_array);
		// Obteniendo propiedades de los servicios que fueron enviados del get;
		$servicio_id 	= ArrayUtils::getItemsProperty($servicio_array,'id', true);
		// $this->debug('0',$servicio_id);$this->debug('0',$servicio_id);
		// Obteinendo los recursos (servicio_recurso) que tienen el id del servicio en el campo id_servicio_primario;
		$servicio_detalles_array	= servicio_recurso::search(array('id_servicio_primario'=>$servicio_id),false,'id');
		// $this->debug('1',$servicio_detalles_array);
		// Obteniendo propiedades de los recursos  (servicio_recurso);
		$servicios_secundarios_ids = ArrayUtils::getItemsProperty($servicio_detalles_array,'id_servicio_secundario',true);
		// $this->debug('2',$servicios_ids);
		// obteniendo array de servicios con la lista de id's obtenida ($servicios_ids);
		$servicios_secundarios_array = servicio::search(array('id'=>$servicios_secundarios_ids),false,'id');
		// $this->debug('3',$servicios_array);
		// Agrupando el array de servicios en base al id como indice;
		$servicio_secundario_array = arrayUtils::groupByIndex($servicios_secundarios_array,'id');
		// $this->debug('4',$servicio_array);
		// Agrupando el array de recursos en base al id como indice 
		$servicio_detalle_array = arrayUtils::groupByIndex($servicio_detalles_array,'id_servicio_primario');
		// $this->debug('5',$servicio_detalle_array);
	

		$result = array();
		// $this->debug('servicio_array',$servicio_array);
		foreach($servicio_array as $servicio)
		{
			// $this->debug('recurso',$servicio);
			$servicio_detalles = isset( $servicio_detalle_array[ $servicio['id'] ] )
				? $servicio_detalle_array[ $servicio['id'] ]
				: array();


			$recursos_info = array();

			foreach($servicio_detalles as $servicio_recurso)
			{
				// $this->debug('cd',$servicio_recurso);
				$servicio_recurso = $servicio_detalles_array[ $servicio_recurso['id'] ];
				$servicio_secundario = $servicios_secundarios_array[$servicio_recurso['id_servicio_secundario']];
				// $category = $category_array[ $item['category_id'] ];

				$recursos_info[]= array(
					'servicio_secundario'=>$servicio_secundario,
					'servicio_recurso'=> $servicio_recurso,
				);
			}

			$result[] = array(
				// ''=> $detalles_info,
				'servicio'=>$servicio,
				'servicio_detalles'=> $recursos_info
			);
		}
		$this->debug('info', $result );

		return $result;
	}

	function post()
	{
		$this->setAllowHeader();
		$params = $this->getMethodParams();
		app::connect();
		DBTable::autocommit(false );

		try
		{
			$user = app::getUserFromSession();
			if( $user == null )
				throw new ValidationException('Please login');

			$is_assoc	= $this->isAssociativeArray( $params );
			$result		= $this->batchInsert( $is_assoc	? array($params) : $params );
			DBTable::commit();
			return $this->sendStatus( 200 )->json( $is_assoc ? $result[0] : $result );
		}
		catch(LoggableException $e)
		{
			DBTable::rollback();
			return $this->sendStatus( $e->code )->json(array("error"=>$e->getMessage()));
		}
		catch(Exception $e)
		{
			DBTable::rollback();
			return $this->sendStatus( 500 )->json(array("error"=>$e->getMessage()));
		}
	}

	function put()
	{
		$this->setAllowHeader();
		$params = $this->getMethodParams();
		app::connect();
		DBTable::autocommit(false );

		try
		{
			$user = app::getUserFromSession();
			if( $user == null )
				throw new ValidationException('Please login');

			$is_assoc	= $this->isAssociativeArray( $params );
			$result		= $this->batchUpdate( $is_assoc	? array($params) : $params );
			DBTable::commit();
			return $this->sendStatus( 200 )->json( $is_assoc ? $result[0] : $result );
		}
		catch(LoggableException $e)
		{
			DBTable::rollback();
			return $this->sendStatus( $e->code )->json(array("error"=>$e->getMessage()));
		}
		catch(Exception $e)
		{
			DBTable::rollback();
			return $this->sendStatus( 500 )->json(array("error"=>$e->getMessage()));
		}
	}


	function batchInsert($array)
	{
		$servicio_props = servicio::getAllPropertiesExcept('id','fecha_creacion','fecha_actualizacion');
		$servicio_recurso_props = servicio_recurso::getAllPropertiesExcept('id','fecha_creacion','fecha_actualizacion');

		$result = array();
		foreach($array as $servicio_info )
		{
			$servicio = new servicio();
			$servicio->assignFromArray( $servicio_info['servicio'], $servicio_props );
	
			$this->debug('servicio',$servicio_info['servicio'] );

			// if( empty( $servicio_recurso_array) )
			// 	throw  new ValidationException('por favor agregar al menos 1 detalle');

			if( !$servicio->insertDb() )
			{
				throw new SystemException('Ocurrio un error por favor intente de nuevo'.$servicio->getError() );
			}

	
			$servicio_recurso_array = ArrayUtils::getItemsProperty($servicio_info['servicio_detalles'],'servicio_detalle');
			foreach($servicio_recurso_array  as $servicio_recurso )
			{
				$servicio_recurso = new servicio_recurso();
				$servicio_recurso->assignFromArray( $servicio_recurso, $servicio_recurso_props );
				$servicio_recurso->id_servicio_primario = $servicio->id;
				// $cotizacion_detalle->id_servicio = $
				if(!$servicio_recurso->insertDb())
				{
					throw new SystemException('Ocurrio un error por favor intente de nuevo'.$servicio_recurso->getError() );
				}
			}
			$result[] = $servicio->toArray();
		}

		return $result;
	}

	function batchUpdate($array)
	{
		$servicio_props = servicio::getAllPropertiesExcept('id','fecha_creacion','fecha_actualizacion');
		$servicio_recurso_props = servicio_recurso::getAllPropertiesExcept('id','fecha_creacion','fecha_actualizacion');
		$this->debug('sede',$servicio_props);

		$result = array();
		foreach($array as $servicio_info )
		{

			if( empty( $servicio_info['servicio']['id']) )
			{
				throw new ValidationException('El id no puede estar vacio');
			}
			$servicio = servicio::get( $servicio_info['servicio']['id'] );


			$servicio->assignFromArray( $servicio_info['servicio'], $servicio_props );

			$servicio_recurso_array = ArrayUtils::getItemsProperty($servicio_info['recursos'],'recurso');

			// if( empty( $servicio_recurso_array) )
			// 	throw  new ValidationException('Por favor agregar al menos 1 item');

			if( !$servicio->update($servicio_props) )
			{
				throw new SystemException('Ocurrio un error por favor intente de nuevo'.$servicio->getError() );
			}


			$servicio_recurso_ids = array();

			$sql = 'DELETE FROM servicio_recurso WHERE id_servicio_primario = "'.DBTable::escape( $servicio->id ).'"';
			//error_log( $sql );
			DBTable::query( $sql );

			foreach($servicio_recurso_array  as $servicio_recurso )
			{
				$servicio_recurso = new servicio_recurso();
				$servicio_recurso->assignFromArray( $cd, $servicio_recurso_props );
				// $servicio_detale->id_cotizacion = $servicio->id;
				$this->debug('cd',$servicio_recurso);

				if(!$servicio_recurso->insertDb())
				{
					throw new SystemException('Ocurrio un error por favor intente de nuevo'.$servicio_recurso->getError() );
				}
			}

			$result[] = $servicio->toArray();
		}

		return $result;
	}
}
$l = new Service();
$l->execute();