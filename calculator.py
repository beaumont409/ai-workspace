"""A tiny four-operation calculator with a command-line interface."""

import argparse
import math
import operator


OPERATIONS = {
    "+": operator.add,
    "-": operator.sub,
    "*": operator.mul,
    "/": operator.truediv,
}


def calculate(left: float, operation: str, right: float) -> float:
    """Calculate using finite numbers; raise ValueError for invalid input."""
    if operation not in OPERATIONS:
        raise ValueError(f"Unsupported operation: {operation}")
    if not math.isfinite(left) or not math.isfinite(right):
        raise ValueError("Operands must be finite numbers")
    if operation == "/" and right == 0:
        raise ValueError("Cannot divide by zero")
    result = OPERATIONS[operation](left, right)
    if not math.isfinite(result):
        raise ValueError("Result must be a finite number")
    return result


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("left", type=float, help="first number")
    parser.add_argument("operation", choices=OPERATIONS, help="arithmetic operator")
    parser.add_argument("right", type=float, help="second number")
    args = parser.parse_args()
    try:
        result = calculate(args.left, args.operation, args.right)
    except ValueError as error:
        parser.error(str(error))
    print(result)


if __name__ == "__main__":
    main()
