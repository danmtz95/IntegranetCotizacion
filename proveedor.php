<?php
namespace POSCO;

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
			$proveedor = proveedor::get( $_GET['id']  );

			if( $proveedor )
			{
				return $this->sendStatus( 200 )->json( $proveedor->toArray() );
			}
			return $this->sendStatus( 404 )->json(array('error'=>'The element wasn\'t found'));
		}


		$constraints = $this->getAllConstraints( proveedor::getAllProperties() );

		$constraints_str = count( $constraints ) > 0 ? join(' AND ',$constraints ) : '1';
		$pagination	= $this->getPagination();

		$sql_proveedores	= 'SELECT SQL_CALC_FOUND_ROWS proveedor.*
			FROM `proveedor`
			WHERE '.$constraints_str.'
			LIMIT '.$pagination->limit.'
			OFFSET '.$pagination->offset;
		$info	= DBTable::getArrayFromQuery( $sql_proveedores );
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
			$properties = proveedor::getAllPropertiesExcept('tiempo_creacion','tiempo_actualizacion','id');

			$proveedor = new proveedor();
			$proveedor->assignFromArray( $params, $properties );
			$proveedor->unsetEmptyValues( DBTable::UNSET_BLANKS );

			if( !$proveedor->insert() )
			{
					throw new ValidationException('An error Ocurred please try again later',$proveedor->_conn->error );
			}

			$results [] = $proveedor->toArray();
		}

		return $results;
	}

	function batchUpdate($array)
	{
		$results = array();
		$insert_with_ids = false;

		foreach($array as $index=>$params )
		{
			$properties = proveedor::getAllPropertiesExcept('tiempo_creacion','tiempo_actualizacion');

			$proveedor = proveedor::createFromArray( $params );

			if( $insert_with_ids )
			{
				if( !empty( $proveedor->id ) )
				{
					if( $proveedor->load(true) )
					{
						$proveedor->assignFromArray( $params, $properties );
						$proveedor->unsetEmptyValues( DBTable::UNSET_BLANKS );

						if( !$proveedor->update($properties) )
						{
							throw new ValidationException('It fails to update element #'.$proveedor->id);
						}
					}
					else
					{
						if( !$proveedor->insertDb() )
						{
							throw new ValidationException('It fails to update element at index #'.$index);
						}
					}
				}
			}
			else
			{
				if( !empty( $proveedor->id ) )
				{
					$proveedor->setWhereString( true );

					$properties = proveedor::getAllPropertiesExcept('id','tiempo_creacion','tiempo_actualizacion');
					$proveedor->unsetEmptyValues( DBTable::UNSET_BLANKS );

					if( !$proveedor->updateDb( $properties ) )
					{
						throw new ValidationException('An error Ocurred please try again later',$proveedor->_conn->error );
					}

					$proveedor->load(true);

					$results [] = $proveedor->toArray();
				}
				else
				{
					$proveedor->unsetEmptyValues( DBTable::UNSET_BLANKS );
					if( !$proveedor->insert() )
					{
						throw new ValidationException('An error Ocurred please try again later',$proveedor->_conn->error );
					}

					$results [] = $proveedor->toArray();
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
				$proveedor = new cliente();
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
