package com.cifrado.model;

import java.io.Serializable;

public class RequestCifradoBO
  implements Serializable
{
  private static final long serialVersionUID = 7830454519253990496L;
  private String base64;
  private String pubKeyStrCert;
  
  public String getBase64() {
    return this.base64;
  }
  
  public void setBase64(String base64) {
    this.base64 = base64;
  }
  
  public String getPubKeyStrCert() {
    return this.pubKeyStrCert;
  }
  
  public void setPubKeyStrCert(String pubKeyStrCert) {
    this.pubKeyStrCert = pubKeyStrCert;
  }
}

