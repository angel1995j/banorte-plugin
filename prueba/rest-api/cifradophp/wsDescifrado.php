<?php

	header("Access-Control-Allow-Origin: *");

	$method = $_SERVER['REQUEST_METHOD'];

	function wh_log($log_msg)
	{
		$log_filename = "log_cifradophp";
		if (!file_exists($log_filename)) 
		{
			// Crea directorio/archivo en caso de que no existe.
			mkdir($log_filename, 0777, true);
		}
		$log_file_data = $log_filename.'/log_wsDescifrado_' . date('Y_m_d') . '.log';
		$now = DateTime::createFromFormat('U.u', microtime(true));

		// Si no se añade `FILE_APPEND`, el archivo va a ser borrado cada que se envíe un mensaje
		file_put_contents($log_file_data, $now->format("Y-m-d H:i:s.u") . " " . $log_msg . "\n", FILE_APPEND);
	} 

	function getDescifradoJavaCurl($dataJSON)
	{
		$method = "POST";
		$url = "http://localhost:8888/wsDescifrado";
		wh_log("URL servicio descifrado Java: $url");
		$data = $dataJSON;
		
		$curl = curl_init();
		
	   switch ($method){
		  case "POST":
			 curl_setopt($curl, CURLOPT_POST, 1);
			 if ($data)
				curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
			 break;
		  case "PUT":
			 curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
			 if ($data)
				curl_setopt($curl, CURLOPT_POSTFIELDS, $data);			 					
			 break;
		  default:
			 if ($data)
				$url = sprintf("%s?%s", $url, http_build_query($data));
	   }

	   // OPTIONS:
	   curl_setopt($curl, CURLOPT_URL, $url);
	   curl_setopt($curl, CURLOPT_HTTPHEADER, array(
		  'APIKEY: 111111111111111111111',
		  'Content-Type: application/json',
	   ));
	   curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	   curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	   // EXECUTE:
	   $result = curl_exec($curl);
	   if(!$result){
		wh_log('{ "error": "Error de conexión al invocar servicio descifrado Java, url: ' . $url . '" }');
		die('{ "error": "Error de conexión al invocar servicio descifrado Java, url: ' . $url . '" }');
   }
	   curl_close($curl);

	   return $result;
	}

	wh_log("");
	wh_log("Inicio proceso cifrado PHP.");

	// Se obtiene el JSON del body
	$json = file_get_contents('php://input');

	wh_log("Datos recibidos: $json");

	// Se convierte en un objeto JSON de PHP
	$data = json_decode($json);

	$vi = $data->{'vi'};
	$salt = $data->{'salt'};
	$passphrase = $data->{'passphrase'};
	$cypherData = $data->{'cypherData'};

	wh_log("vi: $vi");
	wh_log("salt: $salt");
	wh_log("passphrase: $passphrase");
	wh_log("cypherData: $cypherData");

	// Se invoca el servicio para cifrado en Java.
	$resultadoDescifrado = getDescifradoJavaCurl($data);

	$resultado = json_decode($resultadoDescifrado);
	$cadenaDescifrada = $resultado->{'data'}[0];

	wh_log("cypherData descifrada: $cadenaDescifrada");

	echo $resultadoDescifrado;


?>