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
import tinytuya.scanner

DEVICES_FILE = os.path.join(os.path.dirname(__file__), "devices.json")

def scan():
    print("Scanning jaringan untuk perangkat Tuya (tunggu ~18 detik)...")
    print("Pastikan komputer ini terhubung ke WiFi yang sama dengan PS.\n")

    # Broadcast UDP ke jaringan lokal tanpa butuh keylist
    scanner = tinytuya.scanner.TuyaScanner()
    scanner.scan(maxtime=18)
    devices = scanner.found

    if not devices:
        print("Tidak ada perangkat ditemukan.")
        print("Pastikan perangkat menyala dan terhubung ke WiFi yang sama.")
        return

    result = {}
    print(f"Ditemukan {len(devices)} perangkat:\n")

    for dev_id, info in devices.items():
        ip      = info.get("ip", "")
        version = info.get("version", "3.3")
        name    = info.get("name", dev_id)

        print(f"  ID      : {dev_id}")
        print(f"  Nama    : {name}")
        print(f"  IP      : {ip}")
        print(f"  Version : {version}")
        print()

        # local_key harus diisi manual dari Tuya IoT Console / tinytuya wizard
        existing_key = ""
        if os.path.exists(DEVICES_FILE):
            try:
                existing = json.load(open(DEVICES_FILE))
                existing_key = existing.get(dev_id, {}).get("local_key", "")
            except Exception:
                pass

        result[dev_id] = {
            "ip":        ip,
            "version":   str(version),
            "local_key": existing_key,
            "name":      name,
        }

    with open(DEVICES_FILE, "w") as f:
        json.dump(result, f, indent=2)

    print(f"Disimpan ke: {DEVICES_FILE}")
    print()
    print("PENTING: Isi 'local_key' di devices.json untuk setiap perangkat.")
    print("Local key bisa didapat dari: tinytuya wizard  ATAU  Tuya IoT Console -> Device -> Local Key")

if __name__ == "__main__":
    scan()
