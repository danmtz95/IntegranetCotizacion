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

		return $this->genericGet("cotizacion");
	}

	function getInfo($cotizacion_array)
	{
		$cotizacion_ids 	= ArrayUtils::getItemsProperty($cotizacion_array,'id', true);
		// $cotizacion_detalle_array	= cotizacion_detalle::searchGroupByIndex(array('id_cotizacion'=>$cotizacion_ids),false,'id_cotizacion');
		$cotizacion_detalles_array	= cotizacion_detalle::search(array('id_cotizacion'=>$cotizacion_ids),false,'id');
		// 
		$servicios_ids = ArrayUtils::getItemsProperty($cotizacion_detalles_array,'id_servicio',true);
		$servicios_array = servicio::search(array('id'=>$servicios_ids),false,'id');
		$servicio_array = arrayUtils::groupByIndex($servicios_array,'id');
		$cotizacion_detalle_array = arrayUtils::groupByIndex($cotizacion_detalles_array,'id_cotizacion');
		// error_log(print_r($servicios_array,true));
		// error_log(print_r($cotizacion_detalle_array,true));
		// error_log(print_r($cotizacion_detalles_array,true));


		// foreach($cotizacion_detalle_array as $index => $cd)
		// {
		// 	$tmp_ids = ArrayUtils::getItemsProperty($cd,'id_cotizacion', true);
		// 	$detalles_ids = array_merge( $tmp_ids, $detalles_ids );
		// }

		// $items_array = item::search(array('id'=>$items_ids),false, 'id');
		// $category_array	= category::search(array('id'=>array_keys( $items_array )), false, 'id');

		$result = array();

		foreach($cotizacion_array as $cotizacion)
		{
			$cotizacion_detalles = isset( $cotizacion_detalle_array[ $cotizacion['id'] ] )
				? $cotizacion_detalle_array[ $cotizacion['id'] ]
				: array();


			$detalles_info = array();

			foreach($cotizacion_detalles as $cd)
			{
				$this->debug('cd',$cd);
				$detalle = $cotizacion_detalles_array[ $cd['id'] ];
				$servicio = $servicios_array[$cd['id_servicio']];
				// $category = $category_array[ $item['category_id'] ];

				$detalles_info[]= array(
					'servicio'=>$servicio,
					'cotizacion_detalle'=> $detalle,
				);
			}

			$result[] = array(
				// ''=> $detalles_info,
				'cotizacion'=>$cotizacion,
				'cotizacion_detalles'=> $detalles_info
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
		$cotizacion_props = cotizacion::getAllPropertiesExcept('id','fecha_creacion','fecha_actualizacion');
		$cotizacion_detalle_props = cotizacion_detalle::getAllPropertiesExcept('id','id_cotizacion','fecha_creacion','fecha_actualizacion');

		$result = array();
		foreach($array as $cotizacion_info )
		{
			$cotizacion = new cotizacion();
			$cotizacion->assignFromArray( $cotizacion_info['cotizacion'], $cotizacion_props );

			$cotizacion_detalle_array = ArrayUtils::getItemsProperty($cotizacion_info['cotizacion_detalles'],'cotizacion_detalle');
			$this->debug('cotizacion_detalle_array',$cotizacion_detalle_array );

			if( empty( $cotizacion_detalle_array) )
				throw  new ValidationException('por favor agregar al menos 1 detalle');

			if( !$cotizacion->insertDb() )
			{
				throw new SystemException('Ocurrio un error por favor intente de nuevo'.$cotizacion->getError() );
			}

			foreach($cotizacion_detalle_array  as $cd )
			{
				$cotizacion_detalle = new cotizacion_detalle();
				$cotizacion_detalle->assignFromArray( $cd, $cotizacion_detalle_props );
				$cotizacion_detalle->id_cotizacion = $cotizacion->id;
				// $cotizacion_detalle->id_servicio = $
				if(!$cotizacion_detalle->insertDb())
				{
					throw new SystemException('Ocurrio un error por favor intente de nuevo'.$cotizacion_detalle->getError() );
				}
			}
			$result[] = $cotizacion->toArray();
		}

		return $result;
	}

	function batchUpdate($array)
	{
		$cotizacion_props = cotizacion::getAllPropertiesExcept('id','fecha_creacion','fecha_actualizacion');
		$cotizacion_detalle_props = cotizacion_detalle::getAllPropertiesExcept('id','fecha_creacion','fecha_actualizacion');
		$this->debug('sede',$cotizacion_props);

		$result = array();
		foreach($array as $cotizacion_info )
		{

			if( empty( $cotizacion_info['cotizacion']['id']) )
			{
				throw new ValidationException('El id no puede estar vacio');
			}
			$cotizacion = cotizacion::get( $cotizacion_info['cotizacion']['id'] );

			if( $cotizacion->estado_de_compra !== 'PENDIENTE' )
				throw new ValidationException('La cotizacion ya tiene una orden de compra en camino');

			$cotizacion->assignFromArray( $cotizacion_info['cotizacion'], $cotizacion_props );

			$cotizacion_detalle_array = ArrayUtils::getItemsProperty($cotizacion_info['cotizacion_detalles'],'cotizacion_detalle');

			if( empty( $cotizacion_detalle_array) )
				throw  new ValidationException('Por favor agregar al menos 1 item');

			if( !$cotizacion->update($cotizacion_props) )
			{
				throw new SystemException('Ocurrio un error por favor intente de nuevo'.$cotizacion->getError() );
			}


			$cotizacion_detalle_ids = array();

			$sql = 'DELETE FROM cotizacion_detalle WHERE id_cotizacion = "'.DBTable::escape( $cotizacion->id ).'"';
			//error_log( $sql );
			DBTable::query( $sql );

			foreach($cotizacion_detalle_array  as $cd )
			{
				$cotizacion_detalle = new cotizacion_detalle();
				$cotizacion_detalle->assignFromArray( $cd, $cotizacion_detalle_props );
				// $cotizacion_detale->id_cotizacion = $cotizacion->id;
				$this->debug('cd',$cotizacion_detalle);

				if(!$cotizacion_detalle->insertDb())
				{
					throw new SystemException('Ocurrio un error por favor intente de nuevo'.$cotizacion_detalle->getError() );
				}
			}

			$result[] = $cotizacion->toArray();
		}

		return $result;
	}
}
$l = new Service();
$l->execute();