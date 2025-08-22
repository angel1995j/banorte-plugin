package com.cifrado.logger;

import java.net.URI;
import org.apache.logging.log4j.Level;
import org.apache.logging.log4j.core.LoggerContext;
import org.apache.logging.log4j.core.appender.ConsoleAppender;
import org.apache.logging.log4j.core.config.Configuration;
import org.apache.logging.log4j.core.config.ConfigurationFactory;
import org.apache.logging.log4j.core.config.ConfigurationSource;
import org.apache.logging.log4j.core.config.Order;
import org.apache.logging.log4j.core.config.builder.api.AppenderComponentBuilder;
import org.apache.logging.log4j.core.config.builder.api.ComponentBuilder;
import org.apache.logging.log4j.core.config.builder.api.ConfigurationBuilder;
import org.apache.logging.log4j.core.config.builder.api.LayoutComponentBuilder;
import org.apache.logging.log4j.core.config.builder.api.LoggerComponentBuilder;
import org.apache.logging.log4j.core.config.builder.api.RootLoggerComponentBuilder;
import org.apache.logging.log4j.core.config.builder.impl.BuiltConfiguration;
import org.apache.logging.log4j.core.config.plugins.Plugin;

@Plugin(name = "ConfigurationBaseLogger", category = "ConfigurationFactory")
@Order(50)
public class InitLogger
  extends ConfigurationFactory {
  private static String logPath = "";
  private static String logName = "CifradoComponent";
  
  private static String maxSize = "500000";
  
  private static String level = "DEBUG";
  
  public static Configuration createConfiguration(String name, ConfigurationBuilder<BuiltConfiguration> builder) {
    builder.setConfigurationName(name);
    builder.setStatusLevel(Level.ERROR);
    
    AppenderComponentBuilder appenderBuilder = (AppenderComponentBuilder)builder.newAppender("STDOUT", "CONSOLE").addAttribute("target", (Enum)ConsoleAppender.Target.SYSTEM_OUT);
    appenderBuilder.add((LayoutComponentBuilder)((LayoutComponentBuilder)builder.newLayout("PatternLayout")
        .addAttribute("pattern", "%d [%t] - [%-5level] - %logger: %msg%n"))
        .addAttribute("disableAnsi", false));
    
    builder.add(appenderBuilder);

    LayoutComponentBuilder layoutBuilder = (LayoutComponentBuilder)builder.newLayout("PatternLayout").addAttribute("pattern", "%d [%t] - [%-5level] - %logger: %msg%n");
    
    ComponentBuilder triggeringPolicy = builder.newComponent("Policies").addComponent(builder.newComponent("SizeBasedTriggeringPolicy").addAttribute("size", maxSize));
    
    appenderBuilder = (AppenderComponentBuilder)((AppenderComponentBuilder)((AppenderComponentBuilder)((AppenderComponentBuilder)((AppenderComponentBuilder)builder.newAppender("ROLLING", "RollingFile").addAttribute("fileName", logPath + logName + ".log")).addAttribute("append", true)).addAttribute("bufferedIO", false)).addAttribute("filePattern", "-%d{yyyy-MM-dd}-%i.log.gz")).add(layoutBuilder).addComponent(triggeringPolicy);
    builder.add(appenderBuilder);
    
    builder.add((LoggerComponentBuilder)((LoggerComponentBuilder)((LoggerComponentBuilder)builder.newLogger("com.cifrado", level)
        .add(builder.newAppenderRef("ROLLING")))
        .add(builder.newAppenderRef("STDOUT")))
        .addAttribute("additivity", false));
    builder.add((RootLoggerComponentBuilder)builder.newRootLogger(Level.ERROR).add(builder.newAppenderRef("STDOUT")));
    return (Configuration)builder.build();
  }

  
  public Configuration getConfiguration(LoggerContext loggerContext, ConfigurationSource source) {
    return getConfiguration(loggerContext, source.toString(), null);
  }

  
  public Configuration getConfiguration(LoggerContext loggerContext, String name, URI configLocation) {
    ConfigurationBuilder<BuiltConfiguration> builder = newConfigurationBuilder();
    return createConfiguration(name, builder);
  }

  
  protected String[] getSupportedTypes() {
    return new String[] { "*" };
  }
}

