"""
Pairing + test Samsung Smart TV (Tizen).

Cara pakai:
  pip install samsungtvws
  python samsung_pair.py 192.168.1.x

Saat pertama kali konek, TV akan tampilkan popup Allow/Deny — pilih Allow.
"""

import sys
import json
import os

BASE_DIR   = os.path.dirname(__file__)
TOKEN_FILE = os.path.join(BASE_DIR, "samsung_tokens.json")


def load_tokens() -> dict:
    if os.path.exists(TOKEN_FILE):
        with open(TOKEN_FILE) as f:
            return json.load(f)
    return {}


def save_tokens(tokens: dict):
    with open(TOKEN_FILE, "w") as f:
        json.dump(tokens, f, indent=2)


def pair(tv_ip: str):
    try:
        from samsungtvws import SamsungTVWS
    except ImportError:
        print("samsungtvws belum terinstall. Jalankan:")
        print("  pip install samsungtvws")
        sys.exit(1)

    tokens = load_tokens()
    token = tokens.get(tv_ip)

    print(f"\nMenghubungi Samsung TV {tv_ip}...")
    if token:
        print(f"Token tersimpan: {token}")
    else:
        print("Belum ada token — TV akan tampilkan popup Allow/Deny.")
        print("Pilih ALLOW di layar TV!\n")

    try:
        tv = SamsungTVWS(host=tv_ip, port=8002, token=token, timeout=10, name="rental_ps")
        tv.open()

        new_token = tv.token
        if new_token and new_token != token:
            tokens[tv_ip] = new_token
            save_tokens(tokens)
            print(f"Token disimpan: {new_token}")

        print("\n[OK] Konek berhasil!")
        print("Test kirim tombol power...")
        tv.send_key("KEY_POWER")
        tv.close()
        print("[OK] Perintah power terkirim!")
        print(f"\nSekarang isi 'Device Address' di dashboard dengan:")
        print(f"  samsung:{tv_ip}")

    except Exception as e:
        print(f"\n[ERR] Gagal: {e}")
        print("\nPastikan:")
        print("  1. TV menyala dan terhubung WiFi yang sama")
        print("  2. Settings → General → Network → Expert Settings → IP Remote → ON")
        print("  3. Port 8002 tidak diblok firewall")
        sys.exit(1)


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Cara pakai: python samsung_pair.py <IP_TV>")
        print("Contoh   : python samsung_pair.py 192.168.1.22")
        sys.exit(1)

    pair(sys.argv[1])
