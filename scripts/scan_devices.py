#!/usr/bin/env python3
"""
Scan jaringan lokal untuk menemukan perangkat Tuya.
Jalankan sekali saat pertama kali setup, atau saat IP perangkat berubah.

Cara pakai:
  pip install tinytuya
  python scan_devices.py

Output: devices.json (berisi IP, device_id, local_key, version)
"""

import json
import os
import tinytuya

DEVICES_FILE = os.path.join(os.path.dirname(__file__), "devices.json")

def scan():
    print("Scanning jaringan untuk perangkat Tuya (tunggu ~18 detik)...")
    print("Pastikan komputer ini terhubung ke WiFi yang sama dengan PS.\n")

    found = tinytuya.deviceScan(verbose=False, maxretry=18)

    if not found:
        print("Tidak ada perangkat ditemukan.")
        print("Pastikan perangkat menyala dan terhubung ke WiFi yang sama.")
        return

    # Baca local_key yang sudah ada (dari devices.json format array)
    existing_keys = {}
    if os.path.exists(DEVICES_FILE):
        try:
            existing = json.load(open(DEVICES_FILE))
            if isinstance(existing, list):
                for d in existing:
                    existing_keys[d["id"]] = d.get("key", "")
            elif isinstance(existing, dict):
                for dev_id, info in existing.items():
                    existing_keys[dev_id] = info.get("local_key", "")
        except Exception:
            pass

    result = []
    print(f"Ditemukan {len(found)} perangkat:\n")

    for dev_id, info in found.items():
        ip      = info.get("ip", "")
        version = str(info.get("version", "3.3"))
        name    = info.get("name", dev_id)
        key     = existing_keys.get(dev_id, "")

        print(f"  ID      : {dev_id}")
        print(f"  Nama    : {name}")
        print(f"  IP      : {ip}")
        print(f"  Version : {version}")
        print(f"  Key     : {key or '(belum ada)'}")
        print()

        result.append({
            "id":      dev_id,
            "name":    name,
            "key":     key,
            "ip":      ip,
            "version": version,
        })

    with open(DEVICES_FILE, "w") as f:
        json.dump(result, f, indent=2)

    print(f"Disimpan ke: {DEVICES_FILE}")
    print()
    print("PENTING: Isi 'key' di devices.json untuk setiap perangkat jika kosong.")
    print("Local key bisa didapat dari: tinytuya wizard  ATAU  Tuya IoT Console -> Device -> Local Key")

if __name__ == "__main__":
    scan()
