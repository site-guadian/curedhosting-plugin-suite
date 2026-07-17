# Key Maker

This standalone app generates license keys for the CuredHosting Plugin Suite.

The app is independent from WordPress and can be used from the command line.

## Usage

From the `key-maker` folder:

```bash
php generate-key.php --email="customer@example.com" --tier=pro
```

Optional parameters:

- `--email` — customer email address
- `--tier` — `free`, `pro`, or `corporate`
- `--secret` — optional shared secret to make the output reproducible across systems

Example:

```bash
php generate-key.php --email="alice@example.com" --tier=corporate --secret="my-secret"
```
