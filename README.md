# Minimal verification project

This is a non-production project containing documentation and one offline validation check. It does not submit or deliver contact messages.

No network access, real website data, payments, DNS, deployment, or credentials are required or used.

Run the check from this directory:

```sh
python3 -c "from pathlib import Path; p = Path('README.md'); assert p.is_file() and p.stat().st_size > 0, 'README.md must exist and not be empty'"
```

The same check is recorded in `claw-validation.json`.
