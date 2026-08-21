#!/usr/bin/env python3
"""Validate a WordPress plugin ZIP without normalizing its entry names."""

from __future__ import annotations

import re
import sys
import tempfile
import zipfile
from pathlib import Path


REQUIRED_FILES = (
    "halopress-live2d.php",
    "uninstall.php",
    "LICENSE",
    "README.md",
    "readme.txt",
    "THIRD-PARTY-NOTICES.md",
    "assets/admin.css",
    "assets/images/live2d-logo.png",
    "includes/class-halopress-live2d.php",
    "includes/class-halopress-live2d-admin.php",
    "live2d-widget/widget.css",
    "live2d-widget/widget.js",
)


def fail(message: str) -> None:
    raise SystemExit(f"Release validation failed: {message}")


def main() -> None:
    if len(sys.argv) != 2:
        fail("usage: verify-release.py <archive.zip>")

    archive_path = Path(sys.argv[1])
    if not archive_path.is_file():
        fail(f"archive does not exist: {archive_path}")

    with zipfile.ZipFile(archive_path) as archive:
        entries = [entry.filename for entry in archive.infolist()]

        if any("\\" in entry for entry in entries):
            fail("ZIP entries must not contain Windows backslashes")
        if any(entry in {".", "./"} or entry.startswith("./") for entry in entries):
            fail("ZIP entries must not contain an explicit dot directory")
        if any(
            entry.startswith("/")
            or re.match(r"^[A-Za-z]:", entry)
            or ".." in entry.split("/")
            for entry in entries
        ):
            fail("ZIP contains an unsafe path")
        if any(entry.startswith("halopress-live2d/") for entry in entries):
            fail("ZIP must not contain an extra halopress-live2d directory layer")

        missing = [file for file in REQUIRED_FILES if file not in entries]
        if missing:
            fail("ZIP is missing required files: " + ", ".join(missing))

        forbidden = [
            entry
            for entry in entries
            if entry.endswith((".moc", ".moc3", ".model3.json", ".motion3.json"))
            or "CubismSdkForWeb" in entry
            or entry.endswith("live2d.min.js")
        ]
        if forbidden:
            fail("ZIP contains forbidden release assets: " + ", ".join(forbidden))

        with tempfile.TemporaryDirectory() as temporary_directory:
            archive.extractall(temporary_directory)
            extracted_root = Path(temporary_directory)
            missing_after_extract = [
                file for file in REQUIRED_FILES if not (extracted_root / file).is_file()
            ]
            if missing_after_extract:
                fail(
                    "extracted ZIP is missing required files: "
                    + ", ".join(missing_after_extract)
                )

    print(f"Validated {archive_path} ({len(entries)} entries)")


if __name__ == "__main__":
    main()
