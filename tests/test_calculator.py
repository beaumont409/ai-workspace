"""Offline unit and command-line tests using only the standard library."""

from pathlib import Path
import subprocess
import sys
import unittest

from calculator import calculate


SCRIPT = Path(__file__).resolve().parents[1] / "calculator.py"


class CalculatorTests(unittest.TestCase):
    def test_operations(self):
        for left, operation, right, expected in [
            (2, "+", 3, 5),
            (2, "-", 3, -1),
            (-2, "*", 3, -6),
            (7, "/", 2, 3.5),
            (0.1, "+", 0.2, 0.3),
            (0, "*", 7, 0),
        ]:
            with self.subTest(operation=operation, left=left):
                self.assertAlmostEqual(calculate(left, operation, right), expected)

    def test_invalid_operations(self):
        with self.assertRaisesRegex(ValueError, "Unsupported operation"):
            calculate(1, "**", 2)

    def test_zero_division(self):
        for zero in (0, -0.0):
            with self.subTest(zero=zero):
                with self.assertRaisesRegex(ValueError, "divide by zero"):
                    calculate(1, "/", zero)

    def test_nonfinite_numbers(self):
        for number in (float("nan"), float("inf"), float("-inf")):
            for left, right in ((number, 1), (1, number)):
                with self.subTest(left=left, right=right):
                    with self.assertRaisesRegex(ValueError, "finite"):
                        calculate(left, "+", right)

    def test_overflow(self):
        with self.assertRaisesRegex(ValueError, "finite"):
            calculate(1e308, "*", 1e308)

    def run_cli(self, *args):
        return subprocess.run(
            [sys.executable, str(SCRIPT), *args],
            capture_output=True, text=True, check=False, timeout=5,
        )

    def test_cli_success(self):
        result = self.run_cli("-3", "*", "2")
        self.assertEqual(result.returncode, 0)
        self.assertEqual(result.stdout.strip(), "-6.0")
        self.assertEqual(result.stderr, "")

    def test_cli_errors(self):
        for args, message in [
            (("1", "/", "0"), "Cannot divide by zero"),
            (("abc", "+", "2"), "invalid float value"),
            (("1", "**", "2"), "invalid choice"),
            (("nan", "+", "2"), "finite"),
            (("1", "+"), "required"),
        ]:
            with self.subTest(args=args):
                result = self.run_cli(*args)
                self.assertEqual(result.returncode, 2)
                self.assertIn(message, result.stderr)
                self.assertEqual(result.stdout, "")
                self.assertNotIn("Traceback", result.stderr)


if __name__ == "__main__":
    unittest.main()
