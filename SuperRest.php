<?php
namespace POSCO;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');

use \akou\Utils;
use \akou\DBTable;
use \akou\RestController;
use \akou\ArrayUtils;

class SuperRest extends \akou\RestController
{
	function options()
	{
		$this->setAllowHeader();
		return $this->defaultOptions();
	}

	function getPagination()
	{
		$page = 0;

		if( !empty( $_GET['page'] ) )
			$page = intval( $_GET['page'] );

		$page_size = 20;

		if( !empty( $_GET['limit'] ) )
			$page_size =  intval( $_GET['limit'] );


		return	$this->getPaginationInfo($page,$page_size,20);
	}
	function getEqualConstraints($array,$table_name='')
	{
		$constraints = [];
		foreach( $array as $index )
		{
			if( isset( $_GET[$index] ) && $_GET[$index] !== '' )
				$constraints[] =($table_name?$table_name.'.':''). $index.'="'.DBTable::escape( $_GET[ $index ]).'"';
		}
		return $constraints;
	}

	function getNotNullConstraints($parameters, $table_name='')
	{
		$fields_from_request = !empty( $_GET['_NN'] ) ? explode(',',$_GET['_NN']) : [];
		$constraints = [];

		foreach($parameters as $field_name )
		{
			if( in_array($field_name, $fields_from_request) )
			{
				$constraints[] =($table_name?$table_name.'.':''). $field_name.' IS NOT NULL';
			}
		}
		return $constraints;
	}

	function getStartLikeConstraints( $array, $table_name)
	{
		$constraints = array();

		foreach( $array as $index )
		{
			if( isset( $_GET[$index.'^'] ) && $_GET[$index.'^'] !== '' )
			{
				$constraints[] = ($table_name?$table_name.'.':'').$index.' LIKE "'.DBTable::escape( trim( $_GET[ $index.'^' ] ) ).'%"';
			}
		}
		if( count( $constraints ) )
			return array( "(".join(' OR ',$constraints ).")" );
		return array();
	}
	function getLikeConstraints($array,$table_name='')
	{
		$constraints = [];
		foreach( $array as $index )
		{
			if( isset( $_GET[$index.'~~'] ) && $_GET[$index.'~~'] !== '' )
			{
				$constraints[] = ($table_name?$table_name.'.':'').$index.' LIKE "%'.DBTable::escape(trim($_GET[ $index.'~~' ]) ) .'%"';
			}
		}
		if( count( $constraints ) )
			return array( "(".join(' OR ',$constraints ).")" );
		return array();
	}

	function getBiggerOrEqualThanConstraints( $array, $table_name )
	{
		$constraints = [];

		foreach( $array as $index )
		{
			if( isset( $_GET[$index.'>~'] ) && $_GET[$index.'>~'] !== '' )
			{
				$constraints[] = ($table_name?$table_name.'.':'').$index.' >= "'.DBTable::escape( $_GET[ $index.'>~' ]).'"';
			}
		}


		return $constraints;
	}
	function getBiggerThanConstraints($array ,$table_name='')
	{
		$constraints = [];

		foreach( $array as $index )
		{
			if( isset( $_GET[$index.'>'] ) && $_GET[$index.'>'] !== '' )
			{
				$constraints[] = ($table_name?$table_name.'.':'').$index.' > "'.DBTable::escape( $_GET[ $index.'>' ]).'"';
			}
		}
		return $constraints;
	}

	function getSmallestThanConstraints($array,$table_name='')
	{
		$constraints = [];
		foreach( $array as $index )
		{
			if( isset( $_GET[$index.'<'] ) && $_GET[$index.'<'] !== '' )
				$constraints[] = ($table_name?$table_name.'.':'').$index.' < "'.DBTable::escape( $_GET[ $index.'<' ]).'"';
		}
		return $constraints;
	}
	function getSmallestOrEqualThanConstraints($array,$table_name='')
	{
		$constraints = [];
		foreach( $array as $index )
		{
			if( isset( $_GET[$index.'<~'] ) && $_GET[$index.'<~'] !== '' )
				$constraints[] = ($table_name?$table_name.'.':'').$index.' <= "'.DBTable::escape( $_GET[ $index.'<~' ]).'"';
		}
		return $constraints;
	}

	function getCsvConstraints($array,$table_name='')
	{
		$constraints = [];
		foreach( $array as $index )
		{
			if( isset( $_GET[$index.','] ) && $_GET[$index.','] !== '' )
				$constraints[] = ($table_name?$table_name.'.':'').$index.' IN  ('.DBTable::escapeCSV( $_GET[ $index.',' ]).')';
		}
		return $constraints;
	}

	function getAllConstraints($key_constraints,$table_name='')
	{
		$like_constraints = $this->getLikeConstraints( $key_constraints, $table_name );
		$equal_constrints = $this->getEqualConstraints( $key_constraints, $table_name );
		$bigger_than_constraints = $this->getBiggerThanConstraints( $key_constraints, $table_name );
		$ge_constraints = $this->getBiggerOrEqualThanConstraints( $key_constraints, $table_name );
		$smallest_than_constraints = $this->getSmallestThanConstraints( $key_constraints, $table_name );
		$le_than_constraints = $this->getSmallestOrEqualThanConstraints( $key_constraints, $table_name );
		$csv_constraints	= $this->getCsvConstraints( $key_constraints, $table_name );
		$start_constraints			= $this->getStartLikeConstraints( $key_constraints, $table_name );
		$not_null_constrints		= $this->getNotNullConstraints( $key_constraints, $table_name );
		return array_merge( $like_constraints, $equal_constrints, $ge_constraints, $smallest_than_constraints,$le_than_constraints,$csv_constraints,$start_constraints, $not_null_constrints );
	}

	function getSessionErrors($usuario,$roles = NULL )
	{
		if(empty( $usuario_session ))
		{
			return $this->sendStatus( 401 )->json(array('error'=>'Por favor inicia sesion'));
		}

		if( $roles !== NULL && !in_array( $usuario->tipo, $roles) )
		{
			return $this->sendStatus( 403 )->json(array('error'=>'Permiso denegado se necesita alguno de los siguientes permisos '.join(',', $roles)));
		}
		return NULL;
	}

	function isAssociativeArray(array $array)
	{
  		return count(array_filter(array_keys($array), 'is_string')) > 0;
	}

	function debug($label, $array, $json=TRUE)
	{
		if( $json )
			error_log( $label.' '.json_encode( $array, JSON_PRETTY_PRINT ));
		else
		error_log( $label.' '.print_r( $array, true ) );
	}
	function debugArray($label, $array,$json=TRUE )
	{
		return $this->debug($label,$array,$json);
	}

	function saveReplay()
	{
		//$replay				= new replay();
		//$replay->url		= $_SERVER['REQUEST_URI'];
		//$replay->method		= $_SERVER['REQUEST_METHOD'];

		//$replay->get_params	= json_encode( $_GET );
		//$replay->post_params = json_encode( $this->getMethodParams() );
		//$replay->headers	= json_encode( getallheaders() );

		//if( !$replay->insert() )
		//{

		//}
	}
}
