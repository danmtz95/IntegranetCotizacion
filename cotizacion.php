<?php
namespace INTEGRANET_COTIZACION;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');
include_once( __DIR__.'/SuperRest.php');

use \akou\Utils;
use \akou\DBTable;
use \akou\RestController;
use \akou\ArrayUtils;
use \akou\ValidationException;
use \akou\LoggableException;


class Service extends SuperRest
{
	function get()
	{
		session_start();
		App::connect();
		$this->setAllowHeader();

		if( isset( $_GET['id'] ) && !empty( $_GET['id'] ) )
		{
			$cotizacion = cotizacion::get( $_GET['id']  );

			if( $cotizacion )
			{
				return $this->sendStatus( 200 )->json( $cotizacion->toArray() );
			}
			return $this->sendStatus( 404 )->json(array('error'=>'The element wasn\'t found'));
		}


		$constraints = $this->getAllConstraints( cotizacion::getAllProperties() );

		$constraints_str = count( $constraints ) > 0 ? join(' AND ',$constraints ) : '1';
		$pagination	= $this->getPagination();

		$sql_cotizaciones	= 'SELECT SQL_CALC_FOUND_ROWS cotizacion.*
			FROM `cotizacion`
			WHERE '.$constraints_str.'
			LIMIT '.$pagination->limit.'
			OFFSET '.$pagination->offset;
		$info	= DBTable::getArrayFromQuery( $sql_cotizaciones );
		$total	= DBTable::getTotalRows();
		return $this->sendStatus( 200 )->json(array("total"=>$total,"data"=>$info));
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
			$result		= $this->batchInsert( $is_assoc  ? array($params) : $params );
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
		DBTable::autocommit( false );

		try
		{
			$user = app::getUserFromSession();
			if( $user == null )
				throw new ValidationException('Please login');

			$is_assoc	= $this->isAssociativeArray( $params );
			$result		= $this->batchUpdate( $is_assoc  ? array($params) : $params );
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
		$results = array();

		foreach($array as $params )
		{
			$properties = cotizacion::getAllPropertiesExcept('tiempo_creacion','tiempo_actualizacion','id');

			$cotizacion = new cotizacion();
			$cotizacion->assignFromArray( $params, $properties );
			$cotizacion->unsetEmptyValues( DBTable::UNSET_BLANKS );

			if( !$cotizacion->insert() )
			{
					throw new ValidationException('An error Ocurred please try again later',$cotizacion->_conn->error );
			}

			$results [] = $cotizacion->toArray();
		}

		return $results;
	}

	function batchUpdate($array)
	{
		$results = array();
		$insert_with_ids = false;

		foreach($array as $index=>$params )
		{
			$properties = cotizacion::getAllPropertiesExcept('tiempo_creacion','tiempo_actualizacion');

			$cotizacion = cotizacion::createFromArray( $params );

			if( $insert_with_ids )
			{
				if( !empty( $cotizacion->id ) )
				{
					if( $cotizacion->load(true) )
					{
						$cotizacion->assignFromArray( $params, $properties );
						$cotizacion->unsetEmptyValues( DBTable::UNSET_BLANKS );

						if( !$cotizacion->update($properties) )
						{
							throw new ValidationException('It fails to update element #'.$cotizacion->id);
						}
					}
					else
					{
						if( !$cotizacion->insertDb() )
						{
							throw new ValidationException('It fails to update element at index #'.$index);
						}
					}
				}
			}
			else
			{
				if( !empty( $cotizacion->id ) )
				{
					$cotizacion->setWhereString( true );

					$properties = cotizacion::getAllPropertiesExcept('id','tiempo_creacion','tiempo_actualizacion');
					$cotizacion->unsetEmptyValues( DBTable::UNSET_BLANKS );

					if( !$cotizacion->updateDb( $properties ) )
					{
						throw new ValidationException('An error Ocurred please try again later',$cotizacion->_conn->error );
					}

					$cotizacion->load(true);

					$results [] = $cotizacion->toArray();
				}
				else
				{
					$cotizacion->unsetEmptyValues( DBTable::UNSET_BLANKS );
					if( !$cotizacion->insert() )
					{
						throw new ValidationException('An error Ocurred please try again later',$cotizacion->_conn->error );
					}

					$results [] = $cotizacion->toArray();
				}
			}
		}

		return $results;
	}

	/*
	function delete()
	{
		try
		{
			app::connect();
			DBTable::autocommit( false );

			$user = app::getUserFromSession();
			if( $user == null )
				throw new ValidationException('Please login');

			if( empty( $_GET['id'] ) )
			{
				$cotizacion = new cliente();
				$cliente->id = $_GET['id'];

				if( !$cliente->load(true) )
				{
					throw new NotFoundException('The element was not found');
				}

				if( !$cliente->deleteDb() )
				{
					throw new SystemException('An error occourred, please try again later');
				}

			}
			DBTable::commit();
			return $this->sendStatus( 200 )->json( $cliente->toArray() );
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
	*/
}
$l = new Service();
$l->execute();
