<?php
require_once './hornero.php';
/*
modificar el token que le corresponde al equipo para el torneo
y el numero de Problema a Trabajar
*/
$token='66243869015cc19c8ac2fa973b8d7111';
$problema=1;
$hornero=new Hornero($problema,$token);
$parametros=$hornero->solicitud();
/*******************************************************************
Resolver el ejercicio tomando los parámetros del array parametros
y asingar la respuesta a la variable respuesta
*/

$x=$parametros[0];
$y=$parametros[1];
$respuesta= $x+$y;

/*******************************************************************
Se envía la respuesta al servidor hornero NO TOCAR!!!!*/
$respuesta=$hornero->respuesta($respuesta);

print_r($respuesta);
