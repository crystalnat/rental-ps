"""
Pairing satu kali untuk Hisense VIDAA TV.

Cara pakai:
  pip install hisensetv
  python vidaa_pair.py 192.168.1.21

Jalankan satu kali per TV. Setelah berhasil, adb_bridge.py
bisa kontrol TV tersebut secara otomatis.

SEBELUM JALAN:
  Di TV Hisense, aktifkan Remote App:
  Settings → Network → Remote Control (atau "Kontrol Jarak Jauh") → ON
"""

import os
import sys
import time

BASE_DIR  = os.path.dirname(__file__)
VIDAA_DIR = os.path.join(BASE_DIR, "vidaa_certs")


def pair(tv_ip: str):
    try:
        from hisensetv import HisenseTv
    except ImportError:
        print("hisensetv belum terinstall. Jalankan dulu:")
        print("  pip install hisensetv")
        sys.exit(1)

    os.makedirs(VIDAA_DIR, exist_ok=True)
    auth_file = os.path.join(VIDAA_DIR, f"{tv_ip}.json")

    if os.path.exists(auth_file):
        print(f"TV {tv_ip} sudah di-pair sebelumnya.")
        ulang = input("Pair ulang? (y/N): ").strip().lower()
        if ulang != "y":
            print("Dibatalkan.")
            return

    print(f"\nMenghubungi TV {tv_ip}...")
    print("Pastikan TV menyala dan Remote Control/App diaktifkan di Settings TV.\n")

    try:
        with HisenseTv(tv_ip) as tv:
            print("Terhubung! Memulai authorization...")
            tv.start_authorization()
            print("Lihat layar TV — seharusnya muncul kode angka.")
            code = input("Masukkan kode yang tampil di TV: ").strip()
            tv.send_authorization_code(code)
            time.sleep(1)

        # Simpan tanda sudah pair
        import json
        with open(auth_file, "w") as f:
            json.dump({"ip": tv_ip, "paired": True}, f)

        print(f"\n[OK] Pairing berhasil!")
        print(f"\nSekarang isi 'Device Address' di dashboard dengan:")
        print(f"  vidaa:{tv_ip}")

    except Exception as e:
        print(f"\n[ERR] Pairing gagal: {e}")
        print("\nPastikan:")
        print("  1. TV menyala dan di jaringan WiFi yang sama")
        print("  2. Settings TV → Network → Remote Control → ON")
        print("  3. Tidak ada firewall yang block port 36669")
        sys.exit(1)


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Cara pakai: python vidaa_pair.py <IP_TV>")
        print("Contoh   : python vidaa_pair.py 192.168.1.21")
        sys.exit(1)

    pair(sys.argv[1])
