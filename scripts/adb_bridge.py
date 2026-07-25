#!/usr/bin/env python3
"""
Device Bridge — polling perintah dari server Laravel,
lalu eksekusi ke TV/device via ADB, VIDAA, Samsung, atau Tuya.

Cara pakai:
  pip install requests hisensetv samsungtvws tinytuya
  python adb_bridge.py

Format device_id di dashboard:
  - Android TV   : "192.168.1.20:5555"   (IP:PORT adb)
  - Hisense VIDAA: "vidaa:192.168.1.21"  (prefix vidaa:)
  - Samsung TV   : "samsung:192.168.1.22" (prefix samsung:)
  - Tuya plug    : "tuya:abc123deviceid"  (prefix tuya: + device ID)

Pairing:
  VIDAA  : python vidaa_pair.py 192.168.1.21
  Samsung: python samsung_pair.py 192.168.1.22
  Tuya   : python setup.py  (simpan ke devices.json)
"""

import json
import os
import subprocess
import threading
import time
import requests

BASE_DIR       = os.path.dirname(__file__)
CONFIG_FILE    = os.path.join(BASE_DIR, "config.json")
VIDAA_DIR      = os.path.join(BASE_DIR, "vidaa_certs")
SAMSUNG_TOKENS = os.path.join(BASE_DIR, "samsung_tokens.json")
DEVICES_FILE   = os.path.join(BASE_DIR, "devices.json")

DEFAULT_CONFIG = {
    "server_url":      "http://192.168.1.85:8000",
    "store_id":        "1",
    "token":           "ganti-dengan-token-rahasia",
    "poll_interval":   2,
    "adb_path":        "adb",
    "status_interval": 30,
}

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
    if "adb_path" not in cfg:
        cfg["adb_path"] = "adb"
    return cfg


# ─── ADB (Android TV) ────────────────────────────────────────────────────────

def adb_run(adb_path: str, adb_address: str, args: list, timeout: int = 10):
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


def adb_connect(adb_path: str, adb_address: str) -> bool:
    try:
        result = subprocess.run(
            [adb_path, "connect", adb_address],
            capture_output=True, text=True, timeout=8
        )
        output = (result.stdout + result.stderr).strip()
        if "connected" in output.lower() or "already connected" in output.lower():
            return True
        print(f"  [ADB] {adb_address}: {output}")
        return False
    except Exception as e:
        print(f"  [ADB] gagal connect {adb_address}: {e}")
        return False


def execute_adb(adb_path: str, adb_address: str, switch_on: bool) -> bool:
    if not adb_connect(adb_path, adb_address):
        print(f"[ERR] ADB tidak bisa konek ke {adb_address}")
        return False
    action = "ON" if switch_on else "OFF"
    ok, out = adb_run(adb_path, adb_address, ["shell", "input", "keyevent", KEYEVENT_POWER])
    if ok:
        print(f"[OK] ADB {adb_address} -> {action}")
    else:
        print(f"[ERR] ADB {adb_address} -> {action}: {out}")
    return ok


# ─── VIDAA (Hisense VIDAA OS) ─────────────────────────────────────────────────

def execute_vidaa(tv_ip: str, switch_on: bool) -> bool:
    try:
        from hisensetv import HisenseTv
    except ImportError:
        print("[ERR] hisensetv belum terinstall. Jalankan: pip install hisensetv")
        return False

    cert_file = os.path.join(VIDAA_DIR, f"{tv_ip}.pem")
    key_file  = os.path.join(VIDAA_DIR, f"{tv_ip}.key")

    if not os.path.exists(cert_file) or not os.path.exists(key_file):
        print(f"[ERR] VIDAA {tv_ip}: belum di-pair. Jalankan dulu: python vidaa_pair.py {tv_ip}")
        return False

    action = "ON" if switch_on else "OFF"
    try:
        with HisenseTv(tv_ip, certfile=cert_file, keyfile=key_file) as tv:
            tv.send_key("KEY_POWER")
        print(f"[OK] VIDAA {tv_ip} -> {action}")
        return True
    except Exception as e:
        print(f"[ERR] VIDAA {tv_ip} -> {action}: {e}")
        return False


# ─── Samsung TV ───────────────────────────────────────────────────────────────

def execute_samsung(tv_ip: str, switch_on: bool) -> bool:
    try:
        from samsungtvws import SamsungTVWS
    except ImportError:
        print("[ERR] samsungtvws belum terinstall. Jalankan: pip install samsungtvws")
        return False

    tokens = {}
    if os.path.exists(SAMSUNG_TOKENS):
        with open(SAMSUNG_TOKENS) as f:
            tokens = json.load(f)

    token = tokens.get(tv_ip)
    action = "ON" if switch_on else "OFF"
    try:
        tv = SamsungTVWS(host=tv_ip, port=8002, token=token, timeout=10, name="rental_ps")
        tv.open()
        new_token = tv.token
        if new_token and new_token != token:
            tokens[tv_ip] = new_token
            with open(SAMSUNG_TOKENS, "w") as f:
                json.dump(tokens, f, indent=2)
        tv.send_key("KEY_POWER")
        tv.close()
        print(f"[OK] Samsung {tv_ip} -> {action}")
        return True
    except Exception as e:
        print(f"[ERR] Samsung {tv_ip} -> {action}: {e}")
        return False


# ─── Tuya Cloud API ───────────────────────────────────────────────────────────

#TUYA_CLIENT_ID     = "gr95nans9e9ruwdtuuvp"
TUYA_CLIENT_ID     = "dyrqfet77kfhgm88jux7"
# TUYA_CLIENT_SECRET = "79470deb5eea48fc863f9baa6386af8f"
TUYA_CLIENT_SECRET = "9d0bdd948af341c0b0aee55af0dab480"
TUYA_BASE_URL      = "https://openapi-sg.iotbing.com"
_tuya_token_cache  = {"token": None, "expire": 0}


def _tuya_sign(t: str, token: str, method: str, path: str, body: str = "") -> str:
    import hashlib, hmac
    content_sha256 = hashlib.sha256(body.encode()).hexdigest()
    string_to_sign = f"{method}\n{content_sha256}\n\n{path}"
    str_to_sign    = TUYA_CLIENT_ID + token + t + string_to_sign
    return hmac.new(TUYA_CLIENT_SECRET.encode(), str_to_sign.encode(), hashlib.sha256).hexdigest().upper()


def _tuya_get_token() -> str:
    import time
    now = time.time()
    if _tuya_token_cache["token"] and now < _tuya_token_cache["expire"]:
        return _tuya_token_cache["token"]
    t    = str(int(now * 1000))
    path = "/v1.0/token?grant_type=1"
    resp = requests.get(TUYA_BASE_URL + path, headers={
        "client_id":   TUYA_CLIENT_ID,
        "sign":        _tuya_sign(t, "", "GET", path),
        "t":           t,
        "sign_method": "HMAC-SHA256",
    }, timeout=10)
    data = resp.json()
    if not data.get("success"):
        raise Exception(f"Tuya auth gagal: {data.get('msg')}")
    result = data["result"]
    _tuya_token_cache["token"]  = result["access_token"]
    _tuya_token_cache["expire"] = now + result.get("expire_time", 7200) - 60
    return _tuya_token_cache["token"]


def execute_tuya_local(device_id: str, switch_on: bool) -> bool:
    action = "ON" if switch_on else "OFF"
    try:
        import tinytuya
        devices = {}
        if os.path.exists(DEVICES_FILE):
            with open(DEVICES_FILE) as f:
                data = json.load(f)
            if isinstance(data, list):
                devices = {d["id"]: d for d in data}
        info = devices.get(device_id)
        if not info or not info.get("ip") or not info.get("key"):
            print(f"[ERR] Tuya local: device '{device_id}' tidak ada di devices.json atau IP/key kosong")
            return False
        import socket
        try:
            s = socket.create_connection((info["ip"], 6668), timeout=1)
            s.close()
        except OSError:
            print(f"[SKIP] Tuya {device_id} ({info['ip']}) tidak bisa dijangkau")
            return False
        d = tinytuya.OutletDevice(device_id, info["ip"], info["key"])
        d.set_version(float(info.get("version", "3.5")))
        d.set_socketTimeout(2)
        result = d.turn_on() if switch_on else d.turn_off()
        if isinstance(result, dict) and result.get("Error"):
            print(f"[ERR] Tuya local {device_id}: {result}")
            return False
        print(f"[OK] Tuya local {device_id} -> {action}")
        return True
    except Exception as e:
        print(f"[ERR] Tuya local {device_id}: {e}")
        return False


def execute_tuya(device_id: str, switch_on: bool) -> bool:
    action = "ON" if switch_on else "OFF"
    try:
        import time
        token = _tuya_get_token()
        t     = str(int(time.time() * 1000))
        path  = f"/v1.0/iot-01/associated-users/devices/{device_id}/actions"
        body  = json.dumps({"commands": [{"code": "switch_1", "value": switch_on}]})
        resp  = requests.post(TUYA_BASE_URL + path, headers={
            "client_id":    TUYA_CLIENT_ID,
            "access_token": token,
            "sign":         _tuya_sign(t, token, "POST", path, body),
            "t":            t,
            "sign_method":  "HMAC-SHA256",
            "Content-Type": "application/json",
        }, data=body, timeout=10)
        data = resp.json()
        if data.get("success"):
            print(f"[OK] Tuya cloud {device_id} -> {action}")
            return True
        # Fallback endpoint cloud
        path2 = f"/v1.0/devices/{device_id}/commands"
        body2 = json.dumps({"commands": [{"code": "switch_1", "value": switch_on}]})
        t2    = str(int(time.time() * 1000))
        resp2 = requests.post(TUYA_BASE_URL + path2, headers={
            "client_id":    TUYA_CLIENT_ID,
            "access_token": token,
            "sign":         _tuya_sign(t2, token, "POST", path2, body2),
            "t":            t2,
            "sign_method":  "HMAC-SHA256",
            "Content-Type": "application/json",
        }, data=body2, timeout=10)
        data2 = resp2.json()
        if data2.get("success"):
            print(f"[OK] Tuya cloud {device_id} -> {action}")
            return True
        print(f"[WARN] Tuya cloud gagal ({data2.get('msg', data2)}), coba local...")
        return execute_tuya_local(device_id, switch_on)
    except Exception as e:
        print(f"[WARN] Tuya cloud error ({e}), coba local...")
        return execute_tuya_local(device_id, switch_on)


# ─── Router utama ─────────────────────────────────────────────────────────────

def execute(cfg: dict, device_id: str, switch_on: bool) -> bool:
    """
    Deteksi tipe device dari format device_id:
      "samsung:192.168.1.x" → Samsung TV via WebSocket
      "vidaa:192.168.1.x"   → Hisense VIDAA via MQTT
      "tuya:abc123"         → Tuya smart plug via local API
      "192.168.1.x:5555"    → Android TV via ADB
    """
    if device_id.startswith("samsung:"):
        tv_ip = device_id[len("samsung:"):]
        return execute_samsung(tv_ip, switch_on)
    elif device_id.startswith("vidaa:"):
        tv_ip = device_id[len("vidaa:"):]
        return execute_vidaa(tv_ip, switch_on)
    elif device_id.startswith("tuya:"):
        tuya_id = device_id[len("tuya:"):]
        return execute_tuya(tuya_id, switch_on)
    else:
        return execute_adb(cfg.get("adb_path", "adb"), device_id, switch_on)


# ─── Status Sync (Tuya local → Server) ───────────────────────────────────────

def read_tuya_status_local() -> list:
    """
    Baca status ON/OFF semua device Tuya dari devices.json via lokal.
    Return: [{"device_id": "abc", "on": True/False}, ...]
    """
    try:
        import tinytuya
    except ImportError:
        return []

    devices = {}
    if os.path.exists(DEVICES_FILE):
        with open(DEVICES_FILE) as f:
            data = json.load(f)
        if isinstance(data, list):
            devices = {d["id"]: d for d in data}

    results = []
    for device_id, info in devices.items():
        ip  = info.get("ip", "")
        key = info.get("key", "")
        if not ip or not key:
            continue
        try:
            d = tinytuya.OutletDevice(device_id, ip, key)
            d.set_version(float(info.get("version", "3.5")))
            d.set_socketTimeout(3)
            status = d.status()
            on = bool(status.get("dps", {}).get("1", False)) if isinstance(status, dict) else False
            results.append({"device_id": device_id, "on": on})
        except Exception as e:
            print(f"[STATUS] gagal baca {device_id} ({ip}): {e}")
    return results


def read_tuya_status_cloud() -> list:
    """
    Baca status ON/OFF semua device Tuya dari Tuya Cloud API.
    Return: [{"device_id": "abc", "on": True/False}, ...]
    """
    devices = []
    if os.path.exists(DEVICES_FILE):
        with open(DEVICES_FILE) as f:
            data = json.load(f)
        if isinstance(data, list):
            devices = [d["id"] for d in data if d.get("id")]

    if not devices:
        return []

    results = []
    try:
        import time
        token = _tuya_get_token()
        for device_id in devices:
            try:
                t    = str(int(time.time() * 1000))
                path = f"/v1.0/devices/{device_id}/status"
                resp = requests.get(TUYA_BASE_URL + path, headers={
                    "client_id":    TUYA_CLIENT_ID,
                    "access_token": token,
                    "sign":         _tuya_sign(t, token, "GET", path),
                    "t":            t,
                    "sign_method":  "HMAC-SHA256",
                }, timeout=10)
                data = resp.json()
                if data.get("success"):
                    dps = {item["code"]: item["value"] for item in data.get("result", [])}
                    on  = bool(dps.get("switch_1", dps.get("switch", False)))
                    results.append({"device_id": device_id, "on": on})
                else:
                    print(f"[STATUS] cloud gagal {device_id}: {data.get('msg')}")
            except Exception as e:
                print(f"[STATUS] cloud error {device_id}: {e}")
    except Exception as e:
        print(f"[STATUS] cloud token error: {e}")
    return results


def push_status(cfg: dict, statuses: list):
    if not statuses:
        return
    url = f"{cfg['server_url'].rstrip('/')}/api/tuya/status"
    try:
        resp = requests.post(url, json=statuses, params={"token": cfg["token"]}, timeout=10)
        data = resp.json()
        print(f"[STATUS] push {len(statuses)} device(s) -> updated={data.get('updated', '?')}")
    except Exception as e:
        print(f"[STATUS] gagal push ke server: {e}")


# ─── Polling ──────────────────────────────────────────────────────────────────

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

    def _run(cmd):
        ok = execute(cfg, cmd["device_id"], cmd["switch"])
        try:
            requests.post(
                url_ack.format(id=cmd["id"]),
                params={"token": cfg["token"], "success": "1" if ok else "0"},
                timeout=5,
            )
        except Exception:
            pass
        # Langsung kabarin server status terbaru setelah command dieksekusi
        # Kalau gagal (device tidak bisa dijangkau), kirim on=False ke kasir
        raw_id  = cmd["device_id"]
        tuya_id = raw_id[len("tuya:"):] if raw_id.startswith("tuya:") else raw_id
        push_status(cfg, [{"device_id": tuya_id, "on": bool(cmd["switch"]) if ok else False}])

    threads = [threading.Thread(target=_run, args=(cmd,), daemon=True) for cmd in commands]
    for t in threads:
        t.start()
    for t in threads:
        t.join(timeout=8)


def _status_worker(cfg: dict):
    """Thread terpisah untuk sync status — tidak blokir polling command."""
    import threading
    status_interval = cfg.get("status_interval", 30)
    while True:
        time.sleep(status_interval)
        try:
            statuses = read_tuya_status_cloud()
            if not statuses:
                print("[STATUS] cloud kosong, fallback ke local...")
                statuses = read_tuya_status_local()
            push_status(cfg, statuses)
        except Exception as e:
            print(f"[STATUS] error: {e}")


if __name__ == "__main__":
    import threading
    os.makedirs(VIDAA_DIR, exist_ok=True)
    cfg = load_config()
    print(f"Device Bridge aktif — polling {cfg['server_url']} tiap {cfg['poll_interval']}s")
    print(f"Store ID  : {cfg['store_id']}")
    print(f"ADB path  : {cfg.get('adb_path', 'adb')}")
    print("Dukungan  : Android TV (ADB) + Hisense VIDAA + Samsung TV + Tuya Plug")
    print("Tekan Ctrl+C untuk berhenti.\n")

    t = threading.Thread(target=_status_worker, args=(cfg,), daemon=True)
    t.start()

    while True:
        poll(cfg)
        time.sleep(cfg["poll_interval"])
