"""Offline checks for the synthetic automation test project."""

from pathlib import Path
import unittest


class ReadmeTest(unittest.TestCase):
    def test_readme_exists(self):
        project_root = Path(__file__).resolve().parent.parent
        self.assertTrue(
            (project_root / "README.md").is_file(),
            "The project must contain README.md",
        )


if __name__ == "__main__":
    unittest.main()
