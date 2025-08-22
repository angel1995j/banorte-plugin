package com.cifrado.utils;

import java.io.ByteArrayInputStream;
import java.io.InputStream;
import java.nio.charset.StandardCharsets;
import java.security.InvalidKeyException;
import java.security.KeyFactory;
import java.security.NoSuchAlgorithmException;
import java.security.NoSuchProviderException;
import java.security.PublicKey;
import java.security.cert.CertificateFactory;
import java.security.cert.X509Certificate;
import java.security.spec.X509EncodedKeySpec;

import javax.crypto.BadPaddingException;
import javax.crypto.Cipher;
import javax.crypto.IllegalBlockSizeException;
import javax.crypto.NoSuchPaddingException;
import org.apache.commons.codec.binary.Base64;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

public class CifradoRSA
{
  private static Logger logger = LoggerFactory.getLogger(com.cifrado.utils.CifradoRSA.class);
  
  public static String encrypt(String plainText, String publicKey) {
    try {
      Cipher encryptCipher = Cipher.getInstance("RSA/ECB/OAEPWITHSHA-256ANDMGF1PADDING", "SunJCE");
      
      
      byte[] b64decoded = Base64.decodeBase64(publicKey);
      CertificateFactory certFactory = CertificateFactory.getInstance("X.509");
      InputStream in = new ByteArrayInputStream(b64decoded);
      X509Certificate certificate = (X509Certificate)certFactory.generateCertificate(in);
      
      encryptCipher.init(1, certificate.getPublicKey());
      
      byte[] encryptedData = encryptCipher.doFinal(plainText.getBytes(StandardCharsets.UTF_8));
      String encodeToString = Base64.encodeBase64String(encryptedData);
      
      logger.debug("RSA Encrypt Provider: [{}], [{}]", new Object[] { encryptCipher.getProvider().getName(), encryptCipher.getAlgorithm() });
      return encodeToString;
    } catch (InvalidKeyException e) {
      logger.error("Llave RSA invalida", e);
    } catch (NoSuchAlgorithmException e) {
      logger.error("No es posible utilizar el algoritmo especificado RSA", e);
    } catch (NoSuchProviderException e) {
      logger.error("No es posible Localizar el proveedor RSA especificado", e);
    } catch (NoSuchPaddingException e) {
      logger.error("No es posible Localizar el padding especificado", e);
    } catch (IllegalBlockSizeException e) {
      logger.error("Tamaño de bloque ilegal", e);
    } catch (BadPaddingException e) {
      logger.error("Padding incorrecto", e);
    } catch (Exception e) {
      e.printStackTrace();
    } 
    return null;
  }
}
