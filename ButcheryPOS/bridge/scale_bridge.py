#!/usr/bin/env python3
"""
ButcheryPOS Scale Bridge
Reads weight from RS232/USB scale and POSTs to PHP API.

Usage:
  python scale_bridge.py --port COM3 --protocol dibal
  python scale_bridge.py --port /dev/ttyUSB0 --protocol mettler_toledo
  python scale_bridge.py --demo  # Demo mode (random weights)
"""

import serial
import requests
import time
import sys
import json
import argparse
import random

PROTOCOLS = {
    'dibal': {
        'baud': 9600,
        'parity': 'N',
        'stopbits': 1,
        'bytesize': 8,
    },
    'mettler_toledo': {
        'baud': 9600,
        'parity': 'E',
        'stopbits': 1,
        'bytesize': 7,
    },
    'avery': {
        'baud': 4800,
        'parity': 'N',
        'stopbits': 1,
        'bytesize': 8,
    },
}


def parse_weight(raw_bytes, protocol):
    """Parse raw scale output to weight value."""
    try:
        text = raw_bytes.decode('ascii', errors='ignore').strip()
        # Extract numeric value from common formats:
        # Dibal: "ST,GS+   1.250 kg"
        # Mettler Toledo: "S S     1.250  kg"
        # Avery: "W: +1.250 kg"
        digits = ''.join(c for c in text if c.isdigit() or c == '.' or c == '-')
        return abs(float(digits))
    except (ValueError, UnicodeDecodeError):
        return 0.0


def run_serial_bridge(port, protocol, api_url, api_key, interval):
    """Run the serial port scale bridge."""
    cfg = PROTOCOLS.get(protocol, PROTOCOLS['dibal'])

    parity_map = {
        'N': serial.PARITY_NONE,
        'E': serial.PARITY_EVEN,
        'O': serial.PARITY_ODD,
    }

    try:
        ser = serial.Serial(
            port=port,
            baudrate=cfg['baud'],
            parity=parity_map.get(cfg['parity'], serial.PARITY_NONE),
            stopbits=cfg['stopbits'],
            bytesize=cfg['bytesize'],
            timeout=1,
        )
    except serial.SerialException as e:
        print(f"Error opening {port}: {e}", file=sys.stderr)
        sys.exit(1)

    print(f"Scale bridge started: {port} @ {cfg['baud']} baud, protocol={protocol}")
    print(f"Posting to: {api_url}")

    while True:
        try:
            raw = ser.readline()
            if raw:
                weight = parse_weight(raw, protocol)
                if weight > 0:
                    try:
                        resp = requests.post(api_url, json={
                            'weight_value': weight,
                            'device_code': f"{protocol}-{port}",
                            'raw_payload': raw.decode('ascii', errors='replace'),
                            'api_key': api_key,
                        }, timeout=5)
                        if resp.status_code == 200:
                            print(f"  -> {weight:.3f} kg [OK]")
                        else:
                            print(f"  -> {weight:.3f} kg [HTTP {resp.status_code}]")
                    except requests.RequestException as e:
                        print(f"  -> {weight:.3f} kg [ERROR: {e}]")
        except serial.SerialException:
            print("Serial error, reconnecting...", file=sys.stderr)
            time.sleep(2)
            try:
                ser.close()
                ser = serial.Serial(port=port, baudrate=cfg['baud'],
                                    parity=parity_map.get(cfg['parity'], serial.PARITY_NONE),
                                    stopbits=cfg['stopbits'], bytesize=cfg['bytesize'], timeout=1)
            except serial.SerialException:
                pass
        except KeyboardInterrupt:
            print("\nStopped.")
            break

        time.sleep(interval)


def run_demo_bridge(api_url, api_key, interval):
    """Run demo bridge with random weights."""
    print("Demo scale bridge started (random weights)")
    print(f"Posting to: {api_url}")

    while True:
        try:
            weight = round(random.uniform(0.5, 5.0), 3)
            try:
                resp = requests.post(api_url, json={
                    'weight_value': weight,
                    'device_code': 'demo-scale',
                    'raw_payload': f'DEMO: {weight:.3f} kg',
                    'api_key': api_key,
                }, timeout=5)
                if resp.status_code == 200:
                    print(f"  -> {weight:.3f} kg [OK]")
            except requests.RequestException as e:
                print(f"  -> {weight:.3f} kg [ERROR: {e}]")
        except KeyboardInterrupt:
            print("\nStopped.")
            break

        time.sleep(interval)


def main():
    parser = argparse.ArgumentParser(description='ButcheryPOS Scale Bridge')
    parser.add_argument('--port', default='COM3', help='Serial port (default: COM3)')
    parser.add_argument('--protocol', default='dibal',
                        choices=list(PROTOCOLS.keys()),
                        help='Scale protocol (default: dibal)')
    parser.add_argument('--api-url',
                        default='http://localhost/ButcheryPOS/public/api/scale',
                        help='Scale API endpoint URL')
    parser.add_argument('--api-key', default='CHANGE_ME_SCALE_KEY',
                        help='API key for authentication')
    parser.add_argument('--interval', type=float, default=1.5,
                        help='Polling interval in seconds (default: 1.5)')
    parser.add_argument('--demo', action='store_true',
                        help='Run in demo mode (random weights, no serial port)')
    args = parser.parse_args()

    if args.demo:
        run_demo_bridge(args.api_url, args.api_key, args.interval)
    else:
        run_serial_bridge(args.port, args.protocol, args.api_url, args.api_key, args.interval)


if __name__ == '__main__':
    main()