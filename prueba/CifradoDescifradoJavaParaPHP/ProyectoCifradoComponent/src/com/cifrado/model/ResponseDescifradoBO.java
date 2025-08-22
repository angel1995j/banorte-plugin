package com.cifrado.model;

import com.fasterxml.jackson.annotation.JsonCreator;
import com.fasterxml.jackson.annotation.JsonInclude;
import com.fasterxml.jackson.annotation.JsonProperty;
import com.fasterxml.jackson.core.JsonProcessingException;
import com.fasterxml.jackson.databind.ObjectMapper;
import java.io.Serializable;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

@JsonInclude(JsonInclude.Include.NON_NULL)
public class ResponseDescifradoBO
  implements Serializable
{
  private static final long serialVersionUID = 7830454519253990496L;
  @JsonProperty("responseId")
  private String responseId;
  @JsonProperty("code")
  private String code;
  @JsonProperty("message")
  private String message;
  @JsonProperty("data")
  private List<Object> data;
  
  public ResponseDescifradoBO(String responseId, String code, String message, Object data) {
    this.responseId = responseId;
    this.code = code;
    this.message = message;
    this.data = toJsonList(data);
  }
  
  @JsonCreator
  public ResponseDescifradoBO(@JsonProperty("responseId") String responseId, @JsonProperty("code") String code, @JsonProperty("message") String message, @JsonProperty("data") List<Object> data) {
    this.responseId = responseId;
    this.code = code;
    this.message = message;
    this.data = data;
  }
  
  public String getResponseId() {
    return this.responseId;
  }
  
  public void setResponseId(String responseId) {
    this.responseId = responseId;
  }
  
  public String getCode() {
    return this.code;
  }
  
  public void setCode(String code) {
    this.code = code;
  }
  
  public String getMessage() {
    return this.message;
  }
  
  public void setMessage(String message) {
    this.message = message;
  }
  
  public List<Object> getData() {
    return this.data;
  }
  
  public void setData(List<Object> data) {
    this.data = data;
  }
  
  public String toString() {
    try {
      return (new ObjectMapper()).writerWithDefaultPrettyPrinter().writeValueAsString(this);
    } catch (JsonProcessingException var2) {
      var2.printStackTrace();
      return "";
    } 
  }
  
  private ArrayList<Object> toJsonList(Object obj) {
    return new ArrayList(Arrays.asList(new Object[] { obj }));
  }
}
