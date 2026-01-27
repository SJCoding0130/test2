import os
import sys
import requests
from pathlib import Path
import subprocess
import time

# ----------------------------------
# Force unbuffered stdout (CI safe)
# ----------------------------------
sys.stdout.reconfigure(line_buffering=True)

# ----------------------------------
# Wait until PHP server is ready
# ----------------------------------
def wait_for_server(url, timeout=20):
    print("Waiting for PHP server...", flush=True)
    start = time.time()
    while time.time() - start < timeout:
        try:
            r = requests.get(url, timeout=2)
            if r.status_code < 500:
                print("PHP server is ready!", flush=True)
                return
        except requests.exceptions.RequestException:
            time.sleep(1.0)
    raise RuntimeError(f"PHP server did not start at {url} within {timeout}s")

# ----------------------------------
# Paths (repo_b directory)
# ----------------------------------
BASE_DIR = Path(r"D:\a\tester\tester\repo_b").resolve()
print(f"BASE_DIR = {BASE_DIR}", flush=True)

JSON_FOLDER = BASE_DIR / "json"
OUTPUT_BASE = BASE_DIR / "output"
OUTPUT_BASE.mkdir(parents=True, exist_ok=True)

# ----------------------------------
# Start PHP built-in server
# ----------------------------------
print("Starting PHP server...", flush=True)
php_process = subprocess.Popen(
    [
        "php",
        "-d", "upload_max_filesize=50M",
        "-d", "post_max_size=50M",
        "-d", "max_execution_time=300",
        "-S", "localhost:8000"
    ],
    cwd=BASE_DIR,
    stdout=subprocess.DEVNULL,
    stderr=subprocess.DEVNULL
)


try:
    wait_for_server("http://localhost:8000")
except RuntimeError:
    stdout, stderr = php_process.communicate(timeout=5)
    print("PHP STDOUT:\n", stdout.decode(), file=sys.stderr)
    print("PHP STDERR:\n", stderr.decode(), file=sys.stderr)
    php_process.terminate()
    raise

# ----------------------------------
# Endpoint URL
# ----------------------------------
URL = "http://localhost:8000/view.php"

# ----------------------------------
# Languages
# ----------------------------------
LANGUAGES = [
    "Text",
    "English",
    "ChineseTraditional",
    "ChineseSimplified"
]

# ----------------------------------
# Process JSON files
# ----------------------------------
json_files = sorted(JSON_FOLDER.glob("*.book.json"))
print(f"Found {len(json_files)} JSON files", flush=True)

for file_path in json_files:
    print("=" * 40, flush=True)
    print(f"Processing file: {file_path.name}", flush=True)
    print("=" * 40, flush=True)

    for lang in LANGUAGES:
        print(f"  Language: {lang}", flush=True)

        lang_label = "Japanese" if lang == "Text" else lang
        lang_folder = OUTPUT_BASE / "".join(
            c for c in lang_label if c.isalnum() or c in "_-"
        )
        lang_folder.mkdir(parents=True, exist_ok=True)

        form = {"language": lang, "playerName": ""}

        try:
            with open(file_path, "rb") as f:
                files = {"jsonFile": f}
                response = requests.post(
                    URL,
                    data=form,
                    files=files,
                    timeout=120   # 🚨 CRITICAL: prevent hangs
                )
                time.sleep(0.3)
        except requests.exceptions.Timeout:
            print(f"     TIMEOUT: {file_path.name} [{lang}]", flush=True)
            continue
        except requests.exceptions.RequestException as e:
            print(f"     REQUEST ERROR: {e}", flush=True)
            continue

        print(
            f"    HTTP {response.status_code}, "
            f"Response length: {len(response.text)}",
            flush=True
        )

        output_file = lang_folder / f"{file_path.stem}.html"
        with open(output_file, "w", encoding="utf-8") as out_f:
            out_f.write(response.text)

        print(f"    Saved: {output_file}", flush=True)

# ----------------------------------
# Cleanup PHP server
# ----------------------------------
print("Stopping PHP server...", flush=True)
php_process.terminate()
php_process.wait(timeout=5)

print("PHP server terminated.", flush=True)
print("All files & languages processed!", flush=True)




