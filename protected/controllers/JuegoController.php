<?php

class JuegoController extends Controller {

    public function actionIndex() {
        $this->render('index');
    }

    /**
     * Crea una Resolución de un problema para un usuario y torneo dado.
     * Retorna los parámetros de una resolución random del problema y un token
     * que identifica la resolución creada
     * 
     * @param type $token
     * @param type $problema
     */
    public function actionSolicitud($token, $problema) {
        $tiempoActual = (int) (microtime(true) * 1000);
        $respuesta = array();
        //se busca el usuario y el toneo en base al token
        $Usuario = TorneoUsuario::model()->find('token=:token', array(':token' => $token));
        if (is_null($Usuario)) {
            $respuesta['error'] = 'No es un token válido';
        } else {
            /* @var $Usuario TorneoUsuario */
            $idTorneo = $Usuario->idTorneo;
            //busca el problema del torneo
            $Problema = TorneoProblema::model()->find('idTorneo=:idTorneo and Orden=:Orden', array(':idTorneo' => $idTorneo, ':Orden' => $problema));

            /* @var $Problema TorneoProblema */

            if (is_null($Problema)) {
                //si no existe el problema
                $respuesta['error'] = 'No existe el problema para el torneo';
            } else {
                //si existe busco la cantidad de soluciones que hay disponibles para ese problema
                $cantidadSoluciones = $Problema->idProblema0->solucionCount;
                if ($cantidadSoluciones == 0) {
                    $respuesta['error'] = 'No tiene soluciones';
                } else {
                    $solucionRandom = rand(0, $cantidadSoluciones - 1);
                    $soluciones = $Problema->idProblema0->solucions;
                    $solucion = $soluciones[$solucionRandom];
                    /* @var $solucion Solucion */
                    $token = md5(microtime() . $token);
                    //se crea un registro en la tabla Resolución esperando por la respuesta
                    $tablaResolucion = new Resolucion;
                    $tablaResolucion->idUsuario = $Usuario->idUsuario;
                    $tablaResolucion->idTorneo = $idTorneo;
                    $tablaResolucion->idProblema = $solucion->idProblema;
                    $tablaResolucion->idSolucion = $solucion->idSolucion;
                    $tablaResolucion->idEstado = 1;
                    $tablaResolucion->Token = $token;
                    $tablaResolucion->FechaSolicitud = $tiempoActual;
                    if ($tablaResolucion->insert()) {
                        $respuesta['nombreProblema'] = $Problema->idProblema0->Nombre;
                        $respuesta['enunciado'] = $Problema->idProblema0->Enunciado;
                        $respuesta['parametrosEntrada'] = $solucion->ParametrosEntrada;
                        $respuesta['token'] = $token;
                    } else {
                        $respuesta['error'] = 'al agregar la resolucion';
                    }
                }
            }
        }
        echo json_encode($respuesta);
        exit;
    }

    /**
     * Valida que la Respuesta sea correcta
     * @param type $tokenSolicitud
     * @param type $solucion
     */
    public function actionRespuesta($tokenSolicitud, $solucion) {
        $tiempoActual = (int) (microtime(true) * 1000);
        $respuesta = array();
        //se busca el usuario y el toneo en base al token
        $Resolucion = Resolucion::model()->find('Token=:token', array(':token' => $tokenSolicitud));
        if (is_null($Resolucion)) {
            $respuesta['error'] = 'No es un token válido';
        } elseif ($Resolucion->idEstado != 1) {
            $respuesta['error'] = 'Esta solicitud ya ha sido respondida';
        } else {
            /* @var $Resolucion Resolucion */
            $tiempo = $tiempoActual - $Resolucion->FechaSolicitud;

            if ($solucion == $Resolucion->idSolucion0->Salida) {
                $idEstado = 2; //ok
            } else {
                $idEstado = 3; //no ok
            }

            if ($tiempo > $Resolucion->idProblema0->TiempoEjecucionMax) {
                $idEstado+=2; //supera el tiempo
            }

            if ($Resolucion->idTorneo0->idEstado != 2) {
                $idEstado+=4; //torneo terminado
            } else {
                /**
                 * buscar si ya resolvió el problema
                 */
                $ResolucionCorrecta = Resolucion::model()->find('idTorneo=:idTorneo 
                and idUsuario=:idUsuario and idProblema=:idProblema and idEstado=2', array(':idTorneo' => $Resolucion->idTorneo,
                    ':idUsuario' => $Resolucion->idUsuario,
                    ':idProblema' => $Resolucion->idProblema));
                if (count($ResolucionCorrecta) > 0) {
                    $idEstado+=8; //problema ya solucionado
                }
            }
            
            /*
             * Si es la primera vez que se resuelve el problema
             * Se actualiza la Tabla sumando un punto y
             * el timestamp de la respuesta en la tabla 
             */
            if($idEstado==2){
                $torneoUsuario=TorneoUsuario::model()->find('idTorneo=:idTorneo 
                and idUsuario=:idUsuario', array(':idTorneo' => $Resolucion->idTorneo,
                    ':idUsuario' => $Resolucion->idUsuario));
                if($torneoUsuario){
                    $torneoUsuario->Puntos++;
                    $torneoUsuario->Tiempo=$tiempoActual;
                    $torneoUsuario->save();
                    /*
                     * :todo manejar el error
                     */
                }else{
                    /**
                     * :todo manejar el error
                     */
                }
            }
            //TorneoUsuario::model()->find($ResolucionCorrecta, $respuesta);


            $Resolucion->idEstado = $idEstado;
            $Resolucion->Respuesta = $solucion;
            $Resolucion->FechaRespuesta = $tiempoActual;
            if ($Resolucion->update()) {
                $respuesta['codigo'] = $idEstado;
                $respuesta['mensaje'] = $Resolucion->idEstado0->Estado;
                $respuesta['tiempoSolicitud'] = $Resolucion->FechaSolicitud;
                $respuesta['tiempoRespuesta'] = $Resolucion->FechaRespuesta;
                $respuesta['tiempo'] = $tiempo;
            } else {
                $respuesta['error'] = 'al actualizar';
            }
        }
        echo json_encode($respuesta);
        exit;
    }

    // Uncomment the following methods and override them if needed
    /*
      public function filters()
      {
      // return the filter configuration for this controller, e.g.:
      return array(
      'inlineFilterName',
      array(
      'class'=>'path.to.FilterClass',
      'propertyName'=>'propertyValue',
      ),
      );
      }

      public function actions()
      {
      // return external action classes, e.g.:
      return array(
      'action1'=>'path.to.ActionClass',
      'action2'=>array(
      'class'=>'path.to.AnotherActionClass',
      'propertyName'=>'propertyValue',
      ),
      );
      }
     */
}