# LinkPey WordPress plugin (monorepo layout)

| Path | Purpose |
|------|---------|
| **`repo/`** | Canonical plugin source — commit this to **Git** (matches WordPress plugin root). |
| **`svn/linkpey-svn/`** | WordPress.org SVN working copy (not in Git; see `.gitignore`). |
| **`scripts/`** | Release helpers (`sync-svn-release.py`). |
| **`docs/`** | Submission notes, internal documentation. |
| **`devtools/`** | Optional CLI tools (not shipped to wordpress.org). |

## Git

Track the project root; plugin code lives under `repo/`.

```bash
git add repo/ scripts/ docs/ devtools/ README.md .gitignore
git commit -m "Your message"
```

## WordPress.org SVN

1. Edit version in `repo/linkpey.php` and `repo/readme.txt` (`Stable tag`).
2. Sync and create tag:

```bash
python3 scripts/sync-svn-release.py --tag 1.1.5
cd svn/linkpey-svn
svn status
svn add --force tags/1.1.5   # when new tag
svn commit -m "Release 1.1.5: …"
```

Git ignores `svn/`, all `*.zip` archives, and `svn_password` (see `.gitignore`).

**Typical commit:** `repo/`, `scripts/`, `docs/`, `README.md`, `.gitignore` — not zips or SVN checkout.

## Install ZIP (manual upload)

```bash
cd repo && zip -r ../linkpey-$(grep 'Stable tag' readme.txt | awk '{print $3}').zip .
```
