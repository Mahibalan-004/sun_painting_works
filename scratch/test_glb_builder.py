import json
import struct
import math

def pack_glb(json_obj, bin_bytes):
    json_bytes = json.dumps(json_obj, separators=(',', ':')).encode('utf-8')
    # Pad JSON to 4-byte boundary with spaces (0x20)
    json_pad = (4 - (len(json_bytes) % 4)) % 4
    json_bytes += b' ' * json_pad

    # Pad BIN to 4-byte boundary with nulls (0x00)
    bin_pad = (4 - (len(bin_bytes) % 4)) % 4
    bin_bytes += b'\x00' * bin_pad

    total_len = 12 + 8 + len(json_bytes) + 8 + len(bin_bytes)

    header = struct.pack('<4sII', b'glTF', 2, total_len)
    chunk0_hdr = struct.pack('<I4s', len(json_bytes), b'JSON')
    chunk1_hdr = struct.pack('<I4s', len(bin_bytes), b'BIN\0')

    return header + chunk0_hdr + json_bytes + chunk1_hdr + bin_bytes

print("GLB Packer ready")
