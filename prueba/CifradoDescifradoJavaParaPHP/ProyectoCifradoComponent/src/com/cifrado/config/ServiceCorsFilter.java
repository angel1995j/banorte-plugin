package com.cifrado.config;

import org.springframework.stereotype.Component;
import org.springframework.web.servlet.config.annotation.CorsRegistry;
import org.springframework.web.servlet.config.annotation.WebMvcConfigurer;




@Component
public class ServiceCorsFilter
  implements WebMvcConfigurer
{
  public void addCorsMappings(CorsRegistry registry) {
    registry.addMapping("/**")
      .allowedOrigins(new String[] { "*" })
      .allowedMethods(new String[] { "GET", "POST", "PUT", "DELETE", "HEAD" });
  }
}
