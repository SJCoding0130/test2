import os
import requests
import json
from datetime import datetime

API_URL = "https://gateway.live-a-hero.jp/api/status/version"
STATIC_URL = "https://d1itvxfdul6wxg.cloudfront.net"
USER_AGENT = "LiveAHeroAPI"

OUTPUT_FILE = "assetList.txt"
METADATA_FILE = "assetMetadata.json"
ASSET_DIR = "downloaded_assets"

FILTER_KEYWORDS = [
    "gacha_assets_all",
    ".chapter",
    ".book",
]


def get_version():
    headers = {"User-Agent": USER_AGENT}
    response = requests.get(API_URL, headers=headers, timeout=10)
    if not response.ok:
        print("Failed to fetch version")
        return None
    data = response.json()
    return data.get("client")


def list_assets(app_version: str):
    url = f"{STATIC_URL}/{app_version}/assetList.Android"
    headers = {"User-Agent": f"{USER_AGENT}/{app_version}"}
    response = requests.get(url, headers=headers, timeout=10)
    if not response.ok:
        print("Failed to fetch asset list")
        return []

    file_paths = []
    for line in response.text.splitlines():
        if not line.strip():
            continue
        file_path = line.split(",")[0]
        if any(keyword in file_path for keyword in FILTER_KEYWORDS):
            file_paths.append(file_path)
    return file_paths


def load_previous_metadata():
    if os.path.exists(METADATA_FILE):
        with open(METADATA_FILE, "r", encoding="utf-8") as f:
            return json.load(f)
    return {}


def get_clean_filename(file_path):
    """
    Convert a server filename like:
    gacha_assets_all_d32983b98ea4177f864e3bddbd3848d2.bundle
    debugassets_assets_textcheck.chapter_f12681e0ea1f86ee71dbd11f298b3a7d.bundle
    to clean asset filenames:
    gacha_assets_all.asset
    textcheck.chapter.asset
    """
    basename = os.path.basename(file_path)
    
    # Remove hash before .bundle
    if "_" in basename:
        name_part = basename.rsplit("_", 1)[0]  # Remove last underscore + hash
    else:
        name_part = basename
    
    # Remove known prefixes
    prefixes = ["duplicateasset_assets_", "debugassets_assets_"]
    for prefix in prefixes:
        if name_part.startswith(prefix):
            name_part = name_part[len(prefix):]
            break  # Only remove one prefix

    # Add .asset extension
    new_filename = name_part + ".asset"
    return new_filename



def download_assets(app_version: str, file_paths: list, prev_metadata: dict):
    os.makedirs(ASSET_DIR, exist_ok=True)
    metadata = {}

    for idx, file_path in enumerate(file_paths, 1):
        url = f"{STATIC_URL}/{app_version}/{file_path}"
        headers = {"User-Agent": f"{USER_AGENT}/{app_version}"}
        clean_name = get_clean_filename(file_path)
        local_path = os.path.join(ASSET_DIR, clean_name)

        try:
            # Use HEAD request to check ETag first
            head_resp = requests.head(url, headers=headers, timeout=10)
            if head_resp.ok:
                etag = head_resp.headers.get("ETag", "N/A")
                last_modified = head_resp.headers.get("Last-Modified", "N/A")
                #last_checked = datetime.utcnow().isoformat()

                prev_etag = prev_metadata.get(clean_name, {}).get("ETag")
                if etag == prev_etag:
                    print(f"[{idx}/{len(file_paths)}] Skipped (ETag matched): {clean_name}")
                    metadata[clean_name] = {
                        "ETag": etag,
                        "Last-Modified": last_modified,
                        #"Last-Checked": last_checked
                    }
                    continue

                # Download file
                response = requests.get(url, headers=headers, stream=True, timeout=30)
                if response.ok:
                    with open(local_path, "wb") as f:
                        for chunk in response.iter_content(chunk_size=8192):
                            f.write(chunk)
                    print(f"[{idx}/{len(file_paths)}] Downloaded: {clean_name}")
                else:
                    print(f"[{idx}/{len(file_paths)}] Failed to download: {clean_name}")

                metadata[clean_name] = {
                    "ETag": etag,
                    "Last-Modified": last_modified,
                    #"Last-Checked": last_checked
                }
            else:
                print(f"[{idx}/{len(file_paths)}] HEAD request failed: {clean_name}")
                metadata[clean_name] = {
                    "ETag": "Failed",
                    "Last-Modified": "Failed",
                    "Last-Checked": datetime.utcnow().isoformat()
                }

        except Exception as e:
            print(f"[{idx}/{len(file_paths)}] Error: {clean_name} -> {e}")
            metadata[clean_name] = {
                "ETag": "Error",
                "Last-Modified": "Error",
                "Last-Checked": datetime.utcnow().isoformat()
            }

    return metadata


def main():
    app_version = get_version()
    if not app_version:
        print("No version info available")
        return

    print(f"Latest App Version: {app_version}")

    files = list_assets(app_version)
    print(f"Total assets to check/download: {len(files)}")

    # Save asset filenames (clean names)
    # Generate cleaned filenames and sort them in ascending order
    clean_filenames = sorted(get_clean_filename(file_path) for file_path in files)

    with open(OUTPUT_FILE, "w", encoding="utf-8") as f:
        for filename in clean_filenames:
            f.write(filename + "\n")
    print(f"Asset filenames saved to {OUTPUT_FILE}")

    # Load previous metadata
    prev_metadata = load_previous_metadata()

    # Download assets if needed
    metadata = download_assets(app_version, files, prev_metadata)

    # Save updated metadata
    with open(METADATA_FILE, "w", encoding="utf-8") as f:
        json.dump(metadata, f, indent=2)
    print(f"Asset metadata saved to {METADATA_FILE}")
    print(f"Assets are stored in: {ASSET_DIR}")


if __name__ == "__main__":
    main()


