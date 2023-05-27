
import com.itextpdf.text.pdf.parser.ImageRenderInfo;
import com.itextpdf.text.pdf.parser.RenderListener;
import com.itextpdf.text.pdf.parser.TextRenderInfo;
import java.io.PrintWriter;

public class MyTextRenderListener
        implements RenderListener {

    protected PrintWriter out;

    public MyTextRenderListener(PrintWriter out) {
        this.out = out;
    }

    public void beginTextBlock() {
        this.out.print("<");
    }

    public void endTextBlock() {
        this.out.println(">");
    }

    public void renderImage(ImageRenderInfo renderInfo) {
    }

    public void renderText(TextRenderInfo renderInfo) {
        this.out.print("<");
        this.out.print(renderInfo.getText());
        this.out.print(">");
    }
}
