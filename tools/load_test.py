"""
Load test : publie N messages de télémétrie aussi vite que possible,
puis surveille combien la DB en a ingéré pour voir si ça dépile.

Usage :
    python3 -m tools.load_test                  # 100k messages, 10 devices
    python3 -m tools.load_test --count 50000
    python3 -m tools.load_test --count 100000 --devices 20 --qos 0
"""
import argparse
import json
import os
import random
import sys
import threading
import time
import uuid

import paho.mqtt.client as mqtt


def make_message(device_id, room_id, boot_id, seq):
    return {
        "schema_version": 1,
        "message_id": f"{boot_id}-{seq}",
        "device_id": device_id,
        "room_id": room_id,
        "observed_at": "2026-01-01T00:00:00.000Z",
        "temperature": {"value": round(22 + random.uniform(-0.4, 0.4), 2), "unit": "°C"},
        "co2": {"value": random.randint(420, 1200), "unit": "ppm"},
    }


def publish_all(devices, total, qos, on_progress):
    sent = 0
    per_device = total // len(devices)

    clients = []
    for d in devices:
        c = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2, client_id=f"loadtest-{d['boot_id']}", clean_session=True)
        c.username_pw_set(
            os.getenv("MQTT_USER", "simulator"),
            os.getenv("MQTT_PASSWORD", "simulator-demo"),
        )
        c.connect(os.getenv("MQTT_HOST", "localhost"), int(os.getenv("MQTT_PORT", "1883")), keepalive=60)
        c.loop_start()
        clients.append(c)

    start = time.monotonic()
    for i in range(per_device):
        for j, (d, c) in enumerate(zip(devices, clients)):
            d["seq"] += 1
            msg = make_message(d["device_id"], d["room_id"], d["boot_id"], d["seq"])
            topic = f"campus/v1/devices/{d['device_id']}/telemetry"
            c.publish(topic, json.dumps(msg), qos=qos)
            sent += 1
        if sent % 1000 == 0:
            elapsed = time.monotonic() - start
            on_progress(sent, total, elapsed)

    # flush
    for c in clients:
        c.loop_stop()
        c.disconnect()

    elapsed = time.monotonic() - start
    on_progress(sent, total, elapsed)
    return sent, elapsed


def poll_db(table, stop_event, results):
    """Compte les lignes dans la table toutes les secondes via psql."""
    import subprocess

    db_url = os.getenv("DB_URL") or (
        f"postgresql://{os.getenv('DB_USERNAME','laravel')}:{os.getenv('DB_PASSWORD','password')}"
        f"@{os.getenv('DB_HOST','localhost')}:{os.getenv('DB_PORT','5432')}/{os.getenv('DB_DATABASE','laravel')}"
    )
    while not stop_event.is_set():
        try:
            out = subprocess.check_output(
                ["psql", db_url, "-t", "-c", f"SELECT COUNT(*) FROM {table}"],
                stderr=subprocess.DEVNULL,
                timeout=3,
            )
            results.append((time.monotonic(), int(out.strip())))
        except Exception:
            pass
        time.sleep(1)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--count", type=int, default=100_000, help="Nombre total de messages")
    parser.add_argument("--devices", type=int, default=10, help="Nombre de devices simulés")
    parser.add_argument("--qos", type=int, default=0, choices=[0, 1], help="QoS MQTT (0=fire-and-forget par défaut, 1=ack)")
    parser.add_argument("--table", default="telemetry", help="Table TimescaleDB à surveiller")
    args = parser.parse_args()

    devices = [
        {
            "device_id": f"load-{i:03d}",
            "room_id": f"salle-load-{i}",
            "boot_id": uuid.uuid4().hex,
            "seq": 0,
        }
        for i in range(args.devices)
    ]

    print(f"\nLoad test : {args.count:,} messages / {args.devices} devices / QoS {args.qos}")
    print(f"Topics   : campus/v1/devices/load-XXX/telemetry")
    print("-" * 60)

    db_results = []
    stop_db = threading.Event()
    db_thread = threading.Thread(target=poll_db, args=(args.table, stop_db, db_results), daemon=True)
    db_thread.start()

    def on_progress(sent, total, elapsed):
        rate = sent / elapsed if elapsed > 0 else 0
        bar_len = 30
        filled = int(bar_len * sent / total)
        bar = "█" * filled + "░" * (bar_len - filled)
        pct = sent / total * 100
        sys.stdout.write(f"\r  [{bar}] {pct:5.1f}%  {sent:>7,}/{total:,}  {rate:>7,.0f} msg/s  ")
        sys.stdout.flush()

    t0 = time.monotonic()
    sent, elapsed = publish_all(devices, args.count, args.qos, on_progress)
    stop_db.set()

    print(f"\n\nPublié  : {sent:,} messages en {elapsed:.1f}s ({sent/elapsed:,.0f} msg/s)")

    # Attendre encore 10s pour voir si la DB rattrape
    print("Attente ingestion DB (10s)...")
    deadline = time.monotonic() + 10
    while time.monotonic() < deadline:
        try:
            import subprocess
            db_url = os.getenv("DB_URL") or (
                f"postgresql://{os.getenv('DB_USERNAME','laravel')}:{os.getenv('DB_PASSWORD','password')}"
                f"@{os.getenv('DB_HOST','localhost')}:{os.getenv('DB_PORT','5432')}/{os.getenv('DB_DATABASE','laravel')}"
            )
            out = subprocess.check_output(
                ["psql", db_url, "-t", "-c", f"SELECT COUNT(*) FROM {args.table} WHERE device_id LIKE 'load-%'"],
                stderr=subprocess.DEVNULL, timeout=3,
            )
            db_count = int(out.strip())
            lag = sent - db_count
            print(f"  DB : {db_count:>7,} lignes  |  lag : {lag:>7,} messages")
        except Exception as e:
            print(f"  DB : inaccessible ({e})")
        time.sleep(2)

    print("\nRésumé ingestion :")
    if db_results:
        first_count = db_results[0][1]
        last_count = db_results[-1][1]
        duration = db_results[-1][0] - db_results[0][0]
        ingested = last_count - first_count
        rate = ingested / duration if duration > 0 else 0
        print(f"  Avant test : {first_count:,} lignes")
        print(f"  Après test : {last_count:,} lignes")
        print(f"  Ingéré     : {ingested:,} lignes en {duration:.1f}s ({rate:,.0f} lignes/s)")
        if ingested < sent * 0.95:
            print(f"  ⚠ Lag résiduel : ~{sent - ingested:,} messages non ingérés")
        else:
            print(f"  ✓ Tout ingéré")
    else:
        print("  (psql non disponible — vérifie manuellement)")


if __name__ == "__main__":
    main()
