package com.cifrado;

import com.cifrado.logger.InitLogger;
import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.context.annotation.Import;

@SpringBootApplication
@Import({InitLogger.class})
public class PruebaServicioCifrado
{
  public static void main(String[] args) {
    SpringApplication.run(PruebaServicioCifrado.class, args);
  }
}
