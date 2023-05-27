
import com.itextpdf.text.DocumentException;
import com.itextpdf.text.Image;
import com.itextpdf.text.pdf.PdfContentByte;
import com.itextpdf.text.pdf.PdfDictionary;
import com.itextpdf.text.pdf.PdfIndirectReference;
import com.itextpdf.text.pdf.PdfName;
import com.itextpdf.text.pdf.PdfNumber;
import com.itextpdf.text.pdf.PdfObject;
import com.itextpdf.text.pdf.PdfReader;
import com.itextpdf.text.pdf.PdfStamper;
import com.itextpdf.text.pdf.PdfWriter;
import com.itextpdf.text.pdf.parser.PdfReaderContentParser;
import java.io.BufferedReader;
import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.util.Arrays;
import java.util.Iterator;

public class ProcessPageContent {

    public void processMultiImageInPdf(String pdf, String signatureFile, String widthModel, String heightModel, String path) throws IOException, DocumentException {
        Image imageSignatureFile = Image.getInstance(signatureFile);

        PdfReader pdfReader = new PdfReader(pdf);
        PdfStamper pdfStamper = new PdfStamper(pdfReader, new FileOutputStream(String.valueOf(path) + File.separator + (new File(pdf)).getName()));
        PdfWriter writer = pdfStamper.getWriter();

        for (int pageNumber = 1; pageNumber <= pdfReader.getNumberOfPages(); pageNumber++) {
            System.out.println("pageNumber = " + pageNumber);
            PdfDictionary pg = pdfReader.getPageN(pageNumber);
            PdfDictionary res = (PdfDictionary) PdfReader.getPdfObject(pg.get(PdfName.RESOURCES));

            PdfDictionary xobj = (PdfDictionary) PdfReader.getPdfObject(res.get(PdfName.XOBJECT));

            boolean carresigntrouve = false;
            if (xobj != null) {
                for (Iterator<PdfName> it = xobj.getKeys().iterator(); it.hasNext();) {
                    PdfObject obj = xobj.get(it.next());
                    if (obj.isIndirect()) {
                        System.out.println("Recherche image type obj = " + obj);
                        PdfDictionary tg = (PdfDictionary) PdfReader.getPdfObject(obj);
                        System.out.println("Recherche image type tg = " + tg);
                        if (tg != null) {
                            PdfName type = (PdfName) PdfReader.getPdfObject(tg.get(PdfName.SUBTYPE));
                            System.out.println("Recherche image type = " + type);

                            if (PdfName.IMAGE.equals(type)) {
                                PdfNumber width = (PdfNumber) PdfReader.getPdfObject(tg.get(PdfName.WIDTH));
                                PdfNumber height = (PdfNumber) PdfReader.getPdfObject(tg.get(PdfName.HEIGHT));
                                if (width.floatValue() == Float.parseFloat(widthModel) && height.floatValue() == Float.parseFloat(heightModel)) {
                                    System.out.println("Debut remplacement de l'image");
                                    PdfReader.killIndirect(obj);
                                    Image maskImage = imageSignatureFile.getImageMask();
                                    if (maskImage != null) {
                                        writer.addDirectImageSimple(maskImage);
                                    }
                                    writer.addDirectImageSimple(imageSignatureFile, (PdfIndirectReference) obj);
                                    System.out.println("Fin remplacement de l'image");
                                    carresigntrouve = true;

                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        pdfStamper.close();
        pdfReader.close();
    }

    public void processImagePdfAlternate(String pdf, String signatureFile, String widthModel, String heightModel, String path) throws IOException, DocumentException {
        String nomPdf = (new File(pdf)).getName();
        String prefix = nomPdf.substring(0, nomPdf.length() - 4);
        if ((new File(String.valueOf(path) + File.separator + prefix + ".txt")).exists()) {
            (new File(String.valueOf(path) + File.separator + prefix + ".txt")).delete();
        }

        PdfReader reader = new PdfReader(pdf);
        PdfReaderContentParser parser = new PdfReaderContentParser(reader);
        String imgPath = String.valueOf(path) + File.separator + prefix + "%s.%s";
        int numPage = 0;
        MyImageRenderListener listener = new MyImageRenderListener(imgPath, Float.parseFloat(widthModel), Float.parseFloat(heightModel), path, prefix);
        for (int i = 1; i <= reader.getNumberOfPages(); i++) {
            System.out.println("pageNumber = " + i);
            parser.processContent(i, listener);
            if ((new File(String.valueOf(path) + File.separator + prefix + ".txt")).exists()) {

                numPage = i;

                break;
            }
        }
        if ((new File(String.valueOf(path) + File.separator + prefix + ".txt")).exists() && numPage != 0) {

            float x = 0.0F;
            float y = 0.0F;
            float w = 0.0F;
            float h = 0.0F;

            try {
                InputStream ips = new FileInputStream(String.valueOf(path) + File.separator + prefix + ".txt");
                InputStreamReader ipsr = new InputStreamReader(ips);
                BufferedReader br = new BufferedReader(ipsr);

                int j = 0;
                String ligne;
                while ((ligne = br.readLine()) != null) {
                    j++;
                    switch (j) {
                        case 1:
                            x = Float.parseFloat(ligne);
                        case 2:
                            y = Float.parseFloat(ligne);
                        case 3:
                            w = Float.parseFloat(ligne);
                        case 4:
                            h = Float.parseFloat(ligne);
                    }
                }
                br.close();
                ipsr.close();
                ips.close();
            } catch (Exception e) {
                e.printStackTrace();
            }

            try {
                PdfReader pdfReader = new PdfReader(pdf);
                PdfStamper pdfStamper = new PdfStamper(pdfReader, new FileOutputStream(String.valueOf(path) + File.separator + (new File(pdf)).getName()));
                Image imageSignatureFile = Image.getInstance(signatureFile);

                PdfContentByte content = pdfStamper.getOverContent(numPage);
                imageSignatureFile.setAbsolutePosition(x, y);
                imageSignatureFile.scaleAbsolute(w, h);

                content.addImage(imageSignatureFile);

                pdfStamper.close();
            } catch (IOException e) {
                e.printStackTrace();
            } catch (DocumentException e) {
                e.printStackTrace();
            }
        }
        reader.close();
    }

    public void processImagePdf(String pdf, String signatureFile, String widthModel, String heightModel, String path) throws IOException, DocumentException {
        Image imageSignatureFile = Image.getInstance(signatureFile);

        PdfReader pdfReader = new PdfReader(pdf);

        for (int pageNumber = 1; pageNumber <= pdfReader.getNumberOfPages(); pageNumber++) {

            PdfDictionary pg = pdfReader.getPageN(pageNumber);
            PdfDictionary res = (PdfDictionary) PdfReader.getPdfObject(pg.get(PdfName.RESOURCES));

            PdfDictionary xobj = (PdfDictionary) PdfReader.getPdfObject(res.get(PdfName.XOBJECT));

            boolean carresigntrouve = false;
            if (xobj != null) {
                for (Iterator<PdfName> it = xobj.getKeys().iterator(); it.hasNext();) {
                    PdfObject obj = xobj.get(it.next());
                    if (obj.isIndirect()) {

                        PdfDictionary tg = (PdfDictionary) PdfReader.getPdfObject(obj);

                        if (tg != null) {
                            PdfName type = (PdfName) PdfReader.getPdfObject(tg.get(PdfName.SUBTYPE));

                            if (PdfName.IMAGE.equals(type)) {
                                PdfNumber width = (PdfNumber) PdfReader.getPdfObject(tg.get(PdfName.WIDTH));
                                PdfNumber height = (PdfNumber) PdfReader.getPdfObject(tg.get(PdfName.HEIGHT));
                                if (width.floatValue() == Float.parseFloat(widthModel) && height.floatValue() == Float.parseFloat(heightModel)) {
                                    System.out.println("Carré signature trouvé => debut remplacement de l'image");
                                    PdfReader.killIndirect(obj);
                                    Image maskImage = imageSignatureFile.getImageMask();
                                    PdfStamper pdfStamper = new PdfStamper(pdfReader, new FileOutputStream(String.valueOf(path) + File.separator + (new File(pdf)).getName()));
                                    PdfWriter writer = pdfStamper.getWriter();
                                    if (maskImage != null) {
                                        writer.addDirectImageSimple(maskImage);
                                    }
                                    writer.addDirectImageSimple(imageSignatureFile, (PdfIndirectReference) obj);
                                    pdfStamper.close();
                                    System.out.println("Fin remplacement de l'image");
                                    carresigntrouve = true;
                                    break;
                                }
                            }
                        }
                    }
                }
            }
            if (carresigntrouve) {
                break;
            }
        }

        pdfReader.close();
    }

    public static void main(String[] args) throws IOException, DocumentException {
        System.out.println("PDF document to process => " + Arrays.toString(args));
        if (args.length != 5) {

            usage();
        } else {

            System.out.println("-------------------------------------------------------------------------------");
            String pdfFile = args[0];
            System.out.println("PDF document to process => " + pdfFile);

            String signatureFile = args[1];
            System.out.println("JPG signature file => " + signatureFile);

            String widthModel = args[2];
            System.out.println("Width of image model => " + widthModel);

            String heightModel = args[3];
            System.out.println("Height of image model => " + heightModel);

            String resultPath = args[4].replaceAll("\"", "");
            System.out.println("Path of result directory (brut) => " + args[4]);
            System.out.println("Path of result directory => " + resultPath);

            System.out.println("-------------------------------------------------------------------------------");

            (new ProcessPageContent()).processImagePdf(pdfFile, signatureFile, widthModel, heightModel, resultPath);
        }
    }

    private static void usage() {
        System.err
                .println("Usage: ProcessPageContent <PDF file> <signature> <width> <height> <resultpath>\n  <PDF file>                   PDF document to process\n  <signature>                  JPG signature file\n  <width>                   \t  Width of image model\n  <height>                     Height of image model\n  <resultpath>                 Path of result directory\n");

        System.exit(1);
    }
}
