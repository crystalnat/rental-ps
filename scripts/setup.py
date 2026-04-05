#!/usr/bin/env python3
"""
Setup Tuya Local Bridge — edit variabel di bawah lalu jalankan sekali.

  pip install tinytuya requests
  python setup.py
"""

import hashlib
import hmac as _hmac
import json
import os
import time
import requests
import tinytuya

# ============================================================
#  EDIT VARIABEL INI
# ============================================================

CLIENT_ID     = "gr95nans9e9ruwdtuuvp"
CLIENT_SECRET = "79470deb5eea48fc863f9baa6386af8f"

# Region: pilih salah satu
# https://openapi.tuyacn.com      -> China
# https://openapi.tuyaus.com      -> US
# https://openapi.tuyaeu.com      -> Europe
# https://openapi-sg.iotbing.com  -> Singapore (Indonesia)
BASE_URL = "https://openapi-sg.iotbing.com"

SERVER_URL   = "http://10.32.168.209:8000"   # URL Laravel (ganti ke VPS saat deploy)
STORE_ID     = "1"
BRIDGE_TOKEN = "ganti-dengan-token-rahasia"  # Sama dengan TUYA_BRIDGE_TOKEN di .env

# ============================================================

BASE_DIR     = os.path.dirname(os.path.abspath(__file__))
DEVICES_FILE = os.path.join(BASE_DIR, "devices.json")
CONFIG_FILE  = os.path.join(BASE_DIR, "config.json")


def sign(t, token="", method="GET", path="", body=""):
    content_sha256 = hashlib.sha256(body.encode()).hexdigest()
    string_to_sign = f"{method}\n{content_sha256}\n\n{path}"
    str_to_sign    = CLIENT_ID + token + t + string_to_sign
    return _hmac.new(CLIENT_SECRET.encode(), str_to_sign.encode(), hashlib.sha256).hexdigest().upper()


def get_token():
    t    = str(int(time.time() * 1000))
    path = "/v1.0/token?grant_type=1"
    r = requests.get(BASE_URL + path, headers={
        "client_id": CLIENT_ID, "sign": sign(t, path=path),
        "t": t, "sign_method": "HMAC-SHA256",
    }, timeout=10)
    data = r.json()
    if not data.get("success"):
        raise Exception(f"Auth gagal: {data}")
    return data["result"]["access_token"]


def get_devices(token):
    t    = str(int(time.time() * 1000))
    path = "/v1.0/iot-01/associated-users/devices?size=50"
    r = requests.get(BASE_URL + path, headers={
        "client_id": CLIENT_ID, "sign": sign(t, token=token, path=path),
        "t": t, "access_token": token, "sign_method": "HMAC-SHA256",
    }, timeout=10)
    print(f"  [RAW endpoint 1] {r.status_code}: {r.text[:600]}")
    data = r.json()

    path2 = "/v1.0/devices?page_size=50"
    r2 = requests.get(BASE_URL + path2, headers={
        "client_id": CLIENT_ID, "sign": sign(t, token=token, path=path2),
        "t": t, "access_token": token, "sign_method": "HMAC-SHA256",
    }, timeout=10)
    print(f"  [RAW endpoint 2] {r2.status_code}: {r2.text[:600]}")
    data2 = r2.json()

    # Coba parse keduanya
    for d in [data, data2]:
        result = d.get("result")
        if isinstance(result, list) and result:
            return result
        if isinstance(result, dict):
            for key in ["list", "devices", "data", "device_list"]:
                lst = result.get(key)
                if isinstance(lst, list) and lst:
                    return lst
    return []


def run_wizard():
    """Jalankan tinytuya wizard otomatis dengan kredensial yang sudah diset."""
    import subprocess, sys
    print("  Menjalankan tinytuya wizard...")
    proc = subprocess.run(
        [sys.executable, "-m", "tinytuya", "wizard", CLIENT_ID, CLIENT_SECRET, "any", "3"],
        cwd=BASE_DIR,
        capture_output=True, text=True, timeout=60,
    )
    if proc.returncode == 0 or os.path.exists(os.path.join(BASE_DIR, "devices.json")):
        print("  Wizard selesai.")
    else:
        print(f"  [!] Wizard output: {proc.stdout[-300:]} {proc.stderr[-300:]}")


def scan_local(cloud_list):
    print("Scanning IP di jaringan lokal (18 detik)...")

    # Tulis format array yang diharapkan tinytuya
    tmp = [{"id": d["id"], "key": d["key"], "name": d.get("name", "")} for d in cloud_list]
    with open(DEVICES_FILE, "w") as f:
        json.dump(tmp, f)

    found_ips = {}
    try:
        result = tinytuya.deviceScan(verbose=False, maxretry=18)
        if isinstance(result, dict):
            for dev_id, info in result.items():
                ip = info.get("ip", "")
                if ip:
                    found_ips[dev_id] = ip
    except Exception as e:
        print(f"  [!] Auto-scan gagal ({e}), coba cara lain...")

    # Fallback: pakai snapshot.json kalau ada (hasil python -m tinytuya scan)
    snap = os.path.join(BASE_DIR, "snapshot.json")
    if os.path.exists(snap):
        try:
            with open(snap) as f:
                snap_data = json.load(f)
            for dev in snap_data.get("devices", []):
                dev_id = dev.get("id")
                ip     = dev.get("ip", "")
                if dev_id and ip:
                    found_ips[dev_id] = {
                        "ip":      ip,
                        "version": dev.get("ver", "3.5"),
                        "key":     dev.get("key", ""),
                    }
            print(f"  Pakai snapshot.json: {len(found_ips)} IP ditemukan")
        except Exception as e:
            print(f"  [!] Gagal baca snapshot: {e}")

    return found_ips


def main():
    print("=" * 50)
    print("  Tuya Bridge Setup")
    print("=" * 50)

    print("\n[1/4] Jalankan tinytuya wizard (ambil local_key)...")
    run_wizard()

    print("\n[2/4] Ambil token dari Tuya Cloud...")
    token = get_token()
    print("      OK")

    print("[3/4] Download daftar perangkat...")
    devices = get_devices(token)
    if not devices or not isinstance(devices[0], dict):
        print("      [ERR] Tidak ada perangkat ditemukan")
        return
    print(f"      Ditemukan {len(devices)} perangkat")

    print("[4/4] Scan IP lokal...")
    cloud_list = [{"id": d["id"], "key": d.get("local_key", "")} for d in devices]
    local_ips  = scan_local(cloud_list)

    final = []
    for d in devices:
        dev_id   = d["id"]
        snap_info = local_ips.get(dev_id, {})
        ip        = snap_info.get("ip", "") if isinstance(snap_info, dict) else snap_info
        version   = snap_info.get("version", "3.5") if isinstance(snap_info, dict) else "3.5"
        key       = snap_info.get("key") or d.get("local_key", "")
        final.append({
            "id":      dev_id,
            "name":    d.get("name", dev_id),
            "key":     key,
            "ip":      ip,
            "version": version,
        })
        print(f"  {d.get('name','?')} — IP: {ip or 'tidak ketemu'} | key: {'OK' if key else 'KOSONG'}")

    with open(DEVICES_FILE, "w") as f:
        json.dump(final, f, indent=2)

    with open(CONFIG_FILE, "w") as f:
        json.dump({
            "server_url":    SERVER_URL,
            "store_id":      STORE_ID,
            "token":         BRIDGE_TOKEN,
            "poll_interval": 2,
        }, f, indent=2)

    print(f"\nSelesai!")
    print(f"  devices.json -> {DEVICES_FILE}")
    print(f"  config.json  -> {CONFIG_FILE}")
    print(f"\nJalankan bridge: python tuya_bridge.py")


if __name__ == "__main__":
    main()
