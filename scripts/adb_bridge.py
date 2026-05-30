#!/usr/bin/env python3
"""
ADB Bridge — polling perintah dari server Laravel,
lalu eksekusi ke Android TV via ADB (tanpa Tuya).

Cara pakai:
  pip install requests
  python adb_bridge.py

Pastikan ADB sudah terinstall dan ada di PATH.
Konfigurasi via file config.json di folder yang sama.

Format device di devices.json:
  [
    { "id": "192.168.1.20:5555", "name": "PS-01" },
    { "id": "192.168.1.21:5555", "name": "PS-02" }
  ]

"id" di sini adalah ADB address (IP:PORT) yang juga diisi
di field "ADB Address" pada pengaturan unit PS di dashboard.
"""

import json
import os
import subprocess
import time
import requests

BASE_DIR    = os.path.dirname(__file__)
CONFIG_FILE = os.path.join(BASE_DIR, "config.json")

DEFAULT_CONFIG = {
    "server_url":    "http://10.32.168.209:8000",  # URL server Laravel
    "store_id":      "1",                           # ID toko ini
    "token":         "ganti-dengan-token-rahasia",  # Harus sama dengan TUYA_BRIDGE_TOKEN di .env
    "poll_interval": 2,                             # Detik antar polling
    "adb_path":      "adb",                         # Path ke executable adb (default: 'adb' dari PATH)
}

# Keyevent untuk power/sleep (sama dengan menekan tombol power di remote)
KEYEVENT_POWER = "26"


def load_config() -> dict:
    if not os.path.exists(CONFIG_FILE):
        with open(CONFIG_FILE, "w") as f:
            json.dump(DEFAULT_CONFIG, f, indent=2)
        print(f"Config dibuat: {CONFIG_FILE}")
        print("Edit config.json terlebih dahulu, lalu jalankan ulang.\n")
        exit(1)
    with open(CONFIG_FILE) as f:
        cfg = json.load(f)
    # Pastikan adb_path ada
    if "adb_path" not in cfg:
        cfg["adb_path"] = "adb"
    return cfg


def adb_run(adb_path: str, adb_address: str, args: list[str], timeout: int = 10) -> tuple[bool, str]:
    """Jalankan perintah adb, return (sukses, output)."""
    cmd = [adb_path, "-s", adb_address] + args
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=timeout)
        output = (result.stdout + result.stderr).strip()
        return result.returncode == 0, output
    except subprocess.TimeoutExpired:
        return False, "timeout"
    except FileNotFoundError:
        return False, f"ADB tidak ditemukan: {adb_path}"
    except Exception as e:
        return False, str(e)


def connect_device(adb_path: str, adb_address: str) -> bool:
    """Pastikan ADB terhubung ke perangkat."""
    ok, out = adb_run(adb_path, adb_address, [], timeout=1)
    # Coba connect dulu
    try:
        result = subprocess.run(
            [adb_path, "connect", adb_address],
            capture_output=True, text=True, timeout=8
        )
        output = (result.stdout + result.stderr).strip()
        if "connected" in output.lower() or "already connected" in output.lower():
            return True
        print(f"  [ADB] connect {adb_address}: {output}")
        return False
    except Exception as e:
        print(f"  [ADB] gagal connect {adb_address}: {e}")
        return False


def execute(adb_path: str, adb_address: str, switch_on: bool) -> bool:
    """
    switch_on=True  → nyalakan TV (keyevent POWER toggle — TV menyala jika sedang mati)
    switch_on=False → matikan TV / sleep (keyevent 26)
    Keduanya pakai keyevent 26 karena Android TV toggle power dengan tombol yang sama.
    """
    if not connect_device(adb_path, adb_address):
        print(f"[ERR] Tidak bisa konek ke {adb_address}")
        return False

    action = "ON (power toggle)" if switch_on else "OFF (sleep/power)"
    ok, out = adb_run(adb_path, adb_address, ["shell", "input", "keyevent", KEYEVENT_POWER])
    if ok:
        print(f"[OK] {adb_address} -> {action}")
    else:
        print(f"[ERR] {adb_address} -> {action}: {out}")
    return ok


def poll(cfg: dict):
    url_pending = f"{cfg['server_url'].rstrip('/')}/api/tuya/commands"
    url_ack     = f"{cfg['server_url'].rstrip('/')}/api/tuya/commands/{{id}}/ack"
    params      = {"store_id": cfg["store_id"], "token": cfg["token"]}
    adb_path    = cfg.get("adb_path", "adb")

    try:
        resp = requests.get(url_pending, params=params, timeout=5)
        if resp.status_code == 401:
            print("[ERR] Token salah. Cek config.json dan TUYA_BRIDGE_TOKEN di .env server.")
            return
        commands = resp.json()
    except Exception as e:
        print(f"[ERR] Tidak bisa reach server: {e}")
        return

    if not commands:
        return

    for cmd in commands:
        # device_id di DB berisi ADB address, contoh: "192.168.1.20:5555"
        adb_address = cmd["device_id"]
        ok = execute(adb_path, adb_address, cmd["switch"])
        try:
            requests.post(
                url_ack.format(id=cmd["id"]),
                params={"token": cfg["token"], "success": "1" if ok else "0"},
                timeout=5,
            )
        except Exception:
            pass


if __name__ == "__main__":
    cfg = load_config()
    print(f"ADB Bridge aktif — polling {cfg['server_url']} tiap {cfg['poll_interval']}s")
    print(f"Store ID  : {cfg['store_id']}")
    print(f"ADB path  : {cfg.get('adb_path', 'adb')}")
    print("Tekan Ctrl+C untuk berhenti.\n")

    while True:
        poll(cfg)
        time.sleep(cfg["poll_interval"])
