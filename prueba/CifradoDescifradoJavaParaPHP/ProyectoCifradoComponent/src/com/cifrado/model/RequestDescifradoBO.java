package com.cifrado.model;

import java.io.Serializable;


public class RequestDescifradoBO
  implements Serializable
{
  private static final long serialVersionUID = 7830454519253990496L;
  private String vi;
  private String salt;
  private String passphrase;
  private String cypherData;
  
  public String getVi() {
    return this.vi;
  }
  
  public void setVi(String vi) {
    this.vi = vi;
  }
  
  public String getSalt() {
    return this.salt;
  }
  
  public void setSalt(String salt) {
    this.salt = salt;
  }
  
  public String getPassphrase() {
    return this.passphrase;
  }
  
  public void setPassphrase(String passphrase) {
    this.passphrase = passphrase;
  }
  
  public String getCypherData() {
    return this.cypherData;
  }
  
  public void setCypherData(String cypherData) {
    this.cypherData = cypherData;
  }
}
