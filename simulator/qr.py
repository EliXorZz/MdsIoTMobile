"""Génération et vérification de tokens QR signés par HMAC-SHA256."""
import hashlib
import hmac
import json
import os
import time


def _secret() -> bytes:
    secret = os.getenv('QR_SECRET', '')
    if not secret:
        raise RuntimeError('QR_SECRET non défini')
    return secret.encode()


def generate_token(device_id: str) -> dict:
    """Retourne le payload JSON à encoder dans le QR code."""
    issued_at = int(time.time())
    message = f'{device_id}|{issued_at}'.encode()
    sig = hmac.new(_secret(), message, hashlib.sha256).hexdigest()
    return {'device_id': device_id, 'issued_at': issued_at, 'sig': sig}


def verify_token(payload: dict, max_age: int = 300) -> str:
    """Vérifie la signature et la fraîcheur du token. Retourne le device_id."""
    try:
        device_id = str(payload['device_id'])
        issued_at = int(payload['issued_at'])
        sig = str(payload['sig'])
    except (KeyError, TypeError, ValueError) as e:
        raise ValueError('Payload QR invalide') from e

    if time.time() - issued_at > max_age:
        raise ValueError('Token QR expiré')

    message = f'{device_id}|{issued_at}'.encode()
    expected = hmac.new(_secret(), message, hashlib.sha256).hexdigest()
    if not hmac.compare_digest(expected, sig):
        raise ValueError('Signature QR invalide')

    return device_id


def _print_qr(device_id: str, token: dict) -> None:
    try:
        import qrcode  # type: ignore
        qr = qrcode.QRCode(border=1)
        qr.add_data(json.dumps(token, separators=(',', ':')))
        qr.make(fit=True)
        qr.print_ascii(invert=True)
    except ImportError:
        print(json.dumps(token, separators=(',', ':')))


if __name__ == '__main__':
    import sys
    import re

    ids = sys.argv[1:]
    if not ids:
        print('Usage: python -m simulator.qr <device_id> [device_id ...]', file=sys.stderr)
        sys.exit(1)

    for did in ids:
        if not re.fullmatch(r'[A-Za-z0-9_-]{1,64}', did):
            print(f'device_id invalide : {did}', file=sys.stderr)
            sys.exit(1)
        token = generate_token(did)
        print(f'\n=== {did} ===')
        _print_qr(did, token)
        print(f'Payload : {json.dumps(token)}')
