#!/usr/bin/env python3
"""Build the immutable A4 background and a visual QA proof for print revision 1.3.1."""

from pathlib import Path
from tempfile import TemporaryDirectory

from PIL import Image
from pypdf import PdfReader, PdfWriter
from reportlab.graphics.barcode import qr
from reportlab.graphics.shapes import Drawing
from reportlab.lib.colors import Color, HexColor
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen import canvas
from reportlab.lib.utils import ImageReader
from reportlab.platypus import Paragraph


ROOT = Path(__file__).resolve().parents[2]
BRAND = ROOT / "public/assets/brand/master-v1"
BACKGROUND = ROOT / "resources/certificates/master-a4-v1.3.1.pdf"
PROOF = ROOT / "output/pdf/master-a4-v1.3.1-design-proof.pdf"
W, H = A4

NAVY = HexColor("#071B2E")
GOLD = HexColor("#B88A2A")
GOLD_LIGHT = HexColor("#D7B85F")
EMERALD = HexColor("#0C675A")
CREAM = HexColor("#FCFAF4")
INK = HexColor("#122333")
MUTED = HexColor("#607080")
LINE = HexColor("#D8D1BE")
PALE_GOLD = HexColor("#F4EDD9")


def register_fonts() -> None:
    fonts = Path("/usr/share/fonts/truetype/dejavu")
    pdfmetrics.registerFont(TTFont("DejaVuSans", str(fonts / "DejaVuSans.ttf")))
    pdfmetrics.registerFont(TTFont("DejaVuSansBold", str(fonts / "DejaVuSans-Bold.ttf")))
    pdfmetrics.registerFont(TTFont("DejaVuSerifBold", str(fonts / "DejaVuSerif-Bold.ttf")))


def transparent_trim(source: Path, target: Path, threshold: int = 242, max_width: int = 480) -> None:
    image = Image.open(source).convert("RGBA")
    pixels = image.load()
    for y in range(image.height):
        for x in range(image.width):
            red, green, blue, alpha = pixels[x, y]
            white = min(red, green, blue)
            if white >= threshold:
                alpha = max(0, int((255 - white) * 255 / max(1, 255 - threshold)))
                pixels[x, y] = (red, green, blue, alpha)
    box = image.getbbox()
    if box:
        image = image.crop(box)
    image.thumbnail((max_width, max_width), Image.Resampling.LANCZOS)
    image.save(target, optimize=True)


def rounded_panel(c: canvas.Canvas, x: float, y: float, width: float, height: float, radius: float = 3 * mm) -> None:
    c.setFillColor(Color(1, 1, 1, alpha=0.83))
    c.setStrokeColor(LINE)
    c.setLineWidth(0.7)
    c.roundRect(x, y, width, height, radius, fill=1, stroke=1)


def background_page(target: Path, prepared: Path) -> None:
    icga = prepared / "icga.png"
    iuoamc = prepared / "iuoamc.png"
    wicp = prepared / "wicp.png"
    transparent_trim(BRAND / "icga-original.jpg", icga, max_width=320)
    transparent_trim(BRAND / "iuoamc-original.png", iuoamc, max_width=340)
    wicp_image = Image.open(BRAND / "wicp-original.webp").convert("RGBA")
    wicp_image.thumbnail((160, 160), Image.Resampling.LANCZOS)
    wicp_image.save(wicp, optimize=True)

    c = canvas.Canvas(str(target), pagesize=A4, pageCompression=1, invariant=1)
    c.setTitle("IUOAMC Enterprise A4 Master Background 1.3.1")
    c.setAuthor("INTERNATIONAL CULINARY & GASTRONOMY ARBITRATION LTD")
    c.setCreator("IUOAMC controlled certificate asset builder")

    c.setFillColor(CREAM)
    c.rect(0, 0, W, H, fill=1, stroke=0)
    c.setStrokeColor(GOLD)
    c.setLineWidth(1.2)
    c.rect(8.5 * mm, 8.5 * mm, W - 17 * mm, H - 17 * mm, fill=0, stroke=1)
    c.setStrokeColor(GOLD_LIGHT)
    c.setLineWidth(0.35)
    c.rect(11 * mm, 11 * mm, W - 22 * mm, H - 22 * mm, fill=0, stroke=1)

    # Subtle security rosette: vector-only, print-safe and intentionally low contrast.
    c.saveState()
    c.translate(W / 2, H / 2 + 4 * mm)
    c.setStrokeColor(Color(0.72, 0.54, 0.16, alpha=0.055))
    c.setLineWidth(0.32)
    for angle in range(0, 180, 6):
        c.saveState()
        c.rotate(angle)
        c.ellipse(-50 * mm, -15 * mm, 50 * mm, 15 * mm, fill=0, stroke=1)
        c.restoreState()
    c.restoreState()

    # Sovereign issuer header.
    c.setFillColor(NAVY)
    c.rect(11 * mm, H - 59 * mm, W - 22 * mm, 48 * mm, fill=1, stroke=0)
    c.setFillColor(GOLD)
    c.rect(11 * mm, H - 60.7 * mm, W - 22 * mm, 1.7 * mm, fill=1, stroke=0)
    c.drawImage(ImageReader(str(icga)), 19 * mm, H - 54.2 * mm, 38 * mm, 38 * mm,
                preserveAspectRatio=True, anchor="c", mask="auto")
    c.setFillColor(GOLD_LIGHT)
    c.setFont("DejaVuSansBold", 6.4)
    c.drawString(63 * mm, H - 22.2 * mm, "UNITED KINGDOM  |  ESTABLISHED PROFESSIONAL AUTHORITY")
    c.setFillColor(HexColor("#FFFFFF"))
    c.setFont("DejaVuSerifBold", 14.8)
    c.drawString(63 * mm, H - 31.5 * mm, "INTERNATIONAL CULINARY &")
    c.drawString(63 * mm, H - 39.1 * mm, "GASTRONOMY ARBITRATION LTD")
    c.setFillColor(HexColor("#DCE6EC"))
    c.setFont("DejaVuSans", 6.6)
    c.drawString(63 * mm, H - 47.2 * mm,
                 "Company 16846998  |  UKPRN 10101250  |  UK Trade Mark UK00004350642")

    c.setFillColor(PALE_GOLD)
    c.rect(11 * mm, H - 67.2 * mm, W - 22 * mm, 6.5 * mm, fill=1, stroke=0)

    # Programme and immutable metadata architecture.
    c.setFillColor(Color(1, 1, 1, alpha=0.76))
    c.setStrokeColor(GOLD)
    c.setLineWidth(0.85)
    c.roundRect(22 * mm, H - 169 * mm, W - 44 * mm, 28 * mm, 4 * mm, fill=1, stroke=1)
    c.setFillColor(GOLD)
    c.rect(22 * mm, H - 169 * mm, 2.2 * mm, 28 * mm, fill=1, stroke=0)

    gap = 4 * mm
    x = 20 * mm
    width = (W - 40 * mm - gap) / 2
    height = 14.2 * mm
    for top in (188.1 * mm, 206.3 * mm):
        y = H - top - height
        rounded_panel(c, x, y, width, height)
        rounded_panel(c, x + width + gap, y, width, height)

    c.setStrokeColor(LINE)
    c.setLineWidth(0.6)
    c.line(20 * mm, H - 224 * mm, W - 20 * mm, H - 224 * mm)
    c.setStrokeColor(GOLD)
    c.setLineWidth(0.75)
    c.line(130 * mm, H - 226.8 * mm, 179 * mm, H - 226.8 * mm)

    # Exact 40 mm official seal zone. A physical NFC chip may be mounted beneath
    # this printed seal without covering the QR code, registry code or signature.
    seal_x = 144 * mm
    seal_y = 21 * mm
    seal_size = 40 * mm
    c.setFillColor(Color(1, 1, 1, alpha=0.94))
    c.setStrokeColor(GOLD)
    c.setLineWidth(1.15)
    c.circle(seal_x + seal_size / 2, seal_y + seal_size / 2, seal_size / 2, fill=1, stroke=1)
    c.setStrokeColor(GOLD_LIGHT)
    c.setLineWidth(0.45)
    c.circle(seal_x + seal_size / 2, seal_y + seal_size / 2, seal_size / 2 - 1.5 * mm, fill=0, stroke=1)
    c.drawImage(ImageReader(str(icga)), seal_x, seal_y, seal_size, seal_size,
                preserveAspectRatio=True, anchor="c", mask="auto")

    # NFC mark sits beside—not over—the 40 mm seal and remains readable after
    # the physical NFC inlay is applied beneath the seal.
    nfc_cx = 135.5 * mm
    nfc_cy = 41 * mm
    c.setStrokeColor(GOLD)
    c.setLineWidth(1.15)
    c.arc(nfc_cx - 5.6 * mm, nfc_cy - 7.0 * mm, nfc_cx + 5.6 * mm, nfc_cy + 7.0 * mm, -62, 124)
    c.arc(nfc_cx - 3.7 * mm, nfc_cy - 4.8 * mm, nfc_cx + 3.7 * mm, nfc_cy + 4.8 * mm, -60, 120)
    c.arc(nfc_cx - 1.8 * mm, nfc_cy - 2.5 * mm, nfc_cx + 1.8 * mm, nfc_cy + 2.5 * mm, -58, 116)
    c.setFillColor(GOLD)
    c.circle(nfc_cx - 1.0 * mm, nfc_cy, 0.85 * mm, fill=1, stroke=0)
    c.setFont("DejaVuSansBold", 5.3)
    c.drawCentredString(135 * mm, 30.5 * mm, "NFC")
    c.setFont("DejaVuSans", 3.5)
    c.drawCentredString(135 * mm, 27.5 * mm, "SECURED")

    # Controlled footer.
    c.setFillColor(NAVY)
    c.rect(11 * mm, 11 * mm, W - 22 * mm, 10 * mm, fill=1, stroke=0)
    c.setFillColor(GOLD_LIGHT)
    c.setFont("DejaVuSansBold", 4.3)
    c.drawString(17 * mm, 17.2 * mm, "INSTITUTIONAL PARTNERS & INTELLECTUAL DOCUMENTATION")
    c.setFillColor(HexColor("#DCE6EC"))
    c.setFont("DejaVuSans", 4.3)
    c.drawString(17 * mm, 13.5 * mm, "Professional certificate - Not an academic degree")
    c.drawImage(ImageReader(str(iuoamc)), 116 * mm, 11.7 * mm, 22 * mm, 8.0 * mm,
                preserveAspectRatio=True, anchor="c", mask="auto")
    c.setFillColor(GOLD_LIGHT)
    c.setFont("DejaVuSerifBold", 7.1)
    c.drawCentredString(158 * mm, 14.4 * mm, "WSACA")
    c.drawImage(ImageReader(str(wicp)), 178 * mm, 11.5 * mm, 15 * mm, 8.5 * mm,
                preserveAspectRatio=True, anchor="c", mask="auto")
    c.setFillColor(HexColor("#8DA1AF"))
    c.setFont("DejaVuSans", 3.8)
    c.drawRightString(W - 17 * mm, 11.9 * mm, "IUOAMC-PRO-CERT | PRINT REV 1.3.1")
    c.showPage()
    c.save()


def text(c: canvas.Canvas, value: str, x: float, top: float, width: float,
         size: float, font: str = "DejaVuSans", colour=INK) -> None:
    c.setFillColor(colour)
    c.setFont(font, size)
    c.drawCentredString(x + width / 2, H - top - size * 0.35, value)


def proof_overlay(target: Path) -> None:
    c = canvas.Canvas(str(target), pagesize=A4, pageCompression=1, invariant=1)
    text(c, "DESIGN PROOF  |  ENTERPRISE CERTIFICATE SYSTEM V3.1  |  NOT YET ISSUED",
         11 * mm, 63.3 * mm, W - 22 * mm, 6.3, "DejaVuSansBold", GOLD)
    text(c, "PROFESSIONAL CREDENTIAL", 20 * mm, 74 * mm, W - 40 * mm, 7.2, "DejaVuSansBold", EMERALD)
    text(c, "Professional Master's Certificate", 18 * mm, 84 * mm, W - 36 * mm, 23, "DejaVuSerifBold")
    text(c, "Awarded with institutional authority to", 25 * mm, 100.5 * mm, W - 50 * mm, 8, "DejaVuSans", MUTED)
    style = ParagraphStyle("recipient", fontName="DejaVuSerifBold", fontSize=18.5, leading=21.5,
                           textColor=INK, alignment=TA_CENTER)
    p = Paragraph("SAMPLE RECIPIENT", style)
    _, ph = p.wrap(W - 52 * mm, 25 * mm)
    p.drawOn(c, 26 * mm, H - 108 * mm - ph)
    text(c, "CERTIFIED PROFESSIONAL PROGRAMME", 24 * mm, 149 * mm, W - 48 * mm, 6.5, "DejaVuSansBold", EMERALD)
    text(c, "Professional Master in Sensory and Restaurant Evaluation", 27 * mm, 157 * mm, W - 54 * mm, 11.2, "DejaVuSansBold")
    text(c, "Has successfully fulfilled the programme and professional assessment requirements",
         25 * mm, 172.5 * mm, W - 50 * mm, 7.1, "DejaVuSans", MUTED)
    text(c, "and is awarded this certificate in recognition of demonstrated competence.",
         25 * mm, 176.8 * mm, W - 50 * mm, 7.1, "DejaVuSans", MUTED)
    text(c, "Demonstrated competency  |  Final score: 92/100",
         25 * mm, 182.5 * mm, W - 50 * mm, 8.8, "DejaVuSansBold")

    cells = [
        (20, 190.3, "CERTIFICATE NUMBER", "ICGA-PMSRE-2026-000000"),
        (107, 190.3, "CREDENTIAL TYPE", "PMSRE"),
        (20, 208.5, "ACHIEVEMENT DATE", "02 AUG 2026"),
        (107, 208.5, "ISSUE DATE", "09 SEP 2026"),
    ]
    for x_mm, top_mm, label, value in cells:
        c.setFillColor(EMERALD)
        c.setFont("DejaVuSansBold", 6.2)
        c.drawString((x_mm + 4) * mm, H - top_mm * mm, label)
        c.setFillColor(INK)
        c.setFont("DejaVuSansBold", 8.6)
        c.drawString((x_mm + 4) * mm, H - (top_mm + 6.6) * mm, value)

    verify_url = "https://iuoamc.pro/verify/c/" + ("0" * 64)
    code = qr.QrCodeWidget(verify_url)
    bounds = code.getBounds()
    size = 26 * mm
    drawing = Drawing(size, size, transform=[size / (bounds[2] - bounds[0]), 0, 0,
                                             size / (bounds[3] - bounds[1]), 0, 0])
    drawing.add(code)
    drawing.drawOn(c, 22 * mm, H - 259.5 * mm)
    c.setFillColor(EMERALD)
    c.setFont("DejaVuSansBold", 6.8)
    c.drawString(51 * mm, H - 239.6 * mm, "AUTHENTICITY VERIFICATION")
    c.setFillColor(MUTED)
    c.setFont("DejaVuSans", 6.1)
    c.drawString(51 * mm, H - 246 * mm, "Scan the secure QR code or visit iuoamc.pro")
    c.drawString(51 * mm, H - 251 * mm, "Digitally signed record - No expiry date specified")
    c.setFillColor(INK)
    c.setFont("DejaVuSansBold", 5.8)
    c.drawString(51 * mm, H - 257.6 * mm, "PROGRAM INTELLECTUAL PROPERTY REGISTRY CODE")
    c.setFont("DejaVuSans", 5.2)
    c.drawString(51 * mm, H - 262.2 * mm, "WICP-PRO-P-2026-b8e657806ac53e6cd5b38d72d34c5bad")
    text(c, "Master Chef Ahmad Maadarani", 130 * mm, 228.5 * mm, 49 * mm, 8.8, "DejaVuSerifBold")
    text(c, "President General & Authorised Signatory", 130 * mm, 234.7 * mm, 49 * mm, 5.8, "DejaVuSans", MUTED)
    c.showPage()
    c.save()


def merge(background: Path, overlay: Path, target: Path) -> None:
    page = PdfReader(str(background)).pages[0]
    page.merge_page(PdfReader(str(overlay)).pages[0])
    writer = PdfWriter()
    writer.add_page(page)
    writer.add_metadata({
        "/Title": "IUOAMC Enterprise Certificate V3.1 - NFC 40 mm Seal Design Proof",
        "/Subject": "Unissued visual QA proof",
        "/Author": "INTERNATIONAL CULINARY & GASTRONOMY ARBITRATION LTD",
        "/Creator": "IUOAMC controlled certificate asset builder",
    })
    with target.open("wb") as stream:
        writer.write(stream)


def main() -> None:
    register_fonts()
    BACKGROUND.parent.mkdir(parents=True, exist_ok=True)
    PROOF.parent.mkdir(parents=True, exist_ok=True)
    with TemporaryDirectory() as directory:
        prepared = Path(directory)
        overlay = prepared / "proof-overlay.pdf"
        background_page(BACKGROUND, prepared)
        proof_overlay(overlay)
        merge(BACKGROUND, overlay, PROOF)
    print(BACKGROUND)
    print(PROOF)


if __name__ == "__main__":
    main()
