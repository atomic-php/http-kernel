# Contributing

Thank you for your interest in contributing!

- Use PHP 8.4+ with strict types and PSR-12 style.
- Run quality checks before pushing:
  - `composer qa`
  - `composer test-coverage`
- Include tests for new behavior; keep tests fast and deterministic.
- For performance-sensitive changes, include benchmark results:
  - `composer benchmark`
- Avoid breaking public APIs. Open an issue for discussion first.

Pull requests should include a clear description, rationale, and before/after behavior. Attach logs, metrics, or screenshots if relevant.
