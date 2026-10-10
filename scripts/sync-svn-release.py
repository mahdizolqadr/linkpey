#!/usr/bin/env python3
"""Copy plugin source from repo/ to WordPress.org SVN trunk and tag."""
from __future__ import annotations

import argparse
import shutil
import sys
from pathlib import Path

PROJECT_ROOT = Path(__file__).resolve().parents[1]
PLUGIN_SRC = PROJECT_ROOT / "repo"
SVN = PROJECT_ROOT / "svn" / "linkpey-svn"
TRUNK = SVN / "trunk"

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


def sync_trunk() -> None:
    if not PLUGIN_SRC.is_dir():
        print(f"Plugin source not found: {PLUGIN_SRC}", file=sys.stderr)
        sys.exit(1)
    if not TRUNK.is_dir():
        print(f"SVN trunk not found: {TRUNK}", file=sys.stderr)
        sys.exit(1)

    for name in FILES:
        shutil.copy2(PLUGIN_SRC / name, TRUNK / name)
    for name in DIRS:
        copy_tree(PLUGIN_SRC / name, TRUNK / name)
    (TRUNK / "svn_password").unlink(missing_ok=True)


def sync_tag(version: str) -> None:
    tag_dir = SVN / "tags" / version
    if tag_dir.exists():
        shutil.rmtree(tag_dir)
    shutil.copytree(TRUNK, tag_dir)
    (tag_dir / "svn_password").unlink(missing_ok=True)


def main() -> None:
    parser = argparse.ArgumentParser(description="Sync repo/ to WordPress.org SVN.")
    parser.add_argument(
        "--tag",
        required=True,
        help="Release version for tags/X.Y.Z (must match readme Stable tag).",
    )
    args = parser.parse_args()

    sync_trunk()
    sync_tag(args.tag.strip())
    print(f"Synced {PLUGIN_SRC} -> {TRUNK} and {SVN / 'tags' / args.tag}")


if __name__ == "__main__":
    main()
