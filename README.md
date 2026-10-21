# Tiny Python Calculator

A dependency-free command-line calculator for Python 3.9 or newer. Supports
addition, subtraction, multiplication, and division.

Run from this directory:

```sh
python3 calculator.py 2 + 3
# 5.0
python3 calculator.py 7 / 2
# 3.5
python3 calculator.py -3 '*' 2
# -6.0
python3 calculator.py --help
```

Quote `'*'` so your shell does not expand it into filenames. Use `--` before
the operands if a negative number in scientific notation is parsed as an option:
`python3 calculator.py -- -1e3 + 2`.

Invalid input, division by zero, and nonfinite operands or results print an error
to standard error and exit with status 2. Successful calculations exit with
status 0. Calculations use floating-point arithmetic, so decimal results can
have small rounding errors.

You can also import the function:

```python
from calculator import calculate

assert calculate(7, "/", 2) == 3.5
```

The function raises `ValueError` for unsupported operators, division by zero,
or nonfinite numbers.

Run all tests offline with the Python standard library:

```sh
python3 -m unittest discover -s tests -v
```

Tests cover all operations, decimal and negative numbers, invalid input,
overflow, and command-line success and error behavior. No installation,
network access, credentials, or external services are needed.
