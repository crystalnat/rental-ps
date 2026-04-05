#!/usr/bin/env python3
"""
Tuya Local Bridge — polling perintah dari server Laravel,
lalu eksekusi ke perangkat Tuya secara lokal (tanpa internet untuk Tuya).

Cara pakai:
  pip install tinytuya requests
  python tuya_bridge.py

Konfigurasi via file config.json di folder yang sama.
"""

import json
import os
import time
import requests
import tinytuya

BASE_DIR    = os.path.dirname(__file__)
CONFIG_FILE = os.path.join(BASE_DIR, "config.json")
DEVICES_FILE= os.path.join(BASE_DIR, "devices.json")

DEFAULT_CONFIG = {
    "server_url":  "https://your-server.com",   # URL server Laravel
    "store_id":    "1",                          # ID toko ini
    "token":       "ganti-dengan-token-rahasia", # Harus sama dengan TUYA_BRIDGE_TOKEN di .env
    "poll_interval": 2,                          # Detik antar polling
}


def load_config() -> dict:
    if not os.path.exists(CONFIG_FILE):
        with open(CONFIG_FILE, "w") as f:
            json.dump(DEFAULT_CONFIG, f, indent=2)
        print(f"Config dibuat: {CONFIG_FILE}")
        print("Edit config.json terlebih dahulu, lalu jalankan ulang.\n")
        exit(1)
    with open(CONFIG_FILE) as f:
        return json.load(f)


def load_devices() -> dict:
    if not os.path.exists(DEVICES_FILE):
        print(f"[ERR] {DEVICES_FILE} tidak ditemukan. Jalankan: python -m tinytuya scan")
        return {}
    with open(DEVICES_FILE) as f:
        data = json.load(f)
    # Support format array [{id, key, ip, version}]
    if isinstance(data, list):
        return {d["id"]: {"ip": d.get("ip",""), "local_key": d.get("key",""), "version": d.get("version","3.5"), "name": d.get("name","")} for d in data}
    return data


def execute(device_id: str, switch_on: bool, devices: dict) -> bool:
    info = devices.get(device_id)
    if not info:
        print(f"[ERR] Device '{device_id}' tidak ada di devices.json")
        return False

    ip        = info.get("ip", "")
    local_key = info.get("local_key", "")
    version   = float(info.get("version", "3.3"))

    if not ip or not local_key:
        print(f"[ERR] IP atau local_key kosong untuk '{device_id}'")
        return False

    try:
        d = tinytuya.OutletDevice(device_id, ip, local_key)
        d.set_version(version)
        d.set_socketTimeout(5)
        result = d.turn_on() if switch_on else d.turn_off()

        if isinstance(result, dict) and result.get("Error"):
            print(f"[ERR] {device_id}: {result}")
            return False

        print(f"[OK] {device_id} -> {'ON' if switch_on else 'OFF'}")
        return True
    except Exception as e:
        print(f"[ERR] {device_id}: {e}")
        return False


def poll(cfg: dict):
    url_pending = f"{cfg['server_url'].rstrip('/')}/api/tuya/commands"
    url_ack     = f"{cfg['server_url'].rstrip('/')}/api/tuya/commands/{{id}}/ack"
    params      = {"store_id": cfg["store_id"], "token": cfg["token"]}

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

    devices = load_devices()
    for cmd in commands:
        ok = execute(cmd["device_id"], cmd["switch"], devices)
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
    print(f"Tuya Bridge aktif — polling {cfg['server_url']} tiap {cfg['poll_interval']}s")
    print(f"Store ID : {cfg['store_id']}")
    print("Tekan Ctrl+C untuk berhenti.\n")

    while True:
        poll(cfg)
        time.sleep(cfg["poll_interval"])
