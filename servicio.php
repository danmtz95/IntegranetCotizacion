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


class Service extends SuperRest
{
	function get()
	{
		session_start();
		App::connect();
		$this->setAllowHeader();

		if( isset( $_GET['id'] ) && !empty( $_GET['id'] ) )
		{
			$servicio = servicio::get( $_GET['id']  );

			if( $servicio )
			{
				return $this->sendStatus( 200 )->json( $servicio->toArray() );
			}
			return $this->sendStatus( 404 )->json(array('error'=>'The element wasn\'t found'));
		}


		$constraints = $this->getAllConstraints( servicio::getAllProperties() );

		$constraints_str = count( $constraints ) > 0 ? join(' AND ',$constraints ) : '1';
		$pagination	= $this->getPagination();

		$sql_usuarios	= 'SELECT SQL_CALC_FOUND_ROWS servicio.*
			FROM `servicio`
			WHERE '.$constraints_str.'
			LIMIT '.$pagination->limit.'
			OFFSET '.$pagination->offset;
		$info	= DBTable::getArrayFromQuery( $sql_usuarios );
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
			$properties = servicio::getAllPropertiesExcept('tiempo_creacion','tiempo_actualizacion','id');

			$servicio = new servicio();
			$servicio->assignFromArray( $params, $properties );
			$servicio->unsetEmptyValues( DBTable::UNSET_BLANKS );

			if( !$servicio->insert() )
			{
					throw new ValidationException('An error Ocurred please try again later',$servicio->_conn->error );
			}

			$results [] = $servicio->toArray();
		}

		return $results;
	}

	function batchUpdate($array)
	{
		$results = array();
		$insert_with_ids = false;

		foreach($array as $index=>$params )
		{
			$properties = servicio::getAllPropertiesExcept('tiempo_creacion','tiempo_actualizacion');

			$servicio = servicio::createFromArray( $params );

			if( $insert_with_ids )
			{
				if( !empty( $servicio->id ) )
				{
					if( $servicio->load(true) )
					{
						$servicio->assignFromArray( $params, $properties );
						$servicio->unsetEmptyValues( DBTable::UNSET_BLANKS );

						if( !$servicio->update($properties) )
						{
							throw new ValidationException('It fails to update element #'.$servicio->id);
						}
					}
					else
					{
						if( !$servicio->insertDb() )
						{
							throw new ValidationException('It fails to update element at index #'.$index);
						}
					}
				}
			}
			else
			{
				if( !empty( $servicio->id ) )
				{
					$servicio->setWhereString( true );

					$properties = servicio::getAllPropertiesExcept('id','tiempo_creacion','tiempo_actualizacion');
					$servicio->unsetEmptyValues( DBTable::UNSET_BLANKS );

					if( !$servicio->updateDb( $properties ) )
					{
						throw new ValidationException('An error Ocurred please try again later',$servicio->_conn->error );
					}

					$servicio->load(true);

					$results [] = $servicio->toArray();
				}
				else
				{
					$servicio->unsetEmptyValues( DBTable::UNSET_BLANKS );
					if( !$servicio->insert() )
					{
						throw new ValidationException('An error Ocurred please try again later',$servicio->_conn->error );
					}

					$results [] = $servicio->toArray();
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
				$servicio = new servicio();
				$servicio->id = $_GET['id'];

				if( !$servicio->load(true) )
				{
					throw new NotFoundException('The element was not found');
				}

				if( !$servicio->deleteDb() )
				{
					throw new SystemException('An error occourred, please try again later');
				}

			}
			DBTable::commit();
			return $this->sendStatus( 200 )->json( $servicio->toArray() );
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
