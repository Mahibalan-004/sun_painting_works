import json
import struct
import math
import os

def create_glb_from_primitives(meshes_data):
    """
    meshes_data: list of dicts:
      {
        "name": str,
        "material": {
            "name": str,
            "baseColorFactor": [r, g, b, a],
            "metallicFactor": float,
            "roughnessFactor": float,
            "emissiveFactor": [r, g, b] (optional)
        },
        "vertices": list of [x, y, z],
        "normals": list of [nx, ny, nz],
        "uvs": list of [u, v],
        "indices": list of int
      }
    """
    bin_buffer = bytearray()
    buffer_views = []
    accessors = []
    materials = []
    meshes = []
    nodes = []

    for idx, m in enumerate(meshes_data):
        verts = m["vertices"]
        norms = m["normals"]
        uvs = m["uvs"]
        indices = m["indices"]
        mat_def = m["material"]

        # 1. Material
        pbr = {
            "baseColorFactor": mat_def.get("baseColorFactor", [0.8, 0.8, 0.8, 1.0]),
            "metallicFactor": mat_def.get("metallicFactor", 0.1),
            "roughnessFactor": mat_def.get("roughnessFactor", 0.5)
        }
        mat_obj = {
            "name": mat_def.get("name", f"Material_{idx}"),
            "pbrMetallicRoughness": pbr,
            "doubleSided": True
        }
        if "emissiveFactor" in mat_def:
            mat_obj["emissiveFactor"] = mat_def["emissiveFactor"]
        materials.append(mat_obj)

        # 2. Position BufferView & Accessor
        pos_offset = len(bin_buffer)
        min_pos = [float('inf')]*3
        max_pos = [float('-inf')]*3
        for v in verts:
            bin_buffer.extend(struct.pack('<3f', v[0], v[1], v[2]))
            for i in range(3):
                min_pos[i] = min(min_pos[i], v[i])
                max_pos[i] = max(max_pos[i], v[i])
        pos_len = len(bin_buffer) - pos_offset
        # Pad to 4 bytes
        pad = (4 - (len(bin_buffer) % 4)) % 4
        bin_buffer.extend(b'\x00' * pad)

        buffer_views.append({
            "buffer": 0,
            "byteOffset": pos_offset,
            "byteLength": pos_len,
            "target": 34962 # ARRAY_BUFFER
        })
        pos_acc_idx = len(accessors)
        accessors.append({
            "bufferView": len(buffer_views) - 1,
            "byteOffset": 0,
            "componentType": 5126, # FLOAT
            "count": len(verts),
            "type": "VEC3",
            "min": min_pos,
            "max": max_pos
        })

        # 3. Normal BufferView & Accessor
        norm_offset = len(bin_buffer)
        for n in norms:
            bin_buffer.extend(struct.pack('<3f', n[0], n[1], n[2]))
        norm_len = len(bin_buffer) - norm_offset
        pad = (4 - (len(bin_buffer) % 4)) % 4
        bin_buffer.extend(b'\x00' * pad)

        buffer_views.append({
            "buffer": 0,
            "byteOffset": norm_offset,
            "byteLength": norm_len,
            "target": 34962
        })
        norm_acc_idx = len(accessors)
        accessors.append({
            "bufferView": len(buffer_views) - 1,
            "byteOffset": 0,
            "componentType": 5126, # FLOAT
            "count": len(norms),
            "type": "VEC3"
        })

        # 4. UV BufferView & Accessor
        uv_offset = len(bin_buffer)
        for u in uvs:
            bin_buffer.extend(struct.pack('<2f', u[0], u[1]))
        uv_len = len(bin_buffer) - uv_offset
        pad = (4 - (len(bin_buffer) % 4)) % 4
        bin_buffer.extend(b'\x00' * pad)

        buffer_views.append({
            "buffer": 0,
            "byteOffset": uv_offset,
            "byteLength": uv_len,
            "target": 34962
        })
        uv_acc_idx = len(accessors)
        accessors.append({
            "bufferView": len(buffer_views) - 1,
            "byteOffset": 0,
            "componentType": 5126, # FLOAT
            "count": len(uvs),
            "type": "VEC2"
        })

        # 5. Indices BufferView & Accessor (UNSIGNED_INT or UNSIGNED_SHORT)
        idx_offset = len(bin_buffer)
        use_uint = len(verts) > 65535
        for index in indices:
            if use_uint:
                bin_buffer.extend(struct.pack('<I', index))
            else:
                bin_buffer.extend(struct.pack('<H', index))
        idx_len = len(bin_buffer) - idx_offset
        pad = (4 - (len(bin_buffer) % 4)) % 4
        bin_buffer.extend(b'\x00' * pad)

        buffer_views.append({
            "buffer": 0,
            "byteOffset": idx_offset,
            "byteLength": idx_len,
            "target": 34963 # ELEMENT_ARRAY_BUFFER
        })
        idx_acc_idx = len(accessors)
        accessors.append({
            "bufferView": len(buffer_views) - 1,
            "byteOffset": 0,
            "componentType": 5125 if use_uint else 5123, # UNSIGNED_INT / UNSIGNED_SHORT
            "count": len(indices),
            "type": "SCALAR"
        })

        # Mesh Primitive
        mesh_idx = len(meshes)
        meshes.append({
            "name": m.get("name", f"Mesh_{idx}"),
            "primitives": [{
                "attributes": {
                    "POSITION": pos_acc_idx,
                    "NORMAL": norm_acc_idx,
                    "TEXCOORD_0": uv_acc_idx
                },
                "indices": idx_acc_idx,
                "material": idx
            }]
        })

        nodes.append({
            "name": m.get("name", f"Node_{idx}"),
            "mesh": mesh_idx
        })

    gltf_json = {
        "asset": {
            "version": "2.0",
            "generator": "Sun Painting Works 3D Model Engine"
        },
        "scene": 0,
        "scenes": [{
            "name": "DefaultScene",
            "nodes": list(range(len(nodes)))
        }],
        "nodes": nodes,
        "meshes": meshes,
        "materials": materials,
        "accessors": accessors,
        "bufferViews": buffer_views,
        "buffers": [{
            "byteLength": len(bin_buffer)
        }]
    }

    # Pack into binary GLB
    json_bytes = json.dumps(gltf_json, separators=(',', ':')).encode('utf-8')
    json_pad = (4 - (len(json_bytes) % 4)) % 4
    json_bytes += b' ' * json_pad

    bin_pad = (4 - (len(bin_buffer) % 4)) % 4
    bin_buffer.extend(b'\x00' * bin_pad)

    total_len = 12 + 8 + len(json_bytes) + 8 + len(bin_buffer)
    header = struct.pack('<4sII', b'glTF', 2, total_len)
    chunk0_hdr = struct.pack('<I4s', len(json_bytes), b'JSON')
    chunk1_hdr = struct.pack('<I4s', len(bin_buffer), b'BIN\0')

    return header + chunk0_hdr + json_bytes + chunk1_hdr + bytes(bin_buffer)


# --- Geometry Generation Helpers ---
class MeshBuilder:
    def __init__(self, name, material):
        self.name = name
        self.material = material
        self.vertices = []
        self.normals = []
        self.uvs = []
        self.indices = []

    def add_box(self, cx, cy, cz, sx, sy, sz):
        hx, hy, hz = sx/2.0, sy/2.0, sz/2.0
        base_idx = len(self.vertices)
        # 6 faces * 4 vertices = 24 vertices
        faces = [
            # +X
            ([cx+hx, cy-hy, cz-hz], [cx+hx, cy+hy, cz-hz], [cx+hx, cy+hy, cz+hz], [cx+hx, cy-hy, cz+hz], [1,0,0]),
            # -X
            ([cx-hx, cy-hy, cz+hz], [cx-hx, cy+hy, cz+hz], [cx-hx, cy+hy, cz-hz], [cx-hx, cy-hy, cz-hz], [-1,0,0]),
            # +Y
            ([cx-hx, cy+hy, cz-hz], [cx-hx, cy+hy, cz+hz], [cx+hx, cy+hy, cz+hz], [cx+hx, cy+hy, cz-hz], [0,1,0]),
            # -Y
            ([cx-hx, cy-hy, cz+hz], [cx-hx, cy-hy, cz-hz], [cx+hx, cy-hy, cz-hz], [cx+hx, cy-hy, cz+hz], [0,-1,0]),
            # +Z
            ([cx-hx, cy-hy, cz+hz], [cx+hx, cy-hy, cz+hz], [cx+hx, cy+hy, cz+hz], [cx-hx, cy+hy, cz+hz], [0,0,1]),
            # -Z
            ([cx+hx, cy-hy, cz-hz], [cx-hx, cy-hy, cz-hz], [cx-hx, cy+hy, cz-hz], [cx+hx, cy+hy, cz-hz], [0,0,-1]),
        ]
        for f in faces:
            v0, v1, v2, v3, norm = f
            idx = len(self.vertices)
            self.vertices.extend([v0, v1, v2, v3])
            self.normals.extend([norm, norm, norm, norm])
            self.uvs.extend([[0,0], [1,0], [1,1], [0,1]])
            self.indices.extend([idx, idx+1, idx+2, idx, idx+2, idx+3])

    def add_cylinder(self, cx, cy, cz, r, h, axis='y', segs=16):
        # Cylinder along x, y or z
        base_idx = len(self.vertices)
        hh = h / 2.0
        ring1 = []
        ring2 = []
        for i in range(segs):
            theta = 2.0 * math.pi * i / segs
            c = math.cos(theta) * r
            s = math.sin(theta) * r
            if axis == 'y':
                p1 = [cx + c, cy - hh, cz + s]
                p2 = [cx + c, cy + hh, cz + s]
                norm = [math.cos(theta), 0, math.sin(theta)]
            elif axis == 'x':
                p1 = [cx - hh, cy + c, cz + s]
                p2 = [cx + hh, cy + c, cz + s]
                norm = [0, math.cos(theta), math.sin(theta)]
            else: # z
                p1 = [cx + c, cy + s, cz - hh]
                p2 = [cx + c, cy + s, cz + hh]
                norm = [math.cos(theta), math.sin(theta), 0]
            
            # Side vertices
            idx = len(self.vertices)
            self.vertices.extend([p1, p2])
            self.normals.extend([norm, norm])
            u = float(i) / segs
            self.uvs.extend([[u, 0], [u, 1]])

        for i in range(segs):
            next_i = (i + 1) % segs
            v0 = base_idx + i * 2
            v1 = base_idx + i * 2 + 1
            v2 = base_idx + next_i * 2 + 1
            v3 = base_idx + next_i * 2
            self.indices.extend([v0, v1, v2, v0, v2, v3])

        # Caps
        for cap_dir in [-1, 1]:
            cap_norm = [0, cap_dir, 0] if axis == 'y' else ([cap_dir, 0, 0] if axis == 'x' else [0, 0, cap_dir])
            center_v = [cx, cy + cap_dir*hh, cz] if axis == 'y' else ([cx + cap_dir*hh, cy, cz] if axis == 'x' else [cx, cy, cz + cap_dir*hh])
            c_idx = len(self.vertices)
            self.vertices.append(center_v)
            self.normals.append(cap_norm)
            self.uvs.append([0.5, 0.5])

            cap_start = len(self.vertices)
            for i in range(segs):
                theta = 2.0 * math.pi * i / segs
                c = math.cos(theta) * r
                s = math.sin(theta) * r
                if axis == 'y':
                    p = [cx + c, cy + cap_dir*hh, cz + s]
                elif axis == 'x':
                    p = [cx + cap_dir*hh, cy + c, cz + s]
                else:
                    p = [cx + c, cy + s, cz + cap_dir*hh]
                self.vertices.append(p)
                self.normals.append(cap_norm)
                self.uvs.append([0.5 + 0.5*math.cos(theta), 0.5 + 0.5*math.sin(theta)])

            for i in range(segs):
                next_i = (i + 1) % segs
                if cap_dir == 1:
                    self.indices.extend([c_idx, cap_start + i, cap_start + next_i])
                else:
                    self.indices.extend([c_idx, cap_start + next_i, cap_start + i])

    def to_dict(self):
        return {
            "name": self.name,
            "material": self.material,
            "vertices": self.vertices,
            "normals": self.normals,
            "uvs": self.uvs,
            "indices": self.indices
        }

# ============================================================
# 1. BUILD REALISTIC PASSENGER CAR 3D GLB
# ============================================================
def generate_car_glb():
    # Materials
    mat_body_paint = {
        "name": "SunMetallicPaint",
        "baseColorFactor": [0.08, 0.18, 0.38, 1.0], # Deep Luxury Sapphire Blue Metallic
        "metallicFactor": 0.95,
        "roughnessFactor": 0.12
    }
    mat_gold_trim = {
        "name": "SunGoldTrim",
        "baseColorFactor": [0.85, 0.70, 0.22, 1.0], # Official Sun Gold (#D4AF37)
        "metallicFactor": 0.90,
        "roughnessFactor": 0.15
    }
    mat_chrome = {
        "name": "ChromeSilver",
        "baseColorFactor": [0.95, 0.97, 1.0, 1.0],
        "metallicFactor": 0.98,
        "roughnessFactor": 0.05
    }
    mat_glass = {
        "name": "TintedGlass",
        "baseColorFactor": [0.12, 0.16, 0.24, 0.85],
        "metallicFactor": 0.2,
        "roughnessFactor": 0.05
    }
    mat_rubber = {
        "name": "TireRubber",
        "baseColorFactor": [0.08, 0.09, 0.11, 1.0],
        "metallicFactor": 0.05,
        "roughnessFactor": 0.85
    }
    mat_headlight = {
        "name": "LEDHeadlights",
        "baseColorFactor": [1.0, 0.98, 0.90, 1.0],
        "emissiveFactor": [0.95, 0.90, 0.75],
        "metallicFactor": 0.1,
        "roughnessFactor": 0.1
    }
    mat_taillight = {
        "name": "LEDTaillights",
        "baseColorFactor": [0.9, 0.05, 0.05, 1.0],
        "emissiveFactor": [0.9, 0.05, 0.05],
        "metallicFactor": 0.1,
        "roughnessFactor": 0.1
    }

    b_body = MeshBuilder("Car_Body_Chassis", mat_body_paint)
    # Lower chassis
    b_body.add_box(0, 0.55, 0, 4.4, 0.45, 1.95)
    # Hood & front fender
    b_body.add_box(1.3, 0.75, 0, 1.5, 0.28, 1.90)
    # Rear trunk & decklid
    b_body.add_box(-1.4, 0.80, 0, 1.3, 0.30, 1.88)
    # Passenger Cabin main
    b_body.add_box(-0.15, 1.15, 0, 2.1, 0.58, 1.68)
    # Rear aerodynamic spoiler
    b_body.add_box(-2.0, 0.98, 0, 0.25, 0.05, 1.70)
    # Side aero skirts
    b_body.add_box(0, 0.32, 0.96, 2.6, 0.14, 0.08)
    b_body.add_box(0, 0.32, -0.96, 2.6, 0.14, 0.08)
    # Side mirrors
    b_body.add_box(0.7, 1.05, 0.98, 0.22, 0.12, 0.22)
    b_body.add_box(0.7, 1.05, -0.98, 0.22, 0.12, 0.22)

    # Gold Trim & Accents
    b_gold = MeshBuilder("Car_Gold_Accents", mat_gold_trim)
    # Center dual racing sports stripe
    b_gold.add_box(0, 0.90, 0.08, 4.42, 0.02, 0.10)
    b_gold.add_box(0, 0.90, -0.08, 4.42, 0.02, 0.10)
    b_gold.add_box(-0.15, 1.45, 0.08, 2.12, 0.02, 0.10)
    b_gold.add_box(-0.15, 1.45, -0.08, 2.12, 0.02, 0.10)
    # Front grille surround & emblem
    b_gold.add_box(2.21, 0.55, 0, 0.04, 0.28, 1.25)
    b_gold.add_box(2.22, 0.62, 0, 0.06, 0.12, 0.18)
    # Rear diffuser gold accents
    b_gold.add_box(-2.21, 0.35, 0, 0.04, 0.12, 1.4)
    # Window border trim
    b_gold.add_box(-0.15, 1.38, 0.85, 2.05, 0.03, 0.03)
    b_gold.add_box(-0.15, 1.38, -0.85, 2.05, 0.03, 0.03)

    # Chrome Elements
    b_chrome = MeshBuilder("Car_Chrome_Trim", mat_chrome)
    # Exhaust pipes
    b_chrome.add_cylinder(-2.22, 0.28, 0.5, 0.07, 0.18, axis='x', segs=12)
    b_chrome.add_cylinder(-2.22, 0.28, -0.5, 0.07, 0.18, axis='x', segs=12)
    # Door handles
    b_chrome.add_box(0.3, 0.82, 0.98, 0.18, 0.04, 0.03)
    b_chrome.add_box(-0.4, 0.82, 0.98, 0.18, 0.04, 0.03)
    b_chrome.add_box(0.3, 0.82, -0.98, 0.18, 0.04, 0.03)
    b_chrome.add_box(-0.4, 0.82, -0.98, 0.18, 0.04, 0.03)

    # Glass
    b_glass = MeshBuilder("Car_Glass_Windows", mat_glass)
    # Front windshield (slanted box)
    b_glass.add_box(0.85, 1.15, 0, 0.65, 0.50, 1.62)
    # Rear window
    b_glass.add_box(-1.15, 1.18, 0, 0.55, 0.48, 1.60)
    # Side left & right windows
    b_glass.add_box(-0.15, 1.16, 0.84, 1.70, 0.42, 0.04)
    b_glass.add_box(-0.15, 1.16, -0.84, 1.70, 0.42, 0.04)

    # LED Lights
    b_headlights = MeshBuilder("Car_Headlights", mat_headlight)
    b_headlights.add_box(2.18, 0.68, 0.65, 0.08, 0.14, 0.42)
    b_headlights.add_box(2.18, 0.68, -0.65, 0.08, 0.14, 0.42)

    b_taillights = MeshBuilder("Car_Taillights", mat_taillight)
    b_taillights.add_box(-2.18, 0.75, 0.65, 0.06, 0.12, 0.45)
    b_taillights.add_box(-2.18, 0.75, -0.65, 0.06, 0.12, 0.45)
    b_taillights.add_box(-2.18, 0.75, 0, 0.04, 0.04, 0.80) # Center strip

    # Wheels & Rims
    b_rubber = MeshBuilder("Car_Tires", mat_rubber)
    b_rims = MeshBuilder("Car_Alloy_Rims", mat_gold_trim)
    wheel_positions = [
        (1.40, 0.36, 0.98),
        (1.40, 0.36, -0.98),
        (-1.40, 0.36, 0.98),
        (-1.40, 0.36, -0.98),
    ]
    for wx, wy, wz in wheel_positions:
        # Tire
        b_rubber.add_cylinder(wx, wy, wz, 0.36, 0.28, axis='z', segs=20)
        # Deep dish rim
        b_rims.add_cylinder(wx, wy, wz, 0.26, 0.29, axis='z', segs=16)
        # Hub cap
        b_chrome.add_cylinder(wx, wy, wz, 0.08, 0.30, axis='z', segs=12)

    meshes_list = [
        b_body.to_dict(),
        b_gold.to_dict(),
        b_chrome.to_dict(),
        b_glass.to_dict(),
        b_headlights.to_dict(),
        b_taillights.to_dict(),
        b_rubber.to_dict(),
        b_rims.to_dict()
    ]
    return create_glb_from_primitives(meshes_list)


# ============================================================
# 2. BUILD REALISTIC AUTOMOTIVE PAINTER 3D GLB
# ============================================================
def generate_painter_glb():
    # Materials for Painter
    mat_suit = {
        "name": "PainterSuit",
        "baseColorFactor": [0.90, 0.92, 0.95, 1.0], # White/Silver Cleanroom Coverall Suit
        "metallicFactor": 0.15,
        "roughnessFactor": 0.65
    }
    mat_suit_accent = {
        "name": "PainterSuitAccents",
        "baseColorFactor": [0.18, 0.22, 0.28, 1.0], # Charcoal / Navy Protective Knees/Elbows
        "metallicFactor": 0.10,
        "roughnessFactor": 0.75
    }
    mat_gold_trim = {
        "name": "PainterSunGold",
        "baseColorFactor": [0.85, 0.70, 0.22, 1.0], # Sun Gold Accents
        "metallicFactor": 0.85,
        "roughnessFactor": 0.20
    }
    mat_respirator = {
        "name": "RespiratorMask",
        "baseColorFactor": [0.22, 0.25, 0.30, 1.0], # Industrial Grey Mask Body
        "metallicFactor": 0.30,
        "roughnessFactor": 0.40
    }
    mat_filters = {
        "name": "RespiratorFilters",
        "baseColorFactor": [0.95, 0.65, 0.15, 1.0], # High-vis OSHA Dual Organic Vapor Filters
        "metallicFactor": 0.20,
        "roughnessFactor": 0.50
    }
    mat_visor = {
        "name": "SafetyVisor",
        "baseColorFactor": [0.25, 0.65, 0.85, 0.90], # Anti-glare reflective face shield
        "metallicFactor": 0.40,
        "roughnessFactor": 0.10
    }
    mat_gloves = {
        "name": "NitrileGloves",
        "baseColorFactor": [0.10, 0.45, 0.80, 1.0], # Professional Royal Blue Nitrile
        "metallicFactor": 0.10,
        "roughnessFactor": 0.50
    }
    mat_boots = {
        "name": "SafetyBoots",
        "baseColorFactor": [0.12, 0.13, 0.15, 1.0], # Deep Charcoal Workshop Work Boots
        "metallicFactor": 0.20,
        "roughnessFactor": 0.80
    }
    mat_spraygun = {
        "name": "HVLP_SprayGun",
        "baseColorFactor": [0.95, 0.96, 0.98, 1.0], # Chrome Gun Body
        "metallicFactor": 0.98,
        "roughnessFactor": 0.08
    }
    mat_cup = {
        "name": "PaintCup_Reservoir",
        "baseColorFactor": [0.85, 0.72, 0.25, 0.92], # Translucent Gold Paint Reservoir Cup
        "metallicFactor": 0.35,
        "roughnessFactor": 0.25
    }

    # Painter coordinates: Human stands beside car, height approx 1.75m
    # Torso centered at (0, 1.15, 0)
    b_suit = MeshBuilder("Painter_Coverall_Suit", mat_suit)
    b_accent = MeshBuilder("Painter_Suit_Accents", mat_suit_accent)
    b_gold = MeshBuilder("Painter_Gold_Accents", mat_gold_trim)
    b_head = MeshBuilder("Painter_Hood_Head", mat_suit)
    b_mask = MeshBuilder("Painter_Respirator", mat_respirator)
    b_visor = MeshBuilder("Painter_Visor", mat_visor)
    b_gloves = MeshBuilder("Painter_Gloves", mat_gloves)
    b_boots = MeshBuilder("Painter_Boots", mat_boots)
    b_gun = MeshBuilder("Painter_HVLP_Gun", mat_spraygun)
    b_cup = MeshBuilder("Painter_Paint_Cup", mat_cup)

    # 1. Head with protective hood
    b_head.add_box(0, 1.62, 0, 0.22, 0.26, 0.24)
    # Neck
    b_head.add_cylinder(0, 1.46, 0, 0.09, 0.10, axis='y', segs=12)

    # 2. Respirator mask on face (+Z or facing toward car at +X/-Z)
    # Assuming painter faces toward -Z or toward car (we can position spray gun pointing forward)
    # Painter facing forward along -X (toward car):
    # Front of face is at x = -0.12
    b_visor.add_box(-0.11, 1.66, 0, 0.05, 0.10, 0.18) # Safety Goggles / Visor
    b_mask.add_box(-0.12, 1.57, 0, 0.10, 0.12, 0.14) # Half-face mask
    # Dual filter cartridges on left and right of respirator
    b_mask.add_cylinder(-0.12, 1.57, 0.11, 0.05, 0.06, axis='z', segs=12)
    b_mask.add_cylinder(-0.12, 1.57, -0.11, 0.05, 0.06, axis='z', segs=12)
    # Filter cartridges gold/orange caps
    b_gold.add_cylinder(-0.12, 1.57, 0.14, 0.045, 0.02, axis='z', segs=12)
    b_gold.add_cylinder(-0.12, 1.57, -0.14, 0.045, 0.02, axis='z', segs=12)

    # 3. Torso (Coverall Suit)
    b_suit.add_box(0, 1.22, 0, 0.36, 0.44, 0.48) # Upper chest
    b_accent.add_box(0, 1.34, 0, 0.38, 0.12, 0.50) # Shoulder yoke accent
    b_gold.add_box(0, 1.25, 0, 0.37, 0.04, 0.49) # Gold chest brand stripe
    b_suit.add_box(0, 0.92, 0, 0.32, 0.24, 0.42) # Waist / hips
    b_accent.add_box(0, 0.88, 0, 0.34, 0.08, 0.44) # Tool belt / waist harness

    # 4. Legs in athletic, stable painting stance (slightly bent knees)
    # Right leg (front, slightly closer to car)
    b_suit.add_cylinder(-0.06, 0.65, 0.15, 0.11, 0.40, axis='y', segs=12) # Upper leg
    b_accent.add_box(-0.06, 0.46, 0.15, 0.18, 0.12, 0.18) # Knee pad
    b_suit.add_cylinder(-0.06, 0.26, 0.15, 0.095, 0.32, axis='y', segs=12) # Calf

    # Left leg (back / stabilizing)
    b_suit.add_cylinder(0.08, 0.64, -0.15, 0.11, 0.40, axis='y', segs=12) # Upper leg
    b_accent.add_box(0.08, 0.46, -0.15, 0.18, 0.12, 0.18) # Knee pad
    b_suit.add_cylinder(0.08, 0.25, -0.15, 0.095, 0.32, axis='y', segs=12) # Calf

    # 5. Boots
    b_boots.add_box(-0.10, 0.08, 0.15, 0.26, 0.14, 0.14)
    b_boots.add_box(0.06, 0.08, -0.15, 0.26, 0.14, 0.14)
    b_boots.add_box(-0.10, 0.02, 0.15, 0.28, 0.04, 0.15) # Soles
    b_boots.add_box(0.06, 0.02, -0.15, 0.28, 0.04, 0.15)

    # 6. Arms & Hands in focused painting pose
    # Right Arm: Extended forward, aiming spray gun
    b_suit.add_cylinder(-0.10, 1.30, 0.28, 0.08, 0.28, axis='x', segs=10) # Upper arm reaching forward
    b_accent.add_box(-0.25, 1.28, 0.28, 0.12, 0.12, 0.12) # Elbow pad
    b_suit.add_cylinder(-0.42, 1.25, 0.24, 0.07, 0.26, axis='x', segs=10) # Forearm
    b_gloves.add_box(-0.58, 1.24, 0.22, 0.12, 0.08, 0.08) # Right glove gripping gun handle

    # Left Arm: Guiding / stabilizing stance
    b_suit.add_cylinder(-0.04, 1.26, -0.26, 0.08, 0.24, axis='x', segs=10)
    b_suit.add_cylinder(-0.20, 1.10, -0.22, 0.07, 0.26, axis='y', segs=10)
    b_gloves.add_box(-0.22, 0.94, -0.20, 0.10, 0.08, 0.08)

    # 7. Professional HVLP Gravity Feed Spray Gun
    gun_x, gun_y, gun_z = -0.68, 1.24, 0.22
    # Gun handle / grip
    b_gun.add_cylinder(gun_x, gun_y - 0.08, gun_z, 0.022, 0.14, axis='y', segs=10)
    # Gun main barrel / body
    b_gun.add_cylinder(gun_x - 0.08, gun_y, gun_z, 0.028, 0.20, axis='x', segs=12)
    # High-pressure air nozzle (aimed toward car at -X)
    b_gold.add_cylinder(gun_x - 0.19, gun_y, gun_z, 0.024, 0.05, axis='x', segs=12)
    # Trigger
    b_gold.add_box(gun_x - 0.04, gun_y - 0.04, gun_z, 0.03, 0.06, 0.015)
    # Top-mounted Gravity Feed Paint Reservoir Cup (angled upward)
    b_cup.add_cylinder(gun_x - 0.08, gun_y + 0.14, gun_z, 0.055, 0.18, axis='y', segs=14)
    # Paint cup lid
    b_gold.add_cylinder(gun_x - 0.08, gun_y + 0.23, gun_z, 0.06, 0.02, axis='y', segs=14)
    # Air supply hose descending from handle base to floor
    b_accent.add_cylinder(gun_x + 0.02, gun_y - 0.35, gun_z + 0.04, 0.018, 0.45, axis='y', segs=8)

    meshes_list = [
        b_suit.to_dict(),
        b_accent.to_dict(),
        b_gold.to_dict(),
        b_head.to_dict(),
        b_mask.to_dict(),
        b_visor.to_dict(),
        b_gloves.to_dict(),
        b_boots.to_dict(),
        b_gun.to_dict(),
        b_cup.to_dict()
    ]
    return create_glb_from_primitives(meshes_list)

# Generate and write models
os.makedirs("assets/models", exist_ok=True)

car_glb_bytes = generate_car_glb()
with open("assets/models/car.glb", "wb") as f:
    f.write(car_glb_bytes)
print(f"Generated assets/models/car.glb ({len(car_glb_bytes):,} bytes)")

painter_glb_bytes = generate_painter_glb()
with open("assets/models/painter.glb", "wb") as f:
    f.write(painter_glb_bytes)
print(f"Generated assets/models/painter.glb ({len(painter_glb_bytes):,} bytes)")
