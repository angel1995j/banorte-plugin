package com.cifrado.controller;

import com.cifrado.model.RequestCifradoBO;
import com.cifrado.model.ResponseCifradoBO;
import com.cifrado.utils.CifradoAES;
import com.cifrado.utils.CifradoRSA;
import com.cifrado.model.RequestDescifradoBO;
import com.cifrado.model.ResponseDescifradoBO;

import org.apache.commons.codec.binary.Base64;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RestController;

@RestController
public class CifradoController
{
	
  protected final Logger logger = LoggerFactory.getLogger(getClass());
  
  @PostMapping(value = {"/wsCifrado"}, produces = {"application/json"}, consumes = {"application/json"})
  public ResponseCifradoBO cifrarInformacion(@RequestBody RequestCifradoBO request) {
    ResponseCifradoBO responseBO;

    String llavePublica = request.getPubKeyStrCert();
    String cadenaACifrarEnBase64 = request.getBase64();
    
    CifradoAES cifradoaes = CifradoAES.getInstance();
    String ivHex = CifradoAES.createInitializationVector();
    String saltHex = CifradoAES.createSalt();
    String passPhrase = CifradoAES.createRandomKey();
    
    try {
      System.out.println("");
      System.out.println("----------------------------------------------------------------------");
      System.out.println("----------------------------- wsCifrado ------------------------------");
      System.out.println("----------------------------------------------------------------------");
      System.out.println("");
      this.logger.debug("Obteniendo Base64 (JSON Datos) [{}]... ", cadenaACifrarEnBase64);

      String cadenaACifrarDecodificada = new String(Base64.decodeBase64(cadenaACifrarEnBase64.getBytes()));;

      this.logger.debug("Resultado Decodificado de Base64 (JSON Datos) [{}]\n", cadenaACifrarDecodificada);


      this.logger.debug("1.- Se cifrará JSON Datos con la llave pública -> [{}]", llavePublica);
      
      String cadenaCifradaAES = cifradoaes.encrypt(saltHex, ivHex, passPhrase, cadenaACifrarDecodificada);

      this.logger.debug("2.- Resultado cifrado AES ( Subcadena2 ) [{}]", cadenaCifradaAES);
      
      String cadenaDescifradaAES = cifradoaes.decrypt(saltHex, ivHex, passPhrase, cadenaCifradaAES);
      
      this.logger.debug("3.- Prueba decifrado AES (JSON Datos) [{}]", cadenaDescifradaAES);
      
      StringBuilder aesKey = (new StringBuilder()).append(ivHex).append("::").append(saltHex).append("::").append(passPhrase);

      System.out.println("");
      System.out.println("------------ JSON a utilizar en el servicio de Descifrado ------------");
      String llavesAES = "{\r\n" + 
        		"    \"vi\": \""+ ivHex + "\",\r\n" + 
          		"    \"salt\": \"" + saltHex + "\",\r\n" + 
          		"    \"passphrase\": \"" + passPhrase + "\",\r\n" + 
          		"    \"cypherData\": \"Sustituir_por_el_valor_de_cadena_cifrada_(data)_de_la_respuesta_de_VCE\"\r\n" + 
          		"}";
      System.out.println(llavesAES);
      System.out.println("----------------------------------------------------------------------");
      System.out.println("");

      this.logger.debug("4.- AES Llaves Generada [{}]", aesKey);
      this.logger.debug("-");
      
      String cadenaCifradaRSA = CifradoRSA.encrypt(aesKey.toString(), llavePublica);
      this.logger.debug("5.- Resultado cifrado de Llaves AES ( Subcadena1 ) [{}]", cadenaCifradaRSA);

      StringBuilder cadenaParaOrquestador = (new StringBuilder()).append(cadenaCifradaRSA).append(":::").append(cadenaCifradaAES);
      
      this.logger.debug("Cadena para enviar al Orquestador (Subcadena1:::Subcadena2) [{}]", cadenaParaOrquestador);
 
      String respuesta = "{" + 
        		"\"vi\": \""+ ivHex + "\"," + 
        		"\"salt\": \"" + saltHex + "\"," + 
        		"\"passphrase\": \"" + passPhrase + "\"," + 
        		"\"data\": \"" + cadenaParaOrquestador + "\"" + 
        		"}";
      
      responseBO = new ResponseCifradoBO("100", "200", "OK", respuesta);
    
    }
    catch (Exception e) {
      responseBO = new ResponseCifradoBO(null, "500", "No Procesado", null);
      this.logger.error("Ocurrio un error General [{}]", e);
    } 
    return responseBO;
  }
  
  @PostMapping(value = {"/wsDescifrado"}, produces = {"application/json"}, consumes = {"application/json"})
  public ResponseDescifradoBO descifrarInformacion(@RequestBody RequestDescifradoBO request) {
    ResponseDescifradoBO responseBO;
    this.logger.debug("Request [{}]", request.getPassphrase());
    
    String serviceId = "100";
    
    CifradoAES helper = CifradoAES.getInstance();

    
    try {
        System.out.println("");
    	System.out.println("----------------------------------------------------------------------");
    	System.out.println("---------------------------- wsDescifrado ----------------------------");
    	System.out.println("----------------------------------------------------------------------");
    	System.out.println("");
      this.logger.debug("1.- Información a descifrar (JSON Datos) [{}]... ", request.getCypherData());
      this.logger.debug("2.- Llaves para descifrado: Vi [{}], Salt [{}], PassPhrase [{}] ... ", new Object[] { request.getVi(), request.getSalt(), request.getPassphrase() });
      
      String descifradoAES = helper.decrypt(request.getSalt(), request.getVi(), request.getPassphrase(), request.getCypherData());
      this.logger.debug("3.- Resultado decifrado AES: [{}]", descifradoAES);
      
      responseBO = new ResponseDescifradoBO("100", "200", "OK", descifradoAES);
    
    }
    catch (Exception e) {
      responseBO = new ResponseDescifradoBO(null, "500", "No Procesado", null);
      this.logger.error("Ocurrio un error General [{}]", e);
    } 
    return responseBO;
  }
}
