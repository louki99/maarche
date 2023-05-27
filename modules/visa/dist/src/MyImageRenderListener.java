
import com.itextpdf.text.pdf.PdfName;
import com.itextpdf.text.pdf.PdfNumber;
import com.itextpdf.text.pdf.parser.ImageRenderInfo;
import com.itextpdf.text.pdf.parser.Matrix;
import com.itextpdf.text.pdf.parser.PdfImageObject;
import com.itextpdf.text.pdf.parser.RenderListener;
import com.itextpdf.text.pdf.parser.TextRenderInfo;
import java.io.BufferedWriter;
import java.io.File;
import java.io.FileOutputStream;
import java.io.FileWriter;
import java.io.PrintWriter;

public class MyImageRenderListener
        implements RenderListener {

    protected String imgPath = "";
    protected float widthModel = 0.0F;
    protected float heightModel = 0.0F;
    protected String path = "";
    protected String prefix = "";

    public MyImageRenderListener(String imgPath, float wModel, float hModel, String path, String prefix) {
        this.imgPath = imgPath;
        this.widthModel = wModel;
        this.heightModel = hModel;
        this.path = path;
        this.prefix = prefix;
    }

    public void beginTextBlock() {
    }

    public void endTextBlock() {
    }

    public void renderImage(ImageRenderInfo renderInfo) {
        try {
            FileOutputStream os = null;
            PdfImageObject image = renderInfo.getImage();
            if (image == null) {
                return;
            }
            PdfName filter = (PdfName) image.get(PdfName.FILTER);
            System.out.println("filter = " + filter);

            if (PdfName.DCTDECODE.equals(filter)) {

                PdfName colorSpace = (PdfName) image.get(PdfName.COLORSPACE);
                System.out.println("colorSpace = " + colorSpace);
                PdfName name = (PdfName) image.get(PdfName.NAME);
                System.out.println("name = " + name);
                PdfName subType = (PdfName) image.get(PdfName.SUBTYPE);
                System.out.println("subType = " + subType);
                PdfNumber bitsPerComponent = (PdfNumber) image.get(PdfName.BITSPERCOMPONENT);
                System.out.println("bitsPerComponent = " + bitsPerComponent);
                PdfNumber length = (PdfNumber) image.get(PdfName.LENGTH);
                System.out.println("length = " + length);
                PdfNumber width = (PdfNumber) image.get(PdfName.WIDTH);
                System.out.println("width = " + width);
                PdfNumber height = (PdfNumber) image.get(PdfName.HEIGHT);
                System.out.println("height = " + height);

                System.out.println("StartPoint = ");
                System.out.println(renderInfo.getStartPoint());

                System.out.println("Matrice = ");
                System.out.println(renderInfo.getImageCTM());

                Matrix matrix = renderInfo.getImageCTM();
                float x = matrix.get(6);
                float y = matrix.get(7);
                float w = matrix.get(0);
                float h = matrix.get(4);

                System.out.println("widthModel =" + this.widthModel);
                System.out.println("heightModel =" + this.heightModel);
                System.out.println("widthModel =" + width.floatValue());
                System.out.println("heightModel =" + height.floatValue());
                if (width.floatValue() == this.widthModel && height.floatValue() == this.heightModel) {

                    try {
                        FileWriter fw = new FileWriter(String.valueOf(this.path) + File.separator + this.prefix + ".txt");
                        BufferedWriter bw = new BufferedWriter(fw);
                        PrintWriter fichierSortie = new PrintWriter(bw);
                        fichierSortie.println(x);
                        fichierSortie.println(y);
                        fichierSortie.println(w);
                        fichierSortie.println(h);
                        fichierSortie.close();
                    } catch (Exception e) {
                        e.printStackTrace();

                    }

                }

            }

        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    public void renderText(TextRenderInfo renderInfo) {
    }
}