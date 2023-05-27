/** 
 * Jdk platform : 1.8 
 */

/** 
 * SVN version 10
 */

package com.maarch;

import java.io.File;
import java.io.IOException;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.logging.FileHandler;
import java.util.logging.Level;
import java.util.logging.Logger;
import java.util.logging.SimpleFormatter;

/**
 * MyLogger class manages the log of the applet
 * @author Laurent Giovannoni
 */
public class MyLogger {
    
    private String loggerFile;
    private FileHandler fh;
    private Logger logger;

    /**
    * Prepares the log
    * @param pathTologs path to the log file in the tmp dir
    */
    MyLogger(String pathTologs) {
        String userLocalDir = System.getProperty("user.home");
        pathTologs = userLocalDir + File.separator + "maarchTmp" + File.separator + "logs";
        File dir = new File(userLocalDir + File.separator + "maarchTmp");
         if (dir.mkdir()) {
            System.out.println("Directory: " + dir + " created");
        } else {
            System.out.println("Directory: " + dir + " not created");
        }
        dir = new File(userLocalDir + File.separator + "maarchTmp" + File.separator + "logs");
         if (dir.mkdir()) {
            System.out.println("Directory: " + dir + " created");
        } else {
            System.out.println("Directory: " + dir + " not created");
        }
        Date date = new Date() ;
        SimpleDateFormat dateFormat = new SimpleDateFormat("yyyy_MM_dd") ;
        this.loggerFile = pathTologs + File.separator + "maarchCM_"+dateFormat.format(date)+".log";
        this.logger = Logger.getLogger("maarchCM");
        try {
            // This block configure the logger with handler and formatter
            this.fh = new FileHandler(this.loggerFile, true);
            this.logger.addHandler(this.fh);
            this.logger.setLevel(Level.ALL);
            SimpleFormatter formatter = new SimpleFormatter();
            this.fh.setFormatter(formatter);
            // the following statement is used to log any messages   
            this.logger.log(Level.INFO,"\n\n**** LAUNCHING APPLET ****");
        } catch (SecurityException e) {
            System.out.println(e);
        } catch (IOException e) {
            System.out.println(e);
        }
    }
    
    /**
    * Writes the log
    * @param message message to write in the log file
    * @param level level of the message
    */
    public void log(String message, Level level) {
        this.logger.log(level, message);
    }
    
    public void close() {
        this.fh.close();
    }
}