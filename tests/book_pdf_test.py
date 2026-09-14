import copy
import importlib.util
import tempfile
import unittest
from pathlib import Path
spec=importlib.util.spec_from_file_location("book_pdf",Path(__file__).resolve().parents[1]/"scripts/book_pdf.py")
module=importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)
def fixture():
    chapters=[]
    for n in range(3):
        paragraphs=[" ".join(f"Chapter {n+1} paragraph {i+1} sentence {k+1} teaches clear English practice."
                    for k in range(35)) for i in range(2)]
        chapters.append({"title":f"Lesson {n+1}","paragraphs":paragraphs,
            "exercises":[{"question":f"Practice question {i+1}?","answer":"A complete sample answer."} for i in range(3)]})
    return {"title":"Renderer Verification","subtitle":"Synthetic test content, not a saleable book",
        "audience":"Internal testing only","language":"en","mrp_paise":39900,"price_paise":19900,"chapters":chapters}
class RendererTests(unittest.TestCase):
    def test_pdf(self):
        with tempfile.TemporaryDirectory() as d:
            path=Path(d)/"book.pdf"
            report=module.compose(fixture(),path)
            self.assertTrue(path.read_bytes().startswith(b"%PDF-"))
            self.assertGreater(report["pages"],5)
            self.assertFalse(report["published"])
            self.assertFalse(report["editorial_verified"])
            with self.assertRaises(FileExistsError):module.compose(fixture(),path)
    def test_incomplete_and_duplicate(self):
        for mutation in ["short","duplicate","answer","price","unicode"]:
            book=fixture()
            if mutation=="short":book["chapters"][0]["paragraphs"]=["Too short.","Still short."]
            elif mutation=="duplicate":book["chapters"][1]=copy.deepcopy(book["chapters"][0])
            elif mutation=="answer":del book["chapters"][0]["exercises"][0]["answer"]
            elif mutation=="price":book["price_paise"]=50000
            else:book["title"]="Hindi \u0939"
            with self.assertRaises(ValueError,msg=mutation):module.validate(book)
    def test_markup_is_text(self):
        with tempfile.TemporaryDirectory() as d:
            book=fixture()
            book["title"]='<img src="file:///secret">'
            module.compose(book,Path(d)/"escaped.pdf")
if __name__=="__main__":unittest.main()
