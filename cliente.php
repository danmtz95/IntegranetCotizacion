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
			$cliente = cliente::get( $_GET['id']  );

			if( $cliente )
			{
				return $this->sendStatus( 200 )->json( $cliente->toArray() );
			}
			return $this->sendStatus( 404 )->json(array('error'=>'The element wasn\'t found'));
		}


		$constraints = $this->getAllConstraints( cliente::getAllProperties() );

		$constraints_str = count( $constraints ) > 0 ? join(' AND ',$constraints ) : '1';
		$pagination	= $this->getPagination();

		$sql_usuarios	= 'SELECT SQL_CALC_FOUND_ROWS cliente.*
			FROM `cliente`
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
			$properties = cliente::getAllPropertiesExcept('tiempo_creacion','tiempo_actualizacion','id');

			$cliente = new cliente();
			$cliente->assignFromArray( $params, $properties );
			$cliente->unsetEmptyValues( DBTable::UNSET_BLANKS );

			if( !$cliente->insert() )
			{
					throw new ValidationException('An error Ocurred please try again later',$cliente->_conn->error );
			}

			$results [] = $cliente->toArray();
		}

		return $results;
	}

	function batchUpdate($array)
	{
		$results = array();
		$insert_with_ids = false;

		foreach($array as $index=>$params )
		{
			$properties = cliente::getAllPropertiesExcept('tiempo_creacion','tiempo_actualizacion');

			$cliente = cliente::createFromArray( $params );

			if( $insert_with_ids )
			{
				if( !empty( $cliente->id ) )
				{
					if( $cliente->load(true) )
					{
						$cliente->assignFromArray( $params, $properties );
						$cliente->unsetEmptyValues( DBTable::UNSET_BLANKS );

						if( !$cliente->update($properties) )
						{
							throw new ValidationException('It fails to update element #'.$cliente->id);
						}
					}
					else
					{
						if( !$cliente->insertDb() )
						{
							throw new ValidationException('It fails to update element at index #'.$index);
						}
					}
				}
			}
			else
			{
				if( !empty( $cliente->id ) )
				{
					$cliente->setWhereString( true );

					$properties = cliente::getAllPropertiesExcept('id','tiempo_creacion','tiempo_actualizacion');
					$cliente->unsetEmptyValues( DBTable::UNSET_BLANKS );

					if( !$cliente->updateDb( $properties ) )
					{
						throw new ValidationException('An error Ocurred please try again later',$cliente->_conn->error );
					}

					$cliente->load(true);

					$results [] = $cliente->toArray();
				}
				else
				{
					$cliente->unsetEmptyValues( DBTable::UNSET_BLANKS );
					if( !$cliente->insert() )
					{
						throw new ValidationException('An error Ocurred please try again later',$cliente->_conn->error );
					}

					$results [] = $cliente->toArray();
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
				$cliente = new cliente();
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
