import UnityPy
import sys
import os
import json


# Base output directory (repo_b/json)
OUTPUT_DIR = os.path.join(os.getcwd(), "json")
os.makedirs(OUTPUT_DIR, exist_ok=True)


def write_json(bundle_path,mb_name, data, suffix):
    base_name = os.path.splitext(os.path.basename(bundle_path))[0]
    base_name = base_name.replace(".chapter", "").replace(".book", "")
    output_path = os.path.join(OUTPUT_DIR, f"{mb_name}.json")

    with open(output_path, "w", encoding="utf-8") as f:
        json.dump(data, f, indent=4, ensure_ascii=False)

    print(f"[INFO] Saved JSON : {output_path}")


def extract_chapter(bundle_path):
    try:
        env = UnityPy.load(bundle_path)
    except Exception as e:
        print(f"[ERROR] Failed to load UnityFS: {e}")
        return

    found = False

    for obj in env.objects:
        if obj.type.name != "MonoBehaviour":
            continue

        mb = obj.read()
        name = getattr(mb, "m_Name", "")

        if name.endswith(".chapter"):
            found = True
            data = obj.read_typetree()
            write_json(bundle_path, name, data, "chapter")

    if not found:
        print("[WARNING] No .chapter MonoBehaviour found.")


def extract_book(bundle_path):
    try:
        env = UnityPy.load(bundle_path)
    except Exception as e:
        print(f"[ERROR] Failed to load UnityFS: {e}")
        return

    found = False

    for obj in env.objects:
        if obj.type.name != "MonoBehaviour":
            continue

        mb = obj.read()
        name = getattr(mb, "m_Name", "")

        if name.endswith(".book"):
            found = True
            data = obj.read_typetree()
            write_json(bundle_path,name, data, "book")

    if not found:
        print("[WARNING] No .book MonoBehaviour found.")


def auto_extract(bundle_path):
    filename = os.path.basename(bundle_path)

    if filename == "common.chapter.asset":
        print(f"[INFO] Detected CHAPTER asset : {filename}")
        extract_chapter(bundle_path)
    else:
        print(f"[INFO] Running BOOK extractor : {filename}")
        extract_book(bundle_path)


def process_directory(directory):
    print(f"[INFO] Processing directory : {directory}")

    for root, _, files in os.walk(directory):
        for file in files:
            if file.endswith(".asset"):
                auto_extract(os.path.join(root, file))


if __name__ == "__main__":
    assets_dir = os.path.join(os.getcwd(), "downloaded_assets")

    if not os.path.isdir(assets_dir):
        print(f"[ERROR] Directory not found: {assets_dir}")
        sys.exit(1)

    process_directory(assets_dir)