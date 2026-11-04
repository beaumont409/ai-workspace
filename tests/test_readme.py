"""Offline checks for the synthetic automation test project."""

from pathlib import Path
import unittest


class ReadmeTest(unittest.TestCase):
    def test_readme_exists(self):
        readme = Path(__file__).resolve().parents[1] / "README.md"
        self.assertTrue(readme.is_file(), "README.md must exist as a regular file")


if __name__ == "__main__":
    unittest.main()
