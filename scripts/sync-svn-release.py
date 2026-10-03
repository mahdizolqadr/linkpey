#!/usr/bin/env python3
"""Copy plugin release files from repo root to WordPress.org SVN trunk and tag."""
from __future__ import annotations

import shutil
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SVN = ROOT / "svn" / "linkpey-svn"
TRUNK = SVN / "trunk"
TAG = SVN / "tags" / "1.1.5"

FILES = ("linkpey.php", "readme.txt", "LICENSE", "uninstall.php", "index.php")
DIRS = ("assets", "includes", "languages")
EXCLUDE = {"svn_password", ".DS_Store"}


def copy_tree(src: Path, dst: Path) -> None:
    if dst.exists():
        shutil.rmtree(dst)
    shutil.copytree(
        src,
        dst,
        ignore=shutil.ignore_patterns(*EXCLUDE),
    )


def main() -> None:
    for name in FILES:
        shutil.copy2(ROOT / name, TRUNK / name)
    for name in DIRS:
        copy_tree(ROOT / name, TRUNK / name)
    (TRUNK / "svn_password").unlink(missing_ok=True)

    if TAG.exists():
        shutil.rmtree(TAG)
    shutil.copytree(TRUNK, TAG)
    (TAG / "svn_password").unlink(missing_ok=True)
    print(f"Synced {ROOT} -> {TRUNK} and {TAG}")


if __name__ == "__main__":
    main()
