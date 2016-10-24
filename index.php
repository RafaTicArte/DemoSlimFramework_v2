<?php
/**
 * index.php
 * 
 * Copyright 2016 TicArte <rafa@ticarte.com>
 *
 * Demo sobre el funcionamiento de SlimFramework v2
 * 
 **/

/** 
 * Función de conexión a la base de datos 
 */
function connectionDB() {
   // Datos
   $dbhost = "localhost";
   $dbuser = "root";
   $dbpass = "";
   $dbname = "appcontecimientos";
   
   try {
      // Inicia conexión a la base de datos
      $dbcon = new PDO("mysql:host=$dbhost; dbname=$dbname", $dbuser, $dbpass);
      // Activa las excepciones en el controlador PDO
      $dbcon->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      // Fuerza la codificación de caracteres a UTF8
      $dbcon->exec("SET NAMES 'utf8'");
      // Devuelve la conexión
      return $dbcon;
   } catch (PDOException $e) {
      // Muestra el error de conexión
      echo '{"error":-1, "message": "Error de conexión con la base de datos: '.$e->getMessage().'"}';
      return null;
   }
}

// Registra la librería Slim
require 'Slim/Slim.php';
\Slim\Slim::registerAutoloader();

// Crea la aplicación con el servidor REST
$app = new \Slim\Slim();

// Deshabilita el modo depuración. Activar con el servidor REST en producción
// $app->config('debug', false);

// Inserta en la cabecera de las respuesta de las operaciones GET el tipo de contenido
$app->response->headers->set('Content-Type', 'application/json; charset=utf-8');

/**
 * Operación GET de recuperación de un recurso mediante su identificador
 */
$app->get('/acontecimiento/:param_id', function ($param_id) {
   // Comprueba el parámetro de entrada
   $param_id = intval($param_id);
   
   // Sentencias SQL
   $sql_acontecimiento = "SELECT * FROM acontecimientos WHERE id=:bind_id";
   $sql_eventos = "SELECT * FROM eventos WHERE id_acontecimiento=:bind_id";

   try{
      // Conecta con la base de datos
      $db = connectionDB();
      
      if ($db != null){
         // Prepara y ejecuta la sentencia
         $stmt_acontecimiento = $db->prepare($sql_acontecimiento);
         $stmt_acontecimiento->bindParam("bind_id", $param_id);
         $stmt_acontecimiento->execute();
         
         // Obtiene un array asociativo con un registro y elimina los valores vacíos
         $record_acontecimiento = array_filter($stmt_acontecimiento->fetch(PDO::FETCH_ASSOC));
         
         if ($record_acontecimiento != false){
            $output = '{"acontecimiento":';
            
            // Convierte el array a formato JSON con caracteres Unicode y modo tabulado
            // Deshabilitar JSON_PRETTY_PRINT con el servidor REST en producción
            $output .= json_encode($record_acontecimiento, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            // Prepara y ejecuta la sentencia
            $stmt_eventos = $db->prepare($sql_eventos);
            $stmt_eventos->bindParam("bind_id", $param_id);
            $stmt_eventos->execute();

            // Obtiene uno a uno los registros para eliminar los valores vacíos en ellos
            $record_eventos = array();
            while ($record = $stmt_eventos->fetch(PDO::FETCH_ASSOC))
               array_push($record_eventos, array_filter($record));
            
            if (sizeof($record_eventos) != 0){
               $output .= ',"eventos":';
               
               // Convierte el array a formato JSON con caracteres Unicode y modo tabulado
               // Deshabilitar JSON_PRETTY_PRINT con el servidor REST en producción
               $output .= json_encode($record_eventos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }

            $output .= '}';
            
            echo $output;
         } else {
            echo '{"error": -11, "message": "El acontecimiento no existe"}';
         }

         // Cierra la conexión con la base de datos
         $db = null;
      }
   } catch (PDOException $e) {
      echo '{"error": -9, "message": "Excepción en la base de datos: '.$e->getMessage().'"}';
   }
});

/**
 * Operación POST de inserción de un recurso
 */
$app->post('/acontecimiento/add', function () {
   // Obtiene la petición que ha recibido el servidor REST
   $request = \Slim\Slim::getInstance()->request();

   // Obtiene el body de la petición recibida
   $request_body = $request->getBody();

   // Transforma el contenido JSON del body en un array
   $acontecimiento = json_decode($request_body, true, 10);
   
   // Comprueba los errores en el contenido JSON
   if (json_last_error() != JSON_ERROR_NONE) {
      echo '{"error":-21, "message": "Contenido JSON con errores"}';
   } else {
      // Comprueba los valores del contenido JSON
      $acontecimiento['nombre'] = (isset($acontecimiento['nombre'])) ? $acontecimiento['nombre'] : '';
      
      // Sentencias SQL
      $sql_insert = "INSERT INTO acontecimientos (nombre) VALUES (:bind_nombre)";
   
      try {
         // Conecta con la base de datos
         $db = connectionDB();
         
         if ($db != null){
            // Prepara y ejecuta de la sentencia
            $stmt_insert = $db->prepare($sql_insert);
            $stmt_insert->bindParam("bind_nombre", $acontecimiento['nombre']);
            $stmt_insert->execute();

            echo '{"error": 1, "message": "Acontecimiento insertado correctamente con el id '.$db->lastInsertId().'"}';
            
            // Cierra la conexión con la base de datos
            $db = null;
         }
      } catch(PDOException $e) {
         echo '{"error": -9, "message": "Excepción en la base de datos: '.$e->getMessage().'"}';
      }
   }
});

// Inicia la aplicación con el servidor REST 
$app->run();

?>
