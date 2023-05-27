/**
 * Jdk platform : 1.8
 */

/**
 * SVN version 141
 */

package com.maarch;

//import java.applet.Applet;
import java.awt.AWTException;
import java.awt.Image;
import java.awt.MenuItem;
import java.awt.PopupMenu;
import java.awt.SystemTray;
import java.awt.Toolkit;
import java.awt.TrayIcon;
import java.awt.event.ActionEvent;
import java.awt.event.ActionListener;
import java.io.*;
import java.lang.reflect.InvocationTargetException;
import java.net.MalformedURLException;
import java.net.URL;
import java.nio.file.FileSystems;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import static java.nio.file.StandardWatchEventKinds.ENTRY_CREATE;
import static java.nio.file.StandardWatchEventKinds.ENTRY_DELETE;
import static java.nio.file.StandardWatchEventKinds.ENTRY_MODIFY;
import java.nio.file.WatchEvent;
import java.nio.file.WatchKey;
import java.nio.file.WatchService;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.security.PrivilegedActionException;
import java.util.ArrayList;
import java.util.HashSet;
import java.util.Hashtable;
import java.util.Iterator;
import java.util.List;
import java.util.Set;
import java.util.logging.Level;
import java.util.logging.Logger;
//import javax.swing.JApplet;
import javax.xml.parsers.DocumentBuilder;
import javax.xml.parsers.DocumentBuilderFactory;
import javax.xml.parsers.ParserConfigurationException;

import netscape.javascript.JSException;
import org.apache.http.client.config.CookieSpecs;
import org.apache.http.client.config.RequestConfig;
import org.apache.http.client.methods.CloseableHttpResponse;
import org.apache.http.client.methods.HttpPost;
import org.apache.http.client.protocol.HttpClientContext;
import org.apache.http.entity.AbstractHttpEntity;
import org.apache.http.impl.client.*;
import org.apache.http.impl.cookie.BasicClientCookie;
import org.w3c.dom.Document;
import org.w3c.dom.NodeList;
import org.xml.sax.SAXException;

import org.apache.http.NameValuePair;
import org.apache.http.client.entity.UrlEncodedFormEntity;
import org.apache.http.message.BasicNameValuePair;

/**
 * MaarchCM class manages webservices between end user desktop and Maarch
 * @author maarch and DIS
 */
public class MaarchCM {

    //INIT PARAMETERS
    protected String url;
    protected String idApplet;
    protected String objectType;
    protected String objectTable;
    protected String objectId;
    protected String cookie;
    protected String clientSideCookies;
    protected String uniqueId;
    protected String convertPdf;
    protected String onlyConvert;
    protected String md5File;

    protected String domain;
    protected String userLocalDirTmp;
    protected String userMaarch;
    protected String messageStatus;
    static Hashtable messageResult = new Hashtable();

    //XML PARAMETERS
    protected String status;
    protected String appPath;
    protected String appPath_convert;
    protected String fileContent;
    protected String fileContentVbs;
    protected String vbsPath;
    protected String fileContentExe;
    protected String useExeConvert;
    protected String fileExtension;
    protected String error;
    protected String endMessage;
    protected String os;
    protected String fileContentTosend;
    protected String pdfContentTosend;

    private  final HttpClientContext httpContext = HttpClientContext.create();
    private CloseableHttpClient httpClient; // Apache HttpClient yet to be instantiated

    public MyLogger logger;
    public FileManager fM;
    public String fileToEdit;
    public String editMode;    
    public String programName;
    
    //If the icon is a file
    Image image = Toolkit.getDefaultToolkit().createImage(this.getClass().getResource("logo_only.png"));
    //Alternative (if the icon is on the classpath):

    ActionListener exitListener = new ActionListener() {
        public void actionPerformed(ActionEvent e) {
            System.out.println("Exiting...");
            System.exit(0);
        }
    };
    TrayIcon trayIcon = null;


    
    public List<String> fileToDelete = new ArrayList<String>();
    
    
    public static void main(String[] args) throws JSException, AWTException, InterruptedException, IOException {
            MaarchCM MaarchCM = new MaarchCM();
            MaarchCM.start(args);
    }
    
   
    /**
     * Launch of the JNLP
     */
    public void start(String[] args) throws JSException, AWTException, InterruptedException, IOException {
        
        logger = new MyLogger(userLocalDirTmp + File.separator);
        FileManager.deleteLogsOnDirWithTime(userLocalDirTmp);
        
        if (SystemTray.isSupported()) {
            SystemTray tray = SystemTray.getSystemTray();
            PopupMenu popup = new PopupMenu();
            MenuItem defaultItem = new MenuItem("Fermer l'applet");
            defaultItem.addActionListener(exitListener);
            popup.add(defaultItem);
            trayIcon = new TrayIcon(image, "Tray Demo");
            //Let the system resize the image if needed
            trayIcon.setImageAutoSize(true);
            //Set tooltip text for the tray icon
            trayIcon.setToolTip("Maarch content editor");
            tray.add(trayIcon);

            trayIcon.setPopupMenu(popup);
        }
        
        initDatas(args);
        
        initHttpRequest();
        
        getClientEnv();
        
        try {
            //editObject();
            if (onlyConvert.equals("true")) {
               launchOnlyConvert(); 
            } else {
                editObject_v2();
            }
            logger.close();
            System.exit(0);
        } catch (Exception ex) {
            Logger.getLogger(MaarchCM.class.getName()).log(Level.SEVERE, null, ex);
        }
    }

    public void initDatas(String[] args) {
        int index;

        for (index = 0; index < args.length; ++index)
        {
            System.out.println("args[" + index + "]: " + args[index]);
        }
        url = args[0];
        objectType = args[1];
        objectTable = args[2];
        objectId = args[3];
        uniqueId = args[4];
        cookie = args[5];
        clientSideCookies = args[6];
        idApplet = args[7];
        userMaarch = args[8];
        convertPdf = args[9];
        onlyConvert = args[10];
        md5File = args[11];

        logger.log("----------CONTROL PARAMETERS----------", Level.INFO);
        logger.log("URL : " + url, Level.INFO);
        logger.log("OBJECT TYPE : " + objectType, Level.INFO);
        logger.log("ID APPLET : " + idApplet, Level.INFO);
        logger.log("OBJECT TABLE : " + objectTable, Level.INFO);
        logger.log("OBJECT ID : " + objectId, Level.INFO);
        logger.log("UNIQUE ID : " + uniqueId, Level.INFO);
        logger.log("COOKIE : " + cookie, Level.INFO);
        logger.log("CLIENTSIDECOOKIES : " + clientSideCookies, Level.INFO);
        logger.log("USERMAARCH : " + userMaarch, Level.INFO);
        logger.log("CONVERTPDF : " + convertPdf, Level.INFO);
        logger.log("ONLYCONVERT : " + onlyConvert, Level.INFO);
        logger.log("MD5FILE : " + md5File, Level.INFO);
        logger.log("--------------------------------------", Level.INFO);
        
        System.out.println("");
    }
    public void getClientEnv() throws InterruptedException, IOException {
        logger.log("----------CONTROL CLIENT SIDE----------", Level.INFO);
        os = System.getProperty("os.name").toLowerCase();
        boolean isUnix = os.contains("nix") || os.contains("nux");
        boolean isWindows = os.contains("win");
        boolean isMac = os.contains("mac");
        if (isWindows) {
            logger.log("OS : Windows", Level.INFO);
            os = "win";
        } else if (isMac) {
            logger.log("OS : Mac", Level.INFO);
            os = "mac";
        } else if (isUnix) {
            logger.log("OS : Linux", Level.INFO);
            os = "linux";
        } else {
            logger.log("OS : Undefined", Level.INFO);
        }
        fM = new FileManager();
        String userLocalDir = System.getProperty("user.home");
        userLocalDirTmp = userLocalDir + File.separator + "maarchTmp";
        
        logger.log("TMP FOLDER : "+userLocalDirTmp, Level.INFO);
        logger.log("APP PATH: " + appPath, Level.INFO);
        System.out.println("----------BEGIN LOCAL DIR TMP IF NOT EXISTS----------");

        String info = fM.createUserLocalDirTmp(userLocalDirTmp, os);

        if (info == "ERROR") {
            messageStatus = "ERROR";
            messageResult.clear();
            messageResult.put("ERROR", "Permissions insuffisante sur votre répertoire temporaire maarch");
            processReturn(messageResult);
        }
        logger.log("---------------------------------------", Level.INFO);
    }
    
    public void initHttpRequest() {
        if (
                isURLInvalid() ||
                isObjectTypeInvalid() ||
                isObjectTableInvalid() ||
                isObjectIdInvalid() ||
                isCookieInvalid()
        ) {
            System.out.println("PARAMETERS NOT OK ! END OF APPLICATION");
            //System.exit(0);
            try {
                //MaarchCM.getAppletContext().showDocument(new URL("error.html"));
                //Go to an appropriate error page
            } catch (Exception e) {
                //Nothing
            }
        }

        System.out.println("----------END PARAMETERS----------");
        
        if ("empty".equals(uniqueId)) {
            uniqueId = null;
        }
        
        if ("empty".equals(clientSideCookies)) {
            clientSideCookies = null;
        }
        
        // The following code is to ensure a high level of management for HTTP cookies
        BasicCookieStore cookieStore = new BasicCookieStore();
        // Loading the cookie store with the Maarch cookie provided by the server
        cookieStore.addCookie(getCookieFromString(cookie));
        if (
                clientSideCookies != null && 
                clientSideCookies.length() > 0
        ) {
            System.out.println("clientSideCookies : " + clientSideCookies);
            // Within the whole cookie string returned from JavaScript, cookies are separated by a semicolon followed by a space
            // Let's get an array where cookies are stored with a "name=value" pattern
            String[] cookies = clientSideCookies.split(";\\s");
            System.out.println("cookies : " + cookies);
            // Iterate through the cookie array to retrieve each cookie name ans value and load the cookie store
            for (String nameValue : cookies) {
                cookieStore.addCookie(getCookieFromString(nameValue)); // Loading the cookie store
            }
        }
        httpContext.setCookieStore(cookieStore); // Assign all the cookies retrieved from JavaScript
        // Apply a Cookie policy: https://hc.apache.org/httpcomponents-client-ga/tutorial/html/statemgmt.html
        RequestConfig globalConfig = RequestConfig.custom().setCookieSpec(CookieSpecs.DEFAULT).build();

        // Pick up the best Apache HttpClient to do the job
        // which will allows for the automatic retrieval of the user session Kerberos ticket,
        // so this app will be able to properly talk with a proxy
        if ("win".equals(os) && WinHttpClients.isWinAuthAvailable()) {
            // Instantiation of the Apache HttpClient for Windows 7
            httpClient = WinHttpClients.custom().setDefaultRequestConfig(globalConfig).setDefaultCookieStore(cookieStore).build();
            System.out.println("The Apache HttpClient for Windows 7 was picked up");
        } else {
            // Instantiation of the generic Apache HttpClient
            HttpClientBuilder httpClientBuilder = HttpClients.custom();
            httpClientBuilder.useSystemProperties();
            httpClientBuilder.setDefaultRequestConfig(globalConfig);
            httpClientBuilder.setDefaultCookieStore(cookieStore);
            httpClient = httpClientBuilder.build();
            System.out.println("The generic Apache HttpClient was picked up");
        }

        if (httpClient == null) {
            System.out.println("NO HTTP CLIENT WAS INSTANTIATED, THE APPLICATION WILL FAIL!");
        }
    }
    
    /**
     * Controls the url parameter
     * @return boolean
     */
    private boolean isURLInvalid() {
        try {
            URL address = new URL(url); // Trying to build a valid URL
            address.openConnection().connect(); // Trying to open a valid connection
            domain = address.getHost(); // Retrieve the domain used
            System.out.println("DOMAIN USED IS: " + domain);
            return false; //success
        } catch (MalformedURLException e) {
            System.out.println("the URL is not a valid form " + url);
        } catch (IOException e) {
            System.out.println("the connection couldn't be etablished " + url);
        }
        return true; //default is failure
    }

    /**
     * Controls the objectType parameter
     * @return boolean
     */
    private boolean isObjectTypeInvalid() {
        Set<String> whiteList = new HashSet<>();
        whiteList.add("template");
        whiteList.add("templateStyle");
        whiteList.add("attachmentVersion");
        whiteList.add("attachmentUpVersion");
        whiteList.add("resource");
        whiteList.add("attachmentFromTemplate");
        whiteList.add("attachment");
        whiteList.add("outgoingMail");
        if (whiteList.contains(objectType)) return false; //success
        System.out.println("ObjectType not in the authorized list " + objectType);
        return true; //default is failure
    }

    /**
     * Controls the objectTable parameter
     * @return boolean
     */
    private boolean isObjectTableInvalid() {
        Set<String> whiteList = new HashSet<>();
        whiteList.add("res_letterbox");
        whiteList.add("res_attachments");
        whiteList.add("res_version_letterbox");
        whiteList.add("res_view_attachments");
        whiteList.add("res_view_letterbox");
        whiteList.add("templates");
        if (whiteList.contains(objectTable)) return false; //success
        logger.log("OBJECTTABLE NOT IN THE AUTHORIZED LIST ! "+ objectTable, Level.WARNING);
        return true; //default is failure
    }

    /**
     * Controls the objectId parameter
     * @return boolean
     */
    private boolean isObjectIdInvalid() {
        if (objectId != null && objectId.length() > 0) return false; //success
        logger.log("OBJECTID IS NULL OR EMPTY ! "+ objectId, Level.WARNING);
        return true; //default is failure
    }

    /**
     * Controls the cookie parameter
     * @return boolean
     */
    private boolean isCookieInvalid() {
        if (cookie != null && cookie.length() > 0) return false; //success
        logger.log("COOKIE IS NULL OR EMPTY !", Level.WARNING);
        return true; //default is failure
    }

    /**
     * Build a cookie from a String
     * @param nameValue
     * @return BasicClientCookie
     */
    private BasicClientCookie getCookieFromString(String nameValue) {
        int separator = nameValue.indexOf('='); // Locating the equal character
        String name = nameValue.substring(0, separator); // Getting everything before the equal character
        String value = nameValue.substring(separator + 1); // Getting everything after the equal character
        BasicClientCookie cookie = new BasicClientCookie(name, value);
        cookie.setPath("/");
        cookie.setDomain(domain);
        return cookie;
    }

    public void createPDF(String docxFile, String directory, String os) {
        logger.log("createPDF ", Level.INFO);
        try {
            System.out.println("mode ! : " + editMode);
         
            if ("linux".equals(os) || "mac".equals(os)) {
                editMode = "libreoffice";
            } else {
                programName = fM.findGoodProgramWithExt(fileExtension);
                String pathProgram;
                pathProgram = fM.findPathProgramInRegistry(programName);
                System.out.println("check prog name : "+programName);
                System.out.println("check path : "+pathProgram);
                if("soffice.exe".equals(programName)){   
                    if("\"null\"".equals(pathProgram)){
                        System.out.println(programName+" not found! switch to microsoft office...");
                        programName = "office.exe";
                    }
                }else{
                    if("\"null\"".equals(pathProgram)){
                        System.out.println(programName+" not found! switch to libreoffice...");
                        programName = "soffice.exe";
                    }
                }
                if("soffice.exe".equals(programName)){
                    editMode = "libreoffice";
                }else{
                    editMode = "office"; 
                }
            }
            
            boolean conversion = true;
            String cmd = "";
            if (docxFile.contains(".odt") || docxFile.contains(".ods") || docxFile.contains(".ODT") || docxFile.contains(".ODS")) {
                //logger.log("This is opendocument ! ", Level.INFO);
                if (os == "linux") {
                    cmd = "libreoffice -env:UserInstallation=file://"+userLocalDirTmp + File.separator + idApplet+"_conv/ --headless --convert-to pdf --outdir \"" + userLocalDirTmp + "\" \"" + docxFile + "\"";
                } else if (os == "mac") {
                    cmd = "cd /Applications/LibreOffice.app/Contents/MacOs && ./soffice --headless --convert-to pdf:writer_pdf_Export --outdir \"" + userLocalDirTmp + "\" \"" + docxFile + "\"";
                } else {
                    String convertProgram;
                    convertProgram = fM.findPathProgramInRegistry("soffice.exe");
                    cmd = convertProgram + " --headless --convert-to pdf --outdir \"" + userLocalDirTmp + "\" \"" + docxFile + "\" \r\n";
                }

            } else if (docxFile.contains(".doc") || docxFile.contains(".docx") || docxFile.contains(".DOC") || docxFile.contains(".DOCX")) {
                //logger.log("This is MSOffice document ", Level.INFO);
                if (useExeConvert.equals("false")) {
                    if (os == "linux") {
                        cmd = "libreoffice --headless --convert-to pdf --outdir \"" + userLocalDirTmp + "\" \"" + docxFile + "\"";
                    }  else if (os == "mac") {
                        cmd = "cd /Applications/LibreOffice.app/Contents/MacOs && ./soffice --headless --convert-to pdf:writer_pdf_Export --outdir \"" + userLocalDirTmp + "\" \"" + docxFile + "\"";
                    } else if(editMode.equals("libreoffice")){
                        String convertProgram;
                        convertProgram = fM.findPathProgramInRegistry("soffice.exe");
                        cmd = convertProgram + " --headless --convert-to pdf --outdir \"" + userLocalDirTmp + "\" \"" + docxFile + "\" \r\n";
                    }else{
                        vbsPath = userLocalDirTmp + File.separator + "DOC2PDF_VBS.vbs";
                        fM.createFile(fileContentVbs, vbsPath);
                        fileToDelete.add(vbsPath);
                        cmd = "cmd /C c:\\Windows\\System32\\cscript \"" + vbsPath + "\" \"" + docxFile + "\" /nologo \r\n";      
                    }
                }
            } else {
                conversion = false;
            }

            if (conversion) {
                Process proc_vbs;
                appPath_convert = userLocalDirTmp + File.separator + "conversion_"+idApplet+".sh";
                fileToDelete.add(appPath_convert);
                logger.log("COMMAND : " + cmd, Level.INFO);
                if (os == "linux" || os == "mac") {
                    final Writer outBat;
                    outBat = new OutputStreamWriter(new FileOutputStream(appPath_convert), "CP850");
                    //logger.log("--- cmd sh  --- " + cmd, Level.INFO);
                    outBat.write(cmd);
                    outBat.close();

                    File myFileBat = new File(appPath_convert);
                    myFileBat.setReadable(true, false);
                    myFileBat.setWritable(true, false);
                    myFileBat.setExecutable(true, false);

                    final String exec_vbs = "\"" + appPath + "\"";
                    proc_vbs = fM.launchApp(appPath_convert);
                } else {
                    proc_vbs = fM.launchApp(cmd);
                }
                
                proc_vbs.waitFor();
            }

        } catch (Throwable e) {
            logger.log(e.toString(), Level.SEVERE);
            e.printStackTrace();
        }
    }

    /**
     * Retrieve the xml message from Maarch and parse it
     * @param flux_xml xml content message
     */
    public void parse_xml(InputStream flux_xml) throws SAXException, IOException, ParserConfigurationException, InterruptedException {
        System.out.println("----------BEGIN PARSE XML----------");
        DocumentBuilder builder = DocumentBuilderFactory.newInstance().newDocumentBuilder();

        try {
            Document doc = builder.parse(flux_xml);
            messageResult.clear();
            NodeList level_one_list = doc.getChildNodes();
            for (Integer i = 0; i < level_one_list.getLength(); i++) {
                NodeList level_two_list = level_one_list.item(i).getChildNodes();
                if ("SUCCESS".equals(level_one_list.item(i).getNodeName())) {
                    for (Integer j = 0; j < level_one_list.item(i).getChildNodes().getLength(); j++) {
                        messageResult.put(level_two_list.item(j).getNodeName(), level_two_list.item(j).getTextContent());
                    }
                    messageStatus = "SUCCESS";
                } else if ("ERROR".equals(level_one_list.item(i).getNodeName())) {
                    for (Integer j = 0; j < level_one_list.item(i).getChildNodes().getLength(); j++) {
                        messageResult.put(level_two_list.item(j).getNodeName(), level_two_list.item(j).getTextContent());
                    }
                    messageStatus = "ERROR";
                }
            }
        } catch (SAXException | IOException e) {

            messageStatus = "ERROR";
            messageResult.put("ERROR", "Réponse inattendue du serveur : " + flux_xml.toString());
            processReturn(messageResult);
        }
        System.out.println("----------END PARSE XML----------");
    }

    /**
     * Manage the return of program execution
     * @param result result of the program execution
     */
    public void processReturn(Hashtable result) throws InterruptedException, UnsupportedEncodingException {
        logger.log("---------- RESPONSE SERVER ----------", Level.INFO);
        
        Iterator itValue = result.values().iterator();
        Iterator itKey = result.keySet().iterator();
        while (itValue.hasNext()) {
            String value = (String) itValue.next();
            String key = (String) itKey.next();

            if (!value.isEmpty() && (!"ERROR".equals(key))) {
                logger.log(key + " : " + value, Level.INFO);
            } else if (!value.isEmpty() && "ERROR".equals(key)){
                logger.log(value, Level.SEVERE);
            }
            
            if ("STATUS".equals(key)) status = value;
            if ("OBJECT_TYPE".equals(key)) objectType = value;
            if ("OBJECT_TABLE".equals(key)) objectTable = value;
            if ("OBJECT_ID".equals(key)) objectId = value;
            if ("UNIQUE_ID".equals(key)) uniqueId = value;
            if ("COOKIE".equals(key)) cookie = value;
            if ("CLIENTSIDECOOKIES".equals(key)) clientSideCookies = value;
            if ("APP_PATH".equals(key)) ; //appPath = value;
            if ("FILE_CONTENT".equals(key)) fileContent = value;
            if ("FILE_CONTENT_VBS".equals(key)) fileContentVbs = value;
            if ("VBS_PATH".equals(key)) vbsPath = value;
            if ("FILE_CONTENT_EXE".equals(key)) fileContentExe = value;
            if ("USE_EXE_CONVERT".equals(key)) useExeConvert = value;
            if ("FILE_EXTENSION".equals(key)) fileExtension = value;
            if ("ERROR".equals(key)) error = value;
            if ("END_MESSAGE".equals(key)) endMessage = value;
        }
        //send message error to Maarch if necessary
        if (!error.isEmpty()) {
            endRequestApplet();
            if (SystemTray.isSupported()) {
                trayIcon.displayMessage("Maarch content editor", error, TrayIcon.MessageType.ERROR);
            } 
            Thread.sleep(5000);
            System.exit(0);
        }
        logger.log("-------------------------------------", Level.INFO);
    }

    /**
     * Launch the external program and wait his execution end
     * @return boolean
     */
    public Boolean launchProcess() throws PrivilegedActionException, InterruptedException, IllegalArgumentException, IllegalAccessException, InvocationTargetException {
        logger.log("LAUNCH THE EDITOR ...", Level.INFO);
        
        if ("linux".equals(os)) {
            editMode = "libreoffice";
            fM.launchApp("libreoffice --nolockcheck --nodefault --nofirststartwizard --nofirststartwizard --norestore " + userLocalDirTmp + File.separator + fileToEdit);
        } else if ("mac".equals(os)) {
            editMode = "libreoffice";
            fM.launchApp("open -W " + userLocalDirTmp + File.separator + fileToEdit);
        } else {
            programName = fM.findGoodProgramWithExt(fileExtension);
            String pathProgram;
            pathProgram = fM.findPathProgramInRegistry(programName);
            String options;
            System.out.println("check prog name : "+programName);
            System.out.println("check path : "+pathProgram);
            if("soffice.exe".equals(programName)){   
                if("\"null\"".equals(pathProgram)){
                    System.out.println(programName+" not found! switch to microsoft office...");
                    programName = "office.exe";
                    pathProgram = fM.findPathProgramInRegistry(programName);
                    options = fM.findGoodOptionsToEdit(fileExtension);
                }else{
                    options = " --nolockcheck --nodefault --nofirststartwizard --nofirststartwizard --norestore ";          
                }
            }else{
                if("\"null\"".equals(pathProgram)){
                    System.out.println(programName+" not found! switch to libreoffice...");
                    programName = "soffice.exe";
                    pathProgram = fM.findPathProgramInRegistry(programName);
                    options = " --nolockcheck --nodefault --nofirststartwizard --nofirststartwizard --norestore ";
                }else{
                    options = fM.findGoodOptionsToEdit(fileExtension);
                }
            }
            
            if("soffice.exe".equals(programName)){
                editMode = "libreoffice";
            }else{
                editMode = "office"; 
            }
            //logger.log("PROGRAM NAME TO EDIT : " + programName, Level.INFO);
            //logger.log("OPTION PROGRAM TO EDIT " + options, Level.INFO);
            //logger.log("PROGRAM PATH TO EDIT : " + pathProgram, Level.INFO);
            
            
            String pathCommand;
            pathCommand = pathProgram + " " + options + "\"" + userLocalDirTmp + File.separator + fileToEdit + "\"";
            logger.log("COMMAND : " + pathCommand, Level.INFO);
            fM.launchApp(pathCommand);
        }
        return true;
    }

    /**
     * Send an http request to Maarch
     * @param theUrl url to contact Maarch
     * @param postRequest the request
     * @param endProcess end request
     */
    public void sendHttpRequest(String theUrl, final String postRequest, final boolean endProcess) throws UnsupportedEncodingException, InterruptedException {
        System.out.println("URL request : " + theUrl);

        // Inner class representing the payload to be posted via HTTP
        AbstractHttpEntity entity = new AbstractHttpEntity() {
            public boolean isRepeatable() {
                return false; // must be implemented
            }

            public long getContentLength() {
                return -1; // must be implemented
            }

            public boolean isStreaming() {
                return false; // must be implemented
            }

            public InputStream getContent() throws IOException {
                return new ByteArrayInputStream(postRequest.getBytes());
            }

            public void writeTo(OutputStream out) throws IOException {
                System.out.println("METHOD 'WriteTo' WAS CALLED!");
                if (!"none".equals(postRequest)) {
                    Writer writer = new OutputStreamWriter(out, "UTF-8");
                    // Using a StringBuffer rather than multiple "+" operators results in much better performance!
                    StringBuffer sb = new StringBuffer();
                    if ("true".equals(convertPdf)) {
                        if (endProcess) {
                            // Prepending "null" saves from testing "if(pdfContentTosend != null)"
                            if ("null".equalsIgnoreCase(pdfContentTosend)) {
                                sb.append("fileContent=");
                                sb.append(fileContentTosend);
                                sb.append("&fileExtension=");
                                sb.append(fileExtension);
                            } else {
                                sb.append("fileContent=");
                                sb.append(fileContentTosend);
                                sb.append("&fileExtension=");
                                sb.append(fileExtension);
                                sb.append("&pdfContent=");
                                sb.append(pdfContentTosend);
                            }
                        } else {
                            sb.append("fileContent=");
                            sb.append(fileContentTosend);
                            sb.append("&fileExtension=");
                            sb.append(fileExtension);
                        }
                    } else {
                        sb.append("fileContent=");
                        sb.append(fileContentTosend);
                        sb.append("&fileExtension=");
                        sb.append(fileExtension);
                    }
                    
                    writer.write(sb.toString());
                    writer.flush();
                }
            }
        };
        HttpPost request = new HttpPost(theUrl); // Construct a HTTP post request
        System.out.println("BUILT REQUEST: " + request);
        
        
        // Request parameters and other properties.
        List<NameValuePair> params = new ArrayList<NameValuePair>(2);
        
        if ("true".equals(convertPdf)) {
            if (endProcess) {
                // Prepending "null" saves from testing "if(pdfContentTosend != null)"
                if ("null".equalsIgnoreCase(pdfContentTosend)) {
                    params.add(new BasicNameValuePair("fileContent", fileContentTosend));
                    params.add(new BasicNameValuePair("fileExtension", fileExtension));
                } else {
                    params.add(new BasicNameValuePair("fileContent", fileContentTosend));
                    params.add(new BasicNameValuePair("fileExtension", fileExtension));
                    params.add(new BasicNameValuePair("pdfContent", pdfContentTosend));
                }
            } else {
                params.add(new BasicNameValuePair("fileContent", fileContentTosend));
                params.add(new BasicNameValuePair("fileExtension", fileExtension));
            }
        } else {
            params.add(new BasicNameValuePair("fileContent", fileContentTosend));
            params.add(new BasicNameValuePair("fileExtension", fileExtension));
        }
        
        request.setEntity(new UrlEncodedFormEntity(params, "UTF-8"));
        System.out.println("FULL REQUEST" + request);
        try {
            System.out.println("COOKIES TO BE SENT: " + httpContext.getCookieStore().getCookies()); // Show the cookies to be sent
            CloseableHttpResponse response = httpClient.execute(request, httpContext); // Carry out the HTTP post request
            //System.out.println(response);
            if (response == null || response.toString().contains("401 Unauthorized")) {
                logger.log("SERVER CONNEXION FAILED : " + response.toString(), Level.SEVERE);
                if (SystemTray.isSupported()) {
                    trayIcon.displayMessage("Maarch content editor", "SERVER CONNEXION FAILED : " + response.toString(), TrayIcon.MessageType.ERROR);
                }
                logger.close();
                Thread.sleep(5000);
                System.exit(0);
            } else{
                parse_xml(response.getEntity().getContent()); // Process the response from the server
                response.close();
            }
        } catch (Exception ex) {
            logger.log("SERVER CONNEXION FAILED : " + ex, Level.SEVERE);
            if (SystemTray.isSupported()) {
                trayIcon.displayMessage("Maarch content editor", "La connexion au serveur a été interrompue, le document édité n'a pas été sauvegardé !", TrayIcon.MessageType.ERROR);
            }
            logger.close();
            Thread.sleep(5000);
            System.exit(0);
        }
    }
    
    public void editObject_v2() throws InterruptedException, IOException, PrivilegedActionException, IllegalArgumentException, IllegalAccessException, InvocationTargetException, Exception {
        String urlToSend;
        if (checksumFile(md5File) == false) {
            logger.log("The file is not found in maarchTmp folder.", Level.INFO);
            logger.log("RETRIEVE DOCUMENT ...", Level.INFO);
            
            urlToSend = url + "?action=editObject&objectType=" + objectType
                + "&objectTable=" + objectTable + "&objectId=" + objectId
                + "&uniqueId=" + uniqueId;

            logger.log("CALL : " + urlToSend, Level.INFO);
            sendHttpRequest(urlToSend, "none", false);
            processReturn(messageResult);

            //fileToEdit = "thefile_" + idApplet + "." + fileExtension;
            if (md5File.equals("0")) {
               md5File =  "thefile_" + idApplet;
            }
            fileToEdit = md5File + "." + fileExtension;

            fM.createFile(fileContent, userLocalDirTmp + File.separator + fileToEdit);
        } else {
            System.out.println("Document found in maarchTmp folder !");
            logger.log("Document found in maarchTmp folder !", Level.INFO);
        }   
        
        //fileToDelete.add(userLocalDirTmp + File.separator + fileToEdit);
        fileContentTosend = "";
        
        logger.log("FILE TO EDIT : " + userLocalDirTmp + fileToEdit, Level.INFO);
        
        launchProcess();
        try {
            WatchService watcher = FileSystems.getDefault().newWatchService();
            
            Path dir = Paths.get(userLocalDirTmp);
            dir.register(watcher, ENTRY_CREATE, ENTRY_DELETE, ENTRY_MODIFY);
            String editor = "";

            while (true) {
                WatchKey key;
                try {
                    key = watcher.take();
                } catch (InterruptedException ex) {
                    return;
                }
                 
                for (WatchEvent<?> event : key.pollEvents()) {
                    WatchEvent.Kind<?> kind = event.kind();
                     
                    @SuppressWarnings("unchecked")
                    WatchEvent<Path> ev = (WatchEvent<Path>) event;
                    Path fileName = ev.context();
                     
                    //System.out.println(kind.name() + ": " + fileName);

                    if (kind == ENTRY_CREATE && fileName.toString().equals(".~lock." + fileToEdit + "#")) {
                        editor = "libreoffice";
                    }
                    if (kind == ENTRY_CREATE && fileName.toString().equals("~$" + fileToEdit.substring(2, fileToEdit.length())) ) {
                        editor = "office";
                    }
                    if (kind == ENTRY_MODIFY && fileName.toString().equals(fileToEdit)) {
                        Thread.sleep(3000);
                        File fileTotest = new File(userLocalDirTmp + File.separator + fileToEdit);
                        if (fileTotest.canRead()) {
                            String actualContent = FileManager.encodeFile(userLocalDirTmp + File.separator + fileToEdit);
                            if (!fileContentTosend.equals(actualContent)) {
                                fileContentTosend = actualContent;
                                logger.log("BACKUP FILE SEND ...", Level.INFO);
                                String urlToSave = url + "?action=saveObject&objectType=" + objectType
                                        + "&objectTable=" + objectTable + "&objectId=" + objectId
                                        + "&uniqueId=" + uniqueId + "&step=backup&userMaarch=" + userMaarch;
                                logger.log("CALL : " + urlToSave, Level.INFO);
                                if (SystemTray.isSupported()) {
                                    trayIcon.displayMessage("Maarch content editor", "Envoi du brouillon ...", TrayIcon.MessageType.INFO);
                                }  
                                sendHttpRequest(urlToSave, fileContentTosend, false);
                                processReturn(messageResult);
                            }
                        } else {
                            logger.log(userLocalDirTmp + fileToEdit + " FILE NOT READABLE !!!!!!", Level.INFO);
                        }
                    }
                    if (kind == ENTRY_CREATE && (fileName.toString().equals(".~lock." + fileToEdit + "#") || fileName.toString().equals("~$" + fileToEdit.substring(2, fileToEdit.length())))) {
                        logger.log("FIRST BACKUP FILE SEND ...", Level.INFO);

                        urlToSend = url + "?action=editObject&objectType=" + objectType
                            + "&objectTable=" + objectTable + "&objectId=" + objectId
                            + "&uniqueId=" + uniqueId;
                        logger.log("CALL : " + urlToSend, Level.INFO);
                        sendHttpRequest(urlToSend, "none", false);
                        processReturn(messageResult);                        
                    }

                    if (kind == ENTRY_DELETE && (fileName.toString().equals(".~lock." + fileToEdit + "#") || fileName.toString().equals("~$" + fileToEdit.substring(2, fileToEdit.length())))) {
                        Thread.sleep(500);
                        File fileTotest = new File(userLocalDirTmp + File.separator +".~lock." + fileToEdit + "#");
                        if(!fileTotest.exists() || editor.equals("office")) {
                            logger.log("ENDING EDITING FILE ...", Level.INFO);
                            logger.log("ENCODING DOCUMENT ...", Level.INFO);
                            fileContentTosend = FileManager.encodeFile(userLocalDirTmp + File.separator + fileToEdit);

                            if ("true".equals(convertPdf)) {
                                if ((fileExtension.equalsIgnoreCase("docx") || fileExtension.equalsIgnoreCase("doc") || fileExtension.equalsIgnoreCase("docm") || fileExtension.equalsIgnoreCase("odt") || fileExtension.equalsIgnoreCase("ott"))) {
                                    logger.log("CONVERT DOCUMENT TO PDF ...", Level.INFO);
                                    //String pdfFile = userLocalDirTmp + File.separator + "thefile_" + idApplet + ".pdf";
                                    String pdfFile = userLocalDirTmp + File.separator + md5File + ".pdf";                                    
                                    createPDF(userLocalDirTmp + File.separator + fileToEdit, userLocalDirTmp, os);
                                    File file=new File(pdfFile);
                                    if (file.exists()) {
                                        pdfContentTosend = FileManager.encodeFile(pdfFile);
                                        fileToDelete.add(pdfFile);
                                        
                                    } else {
                                        pdfContentTosend = "null";
                                        logger.log("CONVERT PDF ERROR !", Level.WARNING); 
                                    }
                                }else{
                                    pdfContentTosend = "not allowed";
                                    logger.log("EXTENSION : " + fileExtension + " CANNOT BE CONVERTED", Level.WARNING);
                                }
                            }
                            if (SystemTray.isSupported()) {
                                trayIcon.displayMessage("Maarch content editor", "Envoi du document ...", TrayIcon.MessageType.INFO);
                            }
                            String urlToSave = url + "?action=saveObject&objectType=" + objectType
                                    + "&objectTable=" + objectTable + "&objectId=" + objectId
                                    + "&uniqueId=" + uniqueId + "&idApplet=" + idApplet + "&step=end&userMaarch=" + userMaarch
                                    + "&onlyConvert=" + onlyConvert;
                            logger.log("CALL : " + urlToSave, Level.INFO);
                            sendHttpRequest(urlToSave, fileContentTosend, true);
                            processReturn(messageResult);

                            if ("true".equals(convertPdf)) {
                                if (pdfContentTosend == "null") {
                                    endMessage = endMessage + " mais la conversion pdf n'a pas fonctionné (le document ne pourra pas être signé)";
                                }
                            }
                            
                            logger.log("CREATE BACKUP FILE IN TMP FOLDER ...", Level.INFO);
                            String newMd5 = getchecksumFile(userLocalDirTmp + File.separator + fileToEdit);
                            
                            if (os.equals("win")) {
                                fileContent = FileManager.encodeFile(userLocalDirTmp + File.separator + fileToEdit);
                                fM.createFile(fileContent,userLocalDirTmp + File.separator + newMd5 + "." + fileExtension);
                            } else {
                                Files.move(new File(userLocalDirTmp + File.separator + fileToEdit).toPath(), new File(userLocalDirTmp + File.separator + newMd5 + "." + fileExtension).toPath(), java.nio.file.StandardCopyOption.REPLACE_EXISTING);
                            }
                            Thread.sleep(2000);
                            logger.log("DELETE TMP FILES ...", Level.INFO);
                            FileManager.deleteSpecificFilesOnDir(fileToDelete);
                            logger.log("DELETE ENV FOLDER ...", Level.INFO);
                            FileManager.deleteEnvDir(userLocalDirTmp + File.separator + idApplet + "_conv");
                            logger.log("DELETE OLDEST FILES ...", Level.INFO);
                            FileManager.deleteFilesOnDirWithTime(userLocalDirTmp);
                         
                            return;
                        }
                    }
                }
                 
                boolean valid = key.reset();
                if (!valid) {
                    break;
                }
            }
             
        } catch (IOException ex) {
            System.err.println(ex);
        }
    }
    
    public void launchOnlyConvert() throws UnsupportedEncodingException, InterruptedException, IOException, PrivilegedActionException, Exception {

        logger.log("RETRIEVE DOCUMENT ...", Level.INFO);
        
        String urlToSend = url + "?action=editObject&objectType=" + objectType
            + "&objectTable=" + objectTable + "&objectId=" + objectId
            + "&uniqueId=" + uniqueId;
        
        logger.log("CALL : " + urlToSend, Level.INFO);
        
        sendHttpRequest(urlToSend, "none", false);
        processReturn(messageResult);
        
        fileToEdit = "thefile_" + idApplet + "." + fileExtension;
        
        logger.log("FILE TO CONVERT : " + userLocalDirTmp + fileToEdit, Level.INFO);
        
        fM.createFile(fileContent, userLocalDirTmp + File.separator + fileToEdit);
        fileToDelete.add(userLocalDirTmp + File.separator + fileToEdit);
        fileContentTosend = FileManager.encodeFile(userLocalDirTmp + File.separator + fileToEdit);
        
        if ((fileExtension.equalsIgnoreCase("docx") || fileExtension.equalsIgnoreCase("doc") || fileExtension.equalsIgnoreCase("docm") || fileExtension.equalsIgnoreCase("odt") || fileExtension.equalsIgnoreCase("ott"))) {
            logger.log("CONVERT DOCUMENT TO PDF ...", Level.INFO);
            String pdfFile = userLocalDirTmp + File.separator + "thefile_" + idApplet + ".pdf";
            createPDF(userLocalDirTmp + File.separator + fileToEdit, userLocalDirTmp, os);
            File file=new File(pdfFile);
            if (file.exists()) {
                pdfContentTosend = FileManager.encodeFile(pdfFile);
                fileToDelete.add(pdfFile);

            } else {
                pdfContentTosend = "null";
                logger.log("CONVERT PDF ERROR !", Level.WARNING); 
            }

        }else{
            pdfContentTosend = "not allowed";
            logger.log("EXTENSION : " + fileExtension + " CANNOT BE CONVERTED", Level.WARNING);  
        }
        String urlToSave = url + "?action=saveObject&objectType=" + objectType
                + "&objectTable=" + objectTable + "&objectId=" + objectId
                + "&uniqueId=" + uniqueId + "&idApplet=" + idApplet + "&step=end&userMaarch=" + userMaarch
                + "&onlyConvert=" + onlyConvert;
        logger.log("----------BEGIN SEND OF THE OBJECT----------", Level.INFO);
        logger.log("CALL : " + urlToSave, Level.INFO);
        sendHttpRequest(urlToSave, fileContentTosend, true);
        processReturn(messageResult);

        Thread.sleep(2000);
        logger.log("DELETE TMP FILES ...", Level.INFO);
        FileManager.deleteSpecificFilesOnDir(fileToDelete);
        logger.log("DELETE ENV FOLDER ...", Level.INFO);
        FileManager.deleteEnvDir(userLocalDirTmp + File.separator + idApplet + "_conv");
        return;
    }
    
    public void endRequestApplet() throws UnsupportedEncodingException, InterruptedException {
        logger.log("CLOSING APPLET ...", Level.INFO);
        fileContentTosend = "";
        String urlToSave = url + "?action=terminate&objectType=" + objectType
            + "&objectTable=" + objectTable + "&objectId=" + objectId
            + "&uniqueId=" + uniqueId + "&idApplet=" + idApplet + "&step=end&userMaarch=" + userMaarch
            + "&onlyConvert=" + onlyConvert;
        logger.log("CALL : " + urlToSave, Level.INFO);
        sendHttpRequest(urlToSave, "none", true);
        logger.close();
        return;
    }
    
    public Boolean checksumFile(String md5) throws NoSuchAlgorithmException, FileNotFoundException, IOException {
        if (md5.equals("0")) {
            return false;
        }
        MessageDigest md = MessageDigest.getInstance("MD5");
        FileInputStream fis = null;
        File dir = new File(userLocalDirTmp);
        File[] directoryListing = dir.listFiles();
        if (directoryListing != null) {
          for (File child : directoryListing) {
            if (child.toString().contains(md5)) {
                
                String checksum = getchecksumFile(child.toString());
                System.out.println("MD5 checksum file found ! " + checksum);
                if (checksum.equals(md5)) {
                    fileToEdit = child.getName().toString();
                    fileExtension = FileManager.getFileExtension(child);
                    return true;
                } else {
                    return false;
                }
            }
          }
        }
        return false;
    }
    
    public String getchecksumFile(String fileName) throws NoSuchAlgorithmException, FileNotFoundException, IOException {

        MessageDigest md = MessageDigest.getInstance("MD5");
        
        FileInputStream fis = new FileInputStream(fileName);

        byte[] dataBytes = new byte[1024];

        int nread = 0; 
        while ((nread = fis.read(dataBytes)) != -1) {
          md.update(dataBytes, 0, nread);
        };
        byte[] mdbytes = md.digest();

        //convert the byte to hex format method 1
        StringBuffer sb = new StringBuffer();
        for (int i = 0; i < mdbytes.length; i++) {
          sb.append(Integer.toString((mdbytes[i] & 0xff) + 0x100, 16).substring(1));
        }
        System.out.println("MD5 checksum file : " + sb.toString());
        return sb.toString();
    }
} 