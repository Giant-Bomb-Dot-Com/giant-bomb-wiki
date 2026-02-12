#!/usr/bin/env python3
"""Delete a specific file from a GCS bucket by searching all subdirectories."""

import sys
from google.cloud import storage

BUCKET_NAME = "giantbomb-images"
PREFIX = "uploads/"
TARGET_FILENAME = "464202-nazi.jpg"

def main():
    client = storage.Client()
    bucket = client.bucket(BUCKET_NAME)

    print(f"Searching {BUCKET_NAME}/{PREFIX} for {TARGET_FILENAME}...")

    found = []
    for blob in client.list_blobs(bucket, prefix=PREFIX):
        if blob.name.endswith("/" + TARGET_FILENAME) or blob.name == PREFIX + TARGET_FILENAME:
            found.append(blob)

    if not found:
        print("Not found.")
        return

    for blob in found:
        print(f"Found: gs://{BUCKET_NAME}/{blob.name}")

    confirm = input(f"\nDelete {len(found)} file(s)? [y/N] ")
    if confirm.lower() != "y":
        print("Cancelled.")
        return

    for blob in found:
        blob.delete()
        print(f"Deleted: {blob.name}")

    print("Done.")

if __name__ == "__main__":
    main()
