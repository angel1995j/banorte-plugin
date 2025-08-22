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
		$log_file_data = $log_filename.'/log_wsCifrado_' . date('Y_m_d') . '.log';
		$now = DateTime::createFromFormat('U.u', microtime(true));

		// Si no se añade `FILE_APPEND`, el archivo va a ser borrado cada que se envíe un mensaje
		file_put_contents($log_file_data, $now->format("Y-m-d H:i:s.u") . " " . $log_msg . "\n", FILE_APPEND);
	} 

	function wh_logSinFecha($log_msg)
	{
		$log_filename = "log_cifradophp";
		if (!file_exists($log_filename)) 
		{
			// Crea directorio/archivo en caso de que no existe.
			mkdir($log_filename, 0777, true);
		}
		$log_file_data = $log_filename.'/log_wsCifrado_' . date('Y_m_d') . '.log';
		$now = DateTime::createFromFormat('U.u', microtime(true));

		// Si no se añade `FILE_APPEND`, el archivo va a ser borrado cada que se envíe un mensaje
		file_put_contents($log_file_data, $log_msg . "\n", FILE_APPEND);
	} 

	function getCifradoJavaCurl($dataJSON)
	{
		$method = "POST";
		$url = "http://localhost:8888/wsCifrado";
		$data = $dataJSON;
		wh_log("URL servicio cifrado Java: $url");
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
			wh_log('{ "error": "Error de conexión al invocar servicio cifrado Java, url: ' . $url . '" }');
			die('{ "error": "Error de conexión al invocar servicio cifrado Java, url: ' . $url . '" }');
	   }
	   curl_close($curl);

	   wh_log("Resultado servicio cifrado Java: $result");
	   return $result;
	}

	wh_log("");
	wh_log("Inicio proceso cifrado PHP.");

	// Se obtiene el JSON del body
	$json = file_get_contents('php://input');

	wh_log("Datos recibidos: $json");

	// Se convierte en un objeto JSON de PHP
	$data = json_decode($json);

	$base64Decodificado = base64_decode($data->{'base64'});
	$llavePublica = $data->{'pubKeyStrCert'};

	wh_log("base64 decodificado: $base64Decodificado");
	wh_log("Llave pública: $llavePublica");

	// Se invoca el servicio para cifrado en Java.
	$respuestaSvcCifrado = getCifradoJavaCurl($data);

	$jsonrespuestaSvcCifrado = json_decode($respuestaSvcCifrado);

	$datosResultado = json_decode($jsonrespuestaSvcCifrado->{'data'}[0]);

	// Se obtienen las llaves para descifrar respuesta de VCE
	$vi = $datosResultado->{'vi'};
	$salt = $datosResultado->{'salt'};
	$passphrase = $datosResultado->{'passphrase'};

	wh_logSinFecha("");
	wh_logSinFecha("------------ JSON a utilizar en el servicio de Descifrado ------------");
	wh_logSinFecha("{");
	wh_logSinFecha("    \"vi\": \"$vi\",");
	wh_logSinFecha("    \"salt\": \"$salt\",");
	wh_logSinFecha("    \"passphrase\": \"$passphrase\",");
    wh_logSinFecha("    \"cypherData\": \"Sustituir_por_el_valor_de_cadena_cifrada_(data)_de_la_respuesta_de_VCE\"");
	wh_logSinFecha("}");
	wh_logSinFecha("----------------------------------------------------------------------");
	wh_logSinFecha("");

	wh_log("AES Llaves Generada[$vi::$salt::$passphrase]");

	// Se obtiene la cadena para enviar al orquestador
	$cadenaParaVCE = $datosResultado->{'data'};

	wh_log("Cadena para enviar al Orquestador VCE (Subcadena1:::Subcadena2): $cadenaParaVCE");

	// Se eliminan llaves de la respuesta, no se deben de enviar al front.
	$jsonrespuestaSvcCifrado->{'data'}[0] = $cadenaParaVCE;

	$respuesta = json_encode($jsonrespuestaSvcCifrado);

	echo $respuesta;


?>