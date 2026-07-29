import re
from collections import defaultdict

log_path = "/Applications/XAMPP/xamppfiles/htdocs/Sistem-Manajemen-Sekolah-terpadu-main/backend/storage/logs/laravel.log"

with open(log_path, "r", encoding="utf-8", errors="ignore") as f:
    lines = f.readlines()

print(f"Total lines in log: {len(lines)}")

errors = []
current_error = None

entry_pattern = re.compile(r"^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)")

for i, line in enumerate(lines):
    match = entry_pattern.match(line)
    if match:
        if current_error:
            errors.append(current_error)
        current_error = {
            "line_num": i + 1,
            "level": match.group(1),
            "header": match.group(2),
            "lines": [line]
        }
    else:
        if current_error:
            current_error["lines"].append(line)

if current_error:
    errors.append(current_error)

print(f"Total error entries found: {len(errors)}")

groups = defaultdict(list)
for err in errors:
    hdr = err["header"]
    norm = re.sub(r"[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}", "UUID", hdr)
    norm = re.sub(r"\d+", "N", norm)
    groups[norm].append(err)

print(f"\n--- UNIQUE ERROR PATTERNS ({len(groups)}) ---")
for norm, err_list in sorted(groups.items(), key=lambda x: len(x[1]), reverse=True):
    first = err_list[0]
    last = err_list[-1]
    print(f"\nPattern (Count: {len(err_list)}): {norm[:120]}")
    print(f"First line: {first['line_num']} | Last line: {last['line_num']}")
    print("Sample header:", first["header"][:200])
    app_frames = [l.strip() for l in first["lines"] if "/app/" in l or "/database/" in l or "vendor/laravel" in l][:3]
    for frame in app_frames:
        print("  ->", frame[:150])
