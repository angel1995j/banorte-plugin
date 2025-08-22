package com.cifrado.utils;

import java.nio.charset.StandardCharsets;
import java.security.GeneralSecurityException;
import java.security.InvalidKeyException;
import java.security.NoSuchAlgorithmException;
import java.security.SecureRandom;
import java.security.spec.KeySpec;
import java.util.Random;
import javax.crypto.Cipher;
import javax.crypto.SecretKey;
import javax.crypto.SecretKeyFactory;
import javax.crypto.spec.IvParameterSpec;
import javax.crypto.spec.PBEKeySpec;
import javax.crypto.spec.SecretKeySpec;
import org.apache.commons.codec.DecoderException;
import org.apache.commons.codec.binary.Base64;
import org.apache.commons.codec.binary.Hex;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;


public class CifradoAES
{
  protected static final Logger logger = LoggerFactory.getLogger(com.cifrado.utils.CifradoAES.class);
  
  private final int keySize;
  
  private final int iterationCount;
  private final Cipher cipher;
  private static com.cifrado.utils.CifradoAES instance;
  
  public static com.cifrado.utils.CifradoAES getInstance() {
    if (instance == null) {
      instance = new com.cifrado.utils.CifradoAES(128, 1000);
    }
    return instance;
  }
  
  private CifradoAES(int keySize, int iterationCount) {
    this.keySize = keySize;
    this.iterationCount = iterationCount;
    try {
      this.cipher = Cipher.getInstance("AES/CTR/NoPadding");
    }
    catch (NoSuchAlgorithmException|javax.crypto.NoSuchPaddingException e) {
      throw fail(e);
    } 
  }
  
  public String encrypt(String salt, String iv, String passphrase, String plaintext) {
    SecretKey key = generateKey(salt, passphrase);
    byte[] encrypted = doFinal(1, key, iv, plaintext.getBytes(StandardCharsets.UTF_8));
    return base64(encrypted);
  }
  
  public String decrypt(String salt, String iv, String passphrase, String ciphertext) {
    try {
      SecretKey key = generateKey(salt, passphrase);
      byte[] decrypted = doFinal(2, key, iv, base64(ciphertext));
      return new String(decrypted, StandardCharsets.UTF_8);
    } catch (Exception e) {
      return null;
    } 
  }
  
  private byte[] doFinal(int encryptMode, SecretKey key, String iv, byte[] bytes) {
    try {
      this.cipher.init(encryptMode, key, new IvParameterSpec(hex(iv)));
      return this.cipher.doFinal(bytes);
    }
    catch (InvalidKeyException|java.security.InvalidAlgorithmParameterException|javax.crypto.IllegalBlockSizeException|javax.crypto.BadPaddingException e) {
      return null;
    } 
  }
  
  private SecretKey generateKey(String salt, String passphrase) {
    try {
      SecretKeyFactory factory = SecretKeyFactory.getInstance("PBKDF2WithHmacSHA1");
      KeySpec spec = new PBEKeySpec(passphrase.toCharArray(), hex(salt), this.iterationCount, this.keySize);
      SecretKey key = new SecretKeySpec(factory.generateSecret(spec).getEncoded(), "AES");
      
      return key;
    }
    catch (NoSuchAlgorithmException|java.security.spec.InvalidKeySpecException e) {
      return null;
    } 
  }
  
  public static String random(int length) {
    byte[] salt = new byte[length];
    (new SecureRandom()).nextBytes(salt);
    return hex(salt);
  }
  
  public static String base64(byte[] bytes) {
    return Base64.encodeBase64String(bytes);
  }
  
  public static byte[] base64(String str) {
    return Base64.decodeBase64(str);
  }
  
  public static String hex(byte[] bytes) {
    return Hex.encodeHexString(bytes);
  }
  
  public static byte[] hex(String str) {
    try {
      return Hex.decodeHex(str.toCharArray());
    }
    catch (DecoderException e) {
      throw new IllegalStateException(e);
    } 
  }
  
  private IllegalStateException fail(Exception e) {
    return null;
  }
  
  public static String createInitializationVector() {
	String cadenaRandom = "0123456789abcdefghiklmnopqrstuvwxyz";
    StringBuilder iv = new StringBuilder();
    Random rnd = new Random();
    while (iv.length() < 16) {
      int index = (int)(rnd.nextFloat() * cadenaRandom.length());
      iv.append(cadenaRandom.charAt(index));
    } 
    char[] ivString = Hex.encodeHex(iv.toString().getBytes(StandardCharsets.UTF_8));
    return String.valueOf(ivString);
  }


  
  public static String createSalt() {
	String ramdom = "0123456789abcdefghiklmnopqrstuvwxyz";
    StringBuilder salt = new StringBuilder();
    Random rnd = new Random();
    while (salt.length() < 16) {
      int index = (int)(rnd.nextFloat() * ramdom.length());
      salt.append(ramdom.charAt(index));
    } 
    char[] saltString = Hex.encodeHex(salt.toString().getBytes(StandardCharsets.UTF_8));
    return String.valueOf(saltString);
  }

  
  public static String createRandomKey() {
    String ramdomkey = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz*&-%/!?*+=()";
    StringBuilder keyRnd = new StringBuilder();
    Random rnd = new Random();
    while (keyRnd.length() < 16) {
      int index = (int)(rnd.nextFloat() * ramdomkey.length());
      keyRnd.append(ramdomkey.charAt(index));
    } 
    return keyRnd.toString();
  }
}
