import json
import unittest
from pathlib import Path


NOTEBOOK = Path(__file__).parents[1] / "notebooks/qwen_colab_t4_api.ipynb"


class ColabNotebookTests(unittest.TestCase):
    def test_notebook_json_and_code_cells_are_valid(self):
        notebook = json.loads(NOTEBOOK.read_text(encoding="utf-8"))
        self.assertEqual(notebook["nbformat"], 4)
        code_cells = [cell for cell in notebook["cells"] if cell["cell_type"] == "code"]
        self.assertGreaterEqual(len(code_cells), 8)
        for cell in code_cells:
            source = "".join(cell["source"])
            if source.lstrip().startswith("%"):
                continue  # IPython %pip cell
            compile(source, str(NOTEBOOK), "exec")
            self.assertEqual(cell.get("outputs", []), [])
            self.assertIsNone(cell.get("execution_count"))

    def test_notebook_has_manual_gpu_secret_and_tunnel_gates(self):
        notebook = json.loads(NOTEBOOK.read_text(encoding="utf-8"))
        content = "\n".join("".join(cell["source"]) for cell in notebook["cells"])
        self.assertIn("QWEN_API_TOKEN", content)
        self.assertIn("START_TUNNEL = False", content)
        self.assertIn("INSTALL_CLOUDFLARED = False", content)
        self.assertIn('"127.0.0.1", port=8000', content)
        self.assertIn("compare_digest", content)
        self.assertIn("sha256(binary).hexdigest()", content)
        self.assertIn('"/health"', content)
        self.assertIn('"/v1/generate"', content)

    def test_notebook_contains_no_saved_secret_or_execution_output(self):
        notebook = json.loads(NOTEBOOK.read_text(encoding="utf-8"))
        for cell in notebook["cells"]:
            self.assertEqual(cell.get("outputs", []), [])
            source = "".join(cell["source"])
            self.assertNotIn("synthetic-test-token", source)


if __name__ == "__main__":
    unittest.main()
