# Sanitized decommissioning archives

Prepared 2026-11-24 for `oemunlocks.com` and `blueprinttechnology.dev`, following an archive-first workflow. Each site has its own archive directory and SHA256SUMS file. Sensitive database contents, tokens, wallet address values, environment files, TLS certificates/private keys, and customer records are excluded from Git; see each site's `ARCHIVE-NOTES.md` and `CLEANUP-MANIFEST.md`.

The cleanup manifest is a proposal only. No production files, services, databases, certificates, domains, or DNS records were changed. Do not remove anything until the owner approves the manifest and the listed encrypted-archive/runtime prerequisites are met.

A local commit is prepared on branch `archive/decommission-sites-20261124`. The push attempt failed before authentication because `github.com` did not resolve. Remote commit and remote completeness verification remain pending until connectivity succeeds.
