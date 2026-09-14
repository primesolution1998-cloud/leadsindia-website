"""Compose a private, reviewable English book PDF; never publish or accept markup."""
from __future__ import annotations
import argparse
import hashlib
import json
import os
import re
import tempfile
from pathlib import Path
from xml.sax.saxutils import escape
from reportlab.lib import colors
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.enums import TA_LEFT
from reportlab.lib.pagesizes import A5
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, PageBreak, KeepTogether

INK = colors.HexColor("#10243B")
ACCENT = colors.HexColor("#16B8A6")
WIDTH, HEIGHT = A5

def text(value, maximum=6000):
    if not isinstance(value, str) or not value.strip() or len(value) > maximum:
        raise ValueError("Invalid or missing text")
    if any(ord(c) > 126 or (ord(c) < 32 and c not in "\n\t") for c in value):
        raise ValueError("This renderer supports English ASCII text only; multilingual font setup required")
    if re.search(r"\b(TODO|TBD|lorem ipsum|insert here)\b", value, re.I):
        raise ValueError("Unfinished placeholder found")
    return value.strip()

def validate(book):
    if not isinstance(book, dict) or book.get("language") != "en":
        raise ValueError("An English book manifest is required")
    for field, maximum in [("title", 110), ("subtitle", 220), ("audience", 180)]:
        text(book.get(field), maximum)
    for field in ("mrp_paise", "price_paise"):
        if type(book.get(field)) is not int or not 100 <= book[field] <= 10000000:
            raise ValueError("Prices must be positive integer paise")
    if book["price_paise"] > book["mrp_paise"]:
        raise ValueError("Selling price exceeds MRP")
    chapters = book.get("chapters")
    if not isinstance(chapters, list) or not 3 <= len(chapters) <= 30:
        raise ValueError("Expected 3-30 complete chapters")
    chapter_titles, chapter_bodies = set(), set()
    words = 0
    for chapter in chapters:
        if not isinstance(chapter, dict):
            raise ValueError("Invalid chapter")
        title = text(chapter.get("title"), 120)
        key = re.sub(r"[^a-z0-9]", "", title.lower())
        if key in chapter_titles:
            raise ValueError("Duplicate chapter title")
        chapter_titles.add(key)
        paragraphs = chapter.get("paragraphs")
        if not isinstance(paragraphs, list) or not 2 <= len(paragraphs) <= 100:
            raise ValueError("Missing chapter paragraphs")
        body = "\n".join(text(p) for p in paragraphs)
        count = len(body.split())
        if count < 250:
            raise ValueError("Chapter is too short for book preflight")
        digest = hashlib.sha256(re.sub(r"\s+", " ", body.lower()).encode()).hexdigest()
        if digest in chapter_bodies:
            raise ValueError("Duplicate chapter content")
        chapter_bodies.add(digest)
        words += count
        exercises = chapter.get("exercises")
        if not isinstance(exercises, list) or not 3 <= len(exercises) <= 30:
            raise ValueError("At least three exercises per chapter required")
        for exercise in exercises:
            if not isinstance(exercise, dict):
                raise ValueError("Invalid exercise")
            text(exercise.get("question"), 1500)
            text(exercise.get("answer"), 2500)
    if words < 1500:
        raise ValueError("Book must contain at least 1500 instructional words")
    return words

def compose(book, output: Path):
    word_count = validate(book)
    digest = hashlib.sha256(json.dumps(book, sort_keys=True, ensure_ascii=True,
                           separators=(",", ":")).encode()).hexdigest()
    if output.exists():
        raise FileExistsError("Refusing to overwrite an existing PDF")
    output.parent.mkdir(parents=True, exist_ok=True)
    styles = {
        "body": ParagraphStyle("body", fontName="Helvetica", fontSize=10.5,
            leading=16, textColor=INK, spaceAfter=10, splitLongWords=True),
        "h1": ParagraphStyle("h1", fontName="Helvetica-Bold", fontSize=23,
            leading=28, textColor=INK, spaceAfter=20),
        "h2": ParagraphStyle("h2", fontName="Helvetica-Bold", fontSize=14,
            leading=19, textColor=INK, spaceBefore=12, spaceAfter=12),
        "small": ParagraphStyle("small", fontName="Helvetica", fontSize=9,
            leading=13, textColor=colors.HexColor("#52667B"), spaceAfter=10),
    }
    story = [Spacer(1, 35), Paragraph(escape(book["title"]), styles["h1"]),
             Paragraph(escape(book["subtitle"]), styles["body"]),
             Spacer(1, 22), Paragraph("YTC EDUCATION", styles["h2"]),
             Paragraph(escape(book["audience"]), styles["body"]),
             Spacer(1, 24),
             Paragraph(f'MRP INR {book["mrp_paise"]/100:.2f} | '
                       f'Price INR {book["price_paise"]/100:.2f}', styles["body"]),
             Paragraph("REVIEW COPY - editorial approval pending", styles["small"]),
             PageBreak(), Paragraph("Contents", styles["h1"])]
    for index, chapter in enumerate(book["chapters"], 1):
        story.append(Paragraph(f'{index}. {escape(chapter["title"])}', styles["body"]))
    story.extend([Spacer(1, 15), Paragraph("Answer keys follow each chapter. "
                  "Read, practise, then check your answers.", styles["small"])])
    for index, chapter in enumerate(book["chapters"], 1):
        story.extend([PageBreak(), Paragraph(f'CHAPTER {index}', styles["small"]),
                      Paragraph(escape(chapter["title"]), styles["h1"])])
        for paragraph in chapter["paragraphs"]:
            story.append(Paragraph(escape(paragraph).replace("\n", "<br/>"), styles["body"]))
        story.append(Paragraph("Practice", styles["h2"]))
        for number, exercise in enumerate(chapter["exercises"], 1):
            story.append(Paragraph(f'{number}. {escape(exercise["question"])}', styles["body"]))
            story.append(Spacer(1, 12))
        story.append(Paragraph("Answer key", styles["h2"]))
        for number, exercise in enumerate(chapter["exercises"], 1):
            story.append(Paragraph(f'{number}. {escape(exercise["answer"])}', styles["body"]))
    pages = []
    def decorate(canvas, document):
        canvas.saveState()
        canvas.setFillColor(ACCENT)
        canvas.rect(0, HEIGHT-12, WIDTH, 12, fill=1, stroke=0)
        canvas.setFillColor(INK)
        canvas.setFont("Helvetica", 8)
        canvas.drawString(36, 22, "YTC EDUCATION | REVIEW COPY")
        canvas.drawRightString(WIDTH-36, 22, str(document.page))
        canvas.restoreState()
        pages.append(document.page)
    fd, temporary = tempfile.mkstemp(prefix=".book-", suffix=".pdf", dir=output.parent)
    os.close(fd)
    try:
        doc = SimpleDocTemplate(temporary, pagesize=A5, rightMargin=36,
            leftMargin=36, topMargin=38, bottomMargin=42,
            title=book["title"], author="YTC Education", pageCompression=1)
        doc.build(story, onFirstPage=decorate, onLaterPages=decorate)
        # Atomic create with no overwrite, including concurrent callers.
        os.link(temporary, output)
        os.chmod(output, 0o640)
    finally:
        Path(temporary).unlink(missing_ok=True)
    return {"status": "composed_for_review", "manuscript_sha256": digest,
            "pdf_sha256": hashlib.sha256(output.read_bytes()).hexdigest(),
            "pages": max(pages), "instructional_words": word_count,
            "editorial_verified": False, "published": False}

if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("manifest", type=Path)
    parser.add_argument("output", type=Path)
    args = parser.parse_args()
    if args.manifest.stat().st_size > 4_000_000:
        raise SystemExit("Manifest exceeds 4 MB")
    try:
        print(json.dumps(compose(json.loads(args.manifest.read_text()), args.output)))
    except (ValueError, FileExistsError) as error:
        raise SystemExit(str(error))
