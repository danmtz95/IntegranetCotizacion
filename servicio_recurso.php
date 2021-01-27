<?php

namespace INTEGRANET_COTIZACION;

include_once(__DIR__ . '/app.php');

use \akou\Utils;
use \akou\DBTable;
use \akou\ArrayUtils;

class Service extends SuperRest
{
	function get()
	{
		session_start();
		App::connect();
		$this->setAllowHeader();

		$usuario = app::getUserFromSession();

		if ($usuario == null) {
			return $this->sendStatus(401)->json(array('error' => 'Por favor inicie sesion'));
		}

		if (isset($_GET['id']) && !empty($_GET['id'])) {
			$servicio = servicio::get($_GET['id']);

			if ($servicio) {
				return $this->sendStatus(200)->json($servicio->toArray());
			}
			return $this->sendStatus(404)->json(array('error' => 'The element wasn\'t found'));
		}

		$recursos = $this->getRecursos(array($servicio->id));

		return $this->sendStatus(200)->json(
				array(
					'servicio'	=> $servicio->toArray(), 'recursos'	=> $recursos[$servicio->id] ?: array()
				)
			);


		$contraints = array();

		if (empty($_GET['fecha_inicio'])) {
			$constraints[] = 'inicio >= "' . DBTable::escape($_GET['fecha_inicio']) . '"';
		}

		if (empty($_GET['fecha_fin'])) {
			$constraints[] = 'inicio <= "' . DBTable::escape($_GET['fecha_inicio']) . '"';
		}

		$contraints_igualdad = array('id_sucursal', 'tipo', 'codigo');

		foreach ($contraints_igualdad as $keyword) {
			if (!empty($_GET[$keyword]))
				$constraints[] = $keyword . '= "' . DBTable::escape($_GET[$keyword]) . '"';
		}

		$paginacion = $this->getPagination();

		$sql = 'SELECT SQL_CALC_FOUND_ROWS servicio.* FROM servicio LIMIT ' . $paginacion->limit . ' OFFSET ' . $paginacion->offset;

		$servicios	= DBTable::getArrayFromQuery($sql);
		$total		= DBTable::getTotalRows();

		$ids 		= ArrayUtils::itemsPropertyToArray($servicios, 'id');

		$recursos	= $this->getRecursos($ids);
		$servicios_data  = array();

		foreach ($servicios as $serv) {
			$servicios_data[] = array(
				'servicio' => $serv,
				'recursos' => $recursos[$serv['id']] ?: array()
			);
		}

		$response = array(
			'total'		=> $total, 'datos'	=> $servicios_data
		);

		return $this->sendStatus(200)->json($response);
	}

	function post()
	{
		$this->setAllowHeader();
		App::connect();

		$params = $this->getMethodParams();
		$usuario = app::getUserFromSession();

		DBTable::autocommit(FALSE);

		if ($usuario == null) {
			return $this->sendStatus(401)->json(array('error' => 'Por favor inicie sesion'));
		}

		$servicio = new servicio();
		$servicio->assignFromArrayExcluding($params['servicio'], 'id', 'fecha_creacion', 'fecha_actualizacion');
		$servicio->id_organizacion = $usuario->id_organizacion;
		$servicio->id_sucursal = $usuario->id_sucursal;
		$servicio->unsetEmptyValues(DBTable::UNSET_ALL);

		if (!$servicio->insertDb()) {
			DBTable::rollback();
			return $this->sendStatus(400)->json(array('error' => 'Ocurrion un error por favor intente de nuevo', 'dev' => $servicio->_conn->error, 'sql' => $servicio->getLastQuery()));
		}

		// $id_servicios = ArrayUtils::itemsPropertyToArray( $params['recursos'],'id_servicio_secundario' );
		// $servicios = array();

		// if( count( $params['recursos'] ) )
		// {
		// 	$sql_servicios	= 'SELECT * FROM servicio WHERE id IN('.DBTable::escapeArrayValues( $id_servicios ).')';
		// 	$servicios		= servicio::getArrayFromQuery( $sql_servicios,'id' );
		// }

		$recursos = array();
		foreach ($params['recursos'] as $serv_rec) {
			if (empty($serv_rec['servicio_recurso']['id_servicio_secundario'])) {
				DBTable::rollback();
				return $this->sendStatus(404)->json(array('error' => 'el servicio "' . $serv_rec['servicio_recurso']['id_servicio_secundario'] . '" no existe'));
			}
			$servicio_secundario = servicio::get($serv_rec['servicio_recurso']['id_servicio_secundario']);

			if (empty($servicio_secundario)) {
				DBTable::rollback();
				return $this->sendStatus(404)->json(array('error' => 'el servicio "' . $servicio_secundario->id . '" no exite'));
			}
			$servicio_recurso = new servicio_recurso();
			$servicio_recurso->id_servicio_primario = $servicio->id;
			$servicio_recurso->id_servicio_secundario = $servicio_secundario->id;
			$servicio_recurso->cantidad = $serv_rec['servicio_recurso']['cantidad'];

			if (!$servicio_recurso->insertDb()) {
				DBTable::rollback();
				return $this->sendStatus(500)->json(array('error' => 'Ocurrio un error por favor intente mas tarde', 'dev' => $servicio_recurso->_conn->error, 'sql' => $servicio_recurso->getLastQuery()));
			}

			$servicios_recurso[] = array('servicio_recurso' => $servicio_recurso->toArray(), 'servicio' => $servicio->toArray(), 'servicio_secundario' => $servicio_secundario->toArray());
		}

		DBTable::commit();
		return $this->sendStatus(200)->json(array('servicio' => $servicio->toArray()));
	}

	function put()
	{
		session_start();
		App::connect();
		DBTable::autocommit(FALSE);

		$usuario = app::getUserFromSession();

		if ($usuario == null) {
			return $this->sendStatus(401)->json(array('error' => 'Por favor inicie sesion'));
		}

		$params		= $this->getMethodParams();

		if (empty($params['servicio']) || empty($params['servicio']['id'])) {
			return $this->sendStatus(401)->json(array('error' => 'El servicio no puede estar vacio', 'params' => $params));
		}

		$servicio	= new servicio();

		$servicio->id = $params['servicio']['id'];

		if (!$servicio->load(true)) {
			return $this->sendStatus(404)->json($servicio->toArray());
		}

		$servicio->assignFromArrayExcluding($params['servicio'], 'tiempo_creacion', 'tiempo_actualizacion', 'tipo');

		$servicio->unsetEmptyValues();

		if (!$servicio->updateDb()) {
			DBTable::rollback();
			return $this->sendStatus(404)->json(array('error' => 'Ocurrion un error por favor intentar mas tarde', 'dev' => $servicio->_conn->error, 'sql' => $servicio->getLastQuery()));
		}

		//$id_servicios	= array();
		//foreach( $params['recursos'] as $sr )
		//{
		//	error_log('HERE UNO'.print_r($sr,true));
		//	$id_servicios[] = $sr['recurso']['id_servicio_secundario'];
		//}

		////error_log( print_r( $id_servicios ) );

		//$servicios		= array();

		//if( !empty( $id_servicios ) )
		//{
		//	$sql_servicios	= 'SELECT * FROM servicio WHERE id IN('.DBTable::escapeArrayValues( $id_servicios ).')';
		//	error_log( print_r( $id_servicios,true ).' '.$sql_servicios );
		//	$servicios		= servicio::getArrayFromQuery( $sql_servicios,'id' );
		//}


		foreach ($params['recursos'] as $serv_rec) {
			//if(empty( $servicios[ $serv_rec['recurso']['id_servicio_secundario'] ] ) )
			//{
			//	DBTable::rollback();
			//	return $this->sendStatus( 404 )->json(array('error'=>'el servicio "'.$serv_rec['recurso']['id_servicio_secundario'].'" no exite','params'=>$params));
			//}

			$sr = new servicio_recurso();
			$sr->id_servicio_primario	= $servicio->id;
			$sr->id_servicio_secundario	= $serv_rec['servicio_recurso']['id_servicio_secundario'];
			$sr->setWhereString();

			$ssec = new servicio();
			$ssec->id = $sr->id_servicio_secundario;

			if (!$ssec->load()) {
				//print_r( $serv_rec );
				DBTable::rollback();
				return $this->sendStatus(404)->json(array('error' => 'el servicio "' . $ssec->id . '" no exite', 'params' => $params));
			}

			if ($sr->load(false, true)) {
				$sr->assignFromArrayExcluding($serv_rec['servicio_recurso'], 'tiempo_creacion', 'id');

				if (!$sr->updateDb('cantidad', 'status')) {
					DBTable::rollback();
					return $this->sendStatus(404)->json(array('error' => 'Ocurrion un error por favor intentar mas tarde', 'dev' => $sr->_conn->error, 'sql' => $sr->getLastQuery()));
				}
			} else {
				error_log('LQ' . $sr->getLastQuery());
				$sr->assignFromArrayExcluding($serv_rec['servicio_recurso'], 'tiempo_creacion', 'id');

				if (!$sr->insertDb()) {
					DBTable::rollback();
					return $this->sendStatus(404)->json(array('error' => 'Ocurrion un error por favor intentar mas tarde', 'dev' => $sr->_conn->error, 'sql' => $sr->getLastQuery()));
				}
			}

			$servicios_recurso[] = array('servicio_recurso' => $sr->toArray());
		}

		DBTable::commit();
		return $this->sendStatus(200)->json(array('servicio' => $servicio->toArray(), 'servicios_recurso' => $servicios_recurso));
	}

	function getRecursos($ids)
	{
		$sql_secundarios 	= 'SELECT ' . servicio::getUniqSelect() . ',' . servicio_recurso::getUniqSelect() . '
			FROM servicio_recurso
			JOIN servicio ON servicio_recurso.id_servicio_secundario = servicio.id
				WHERE servicio_recurso.id_servicio_primario IN(' . DBTable::escapeArrayValues($ids) . ')';

		//echo $sql_secundarios;

		$res = DBTable::query($sql_secundarios);
		$row_info = DBTable::getFieldsInfo($res);

		$servicios_recurso = array();

		while ($data = $res->fetch_assoc()) {
			$row		= DBTable::getRowWithDataTypes($data, $row_info);
			$servicio_recurso	= servicio_recurso::createFromUniqArray($row);
			$s_servicio	= servicio::createFromUniqArray($row);

			if (!isset($recursos[$servicio_recurso->id_servicio_primario]))
				$recursos[$servicio_recurso->id_servicio_primario] = array();

			$recursos[$servicio_recurso->id_servicio_primario][]	= array(
				"servicio_recurso"	=> $servicio_recurso->toArray(), "servicio" => $s_servicio->toArray()
			);
		}

		return $servicios_recurso;
	}
}

$l = new Service();
$l->execute();
