"""Verify the content and snapshot metadata of a GNOSIX Trust Passport ZIP."""

import argparse
import hashlib
import json
from pathlib import Path
from uuid import UUID
from zipfile import BadZipFile, ZipFile


def verify(path: Path) -> str:
    files = {"passport.pdf": "application/pdf", "passport.json": "application/json"}
    with ZipFile(path) as archive:
        names = archive.namelist()
        if len(names) != 3 or set(names) != {*files, "manifest.json"}:
            raise ValueError("Unexpected or duplicate archive entries.")
        if archive.getinfo("manifest.json").file_size > 16_384:
            raise ValueError("Manifest exceeds its size limit.")
        manifest = json.loads(archive.read("manifest.json"))
        if manifest["schema"] != "gnosix.trust-manifest.v1" or manifest["algorithm"] != "sha256":
            raise ValueError("Unsupported manifest schema or hash algorithm.")
        if set(manifest["files"]) != set(files):
            raise ValueError("Manifest file list does not match the archive.")
        for name, media_type in files.items():
            if archive.getinfo(name).file_size > 32_000_000:
                raise ValueError(f"File exceeds its size limit: {name}.")
            entry = manifest["files"][name]
            if entry["media_type"] != media_type or entry["bytes"] != archive.getinfo(name).file_size:
                raise ValueError(f"File metadata does not match for {name}.")
            with archive.open(name) as stream:
                digest = hashlib.sha256()
                for chunk in iter(lambda: stream.read(65_536), b""):
                    digest.update(chunk)
            if digest.hexdigest() != entry["sha256"]:
                raise ValueError(f"SHA-256 mismatch for {name}.")
        if archive.getinfo("passport.json").file_size > 10_485_760:
            raise ValueError("Passport JSON exceeds its size limit.")
        passport = json.loads(archive.read("passport.json"))
        if passport["schema"] != "gnosix.trust-passport.v1" or any(
            passport[field] != manifest[field] for field in ("reference", "captured_at")
        ):
            raise ValueError("Passport snapshot does not match the manifest.")
        reference = passport["reference"]
        if not isinstance(reference, str) or not reference.startswith("TP-") or str(UUID(reference[3:])) != reference[3:]:
            raise ValueError("Invalid passport reference.")
        return reference


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("archive", type=Path)
    args = parser.parse_args()
    try:
        reference = verify(args.archive)
    except (BadZipFile, KeyError, OSError, TypeError, ValueError) as error:
        parser.exit(1, f"Verification failed: {error}\n")
    print(f"Verified file integrity for {reference}. No certification or signature was checked.")


if __name__ == "__main__":
    main()
