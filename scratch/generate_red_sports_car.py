import struct
import json
import math
import os

def create_glb_from_primitives(meshes_data):
    bin_buffer = bytearray()
    json_obj = {
        "asset": {"version": "2.0", "generator": "SunPaintingWorks_RealisticRedSportsSedan"},
        "scenes": [{"nodes": [0]}],
        "nodes": [{"name": "RootNode", "children": []}],
        "meshes": [],
        "materials": [],
        "accessors": [],
        "bufferViews": [],
        "buffers": []
    }

    materials = json_obj["materials"]
    accessors = json_obj["accessors"]
    buffer_views = json_obj["bufferViews"]
    meshes = json_obj["meshes"]

    for idx, m in enumerate(meshes_data):
        mat_def = m["material"]
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

        # Position
        pos_offset = len(bin_buffer)
        min_pos = [float('inf')]*3
        max_pos = [float('-inf')]*3
        for v in m["vertices"]:
            for i in range(3):
                if v[i] < min_pos[i]: min_pos[i] = v[i]
                if v[i] > max_pos[i]: max_pos[i] = v[i]
            bin_buffer.extend(struct.pack('<3f', v[0], v[1], v[2]))
        pos_len = len(bin_buffer) - pos_offset

        # Normal
        norm_offset = len(bin_buffer)
        for n in m["normals"]:
            bin_buffer.extend(struct.pack('<3f', n[0], n[1], n[2]))
        norm_len = len(bin_buffer) - norm_offset

        # UV
        uv_offset = len(bin_buffer)
        for u in m["uvs"]:
            bin_buffer.extend(struct.pack('<2f', u[0], u[1]))
        uv_len = len(bin_buffer) - uv_offset

        # Indices
        ind_offset = len(bin_buffer)
        min_ind = min(m["indices"]) if m["indices"] else 0
        max_ind = max(m["indices"]) if m["indices"] else 0
        for ind in m["indices"]:
            bin_buffer.extend(struct.pack('<H', ind))
        
        pad = (4 - (len(bin_buffer) % 4)) % 4
        bin_buffer.extend(b'\x00' * pad)
        ind_len = len(bin_buffer) - ind_offset

        bv_pos = len(buffer_views)
        buffer_views.append({"buffer": 0, "byteOffset": pos_offset, "byteLength": pos_len, "target": 34962})
        bv_norm = len(buffer_views)
        buffer_views.append({"buffer": 0, "byteOffset": norm_offset, "byteLength": norm_len, "target": 34962})
        bv_uv = len(buffer_views)
        buffer_views.append({"buffer": 0, "byteOffset": uv_offset, "byteLength": uv_len, "target": 34962})
        bv_ind = len(buffer_views)
        buffer_views.append({"buffer": 0, "byteOffset": ind_offset, "byteLength": ind_len, "target": 34963})

        acc_pos = len(accessors)
        accessors.append({"bufferView": bv_pos, "byteOffset": 0, "componentType": 5126, "count": len(m["vertices"]), "type": "VEC3", "min": min_pos, "max": max_pos})
        acc_norm = len(accessors)
        accessors.append({"bufferView": bv_norm, "byteOffset": 0, "componentType": 5126, "count": len(m["normals"]), "type": "VEC3"})
        acc_uv = len(accessors)
        accessors.append({"bufferView": bv_uv, "byteOffset": 0, "componentType": 5126, "count": len(m["uvs"]), "type": "VEC2"})
        acc_ind = len(accessors)
        accessors.append({"bufferView": bv_ind, "byteOffset": 0, "componentType": 5123, "count": len(m["indices"]), "type": "SCALAR", "min": [min_ind], "max": [max_ind]})

        mesh_idx = len(meshes)
        meshes.append({
            "name": m["name"],
            "primitives": [{
                "attributes": {"POSITION": acc_pos, "NORMAL": acc_norm, "TEXCOORD_0": acc_uv},
                "indices": acc_ind,
                "material": idx
            }]
        })

        node_idx = len(json_obj["nodes"])
        json_obj["nodes"].append({"name": m["name"] + "_Node", "mesh": mesh_idx})
        json_obj["nodes"][0]["children"].append(node_idx)

    json_obj["buffers"].append({"byteLength": len(bin_buffer)})

    json_bytes = json.dumps(json_obj, separators=(',', ':')).encode('utf-8')
    json_pad = (4 - (len(json_bytes) % 4)) % 4
    json_bytes += b' ' * json_pad

    bin_pad = (4 - (len(bin_buffer) % 4)) % 4
    bin_buffer.extend(b'\x00' * bin_pad)

    total_len = 12 + 8 + len(json_bytes) + 8 + len(bin_buffer)
    header = struct.pack('<4sII', b'glTF', 2, total_len)
    chunk0_hdr = struct.pack('<I4s', len(json_bytes), b'JSON')
    chunk1_hdr = struct.pack('<I4s', len(bin_buffer), b'BIN\0')

    return header + chunk0_hdr + json_bytes + chunk1_hdr + bytes(bin_buffer)


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
        faces = [
            ([cx+hx, cy-hy, cz-hz], [cx+hx, cy+hy, cz-hz], [cx+hx, cy+hy, cz+hz], [cx+hx, cy-hy, cz+hz], [1,0,0]),
            ([cx-hx, cy-hy, cz+hz], [cx-hx, cy+hy, cz+hz], [cx-hx, cy+hy, cz-hz], [cx-hx, cy-hy, cz-hz], [-1,0,0]),
            ([cx-hx, cy+hy, cz-hz], [cx-hx, cy+hy, cz+hz], [cx+hx, cy+hy, cz+hz], [cx+hx, cy+hy, cz-hz], [0,1,0]),
            ([cx-hx, cy-hy, cz+hz], [cx-hx, cy-hy, cz-hz], [cx+hx, cy-hy, cz-hz], [cx+hx, cy-hy, cz+hz], [0,-1,0]),
            ([cx-hx, cy-hy, cz+hz], [cx+hx, cy-hy, cz+hz], [cx+hx, cy+hy, cz+hz], [cx-hx, cy+hy, cz+hz], [0,0,1]),
            ([cx+hx, cy-hy, cz-hz], [cx-hx, cy-hy, cz-hz], [cx-hx, cy+hy, cz-hz], [cx+hx, cy+hy, cz-hz], [0,0,-1]),
        ]
        for f in faces:
            v0, v1, v2, v3, norm = f
            idx = len(self.vertices)
            self.vertices.extend([v0, v1, v2, v3])
            self.normals.extend([norm, norm, norm, norm])
            self.uvs.extend([[0,0], [1,0], [1,1], [0,1]])
            self.indices.extend([idx, idx+1, idx+2, idx, idx+2, idx+3])

    def add_sloped_box(self, cx, cy, cz, sx, sy, sz, top_sx=None, top_sz=None, top_ox=0.0, top_oz=0.0):
        """Creates an aerodynamically tapered/sloped box for realistic car hood, cabin, and fastback."""
        if top_sx is None: top_sx = sx
        if top_sz is None: top_sz = sz
        hx_b, hy, hz_b = sx/2.0, sy/2.0, sz/2.0
        hx_t, hz_t = top_sx/2.0, top_sz/2.0

        # Bottom 4 vertices at y = cy - hy
        b0 = [cx - hx_b, cy - hy, cz - hz_b]
        b1 = [cx - hx_b, cy - hy, cz + hz_b]
        b2 = [cx + hx_b, cy - hy, cz + hz_b]
        b3 = [cx + hx_b, cy - hy, cz - hz_b]

        # Top 4 vertices at y = cy + hy
        t0 = [cx + top_ox - hx_t, cy + hy, cz + top_oz - hz_t]
        t1 = [cx + top_ox - hx_t, cy + hy, cz + top_oz + hz_t]
        t2 = [cx + top_ox + hx_t, cy + hy, cz + top_oz + hz_t]
        t3 = [cx + top_ox + hx_t, cy + hy, cz + top_oz - hz_t]

        def quad(v0, v1, v2, v3):
            idx = len(self.vertices)
            # Compute normal
            ux, uy, uz = v1[0]-v0[0], v1[1]-v0[1], v1[2]-v0[2]
            vx, vy, vz = v2[0]-v0[0], v2[1]-v0[1], v2[2]-v0[2]
            nx = uy*vz - uz*vy
            ny = uz*vx - ux*vz
            nz = ux*vy - uy*vx
            l = math.sqrt(nx*nx + ny*ny + nz*nz) or 1.0
            norm = [nx/l, ny/l, nz/l]
            self.vertices.extend([v0, v1, v2, v3])
            self.normals.extend([norm, norm, norm, norm])
            self.uvs.extend([[0,0], [1,0], [1,1], [0,1]])
            self.indices.extend([idx, idx+1, idx+2, idx, idx+2, idx+3])

        quad(b3, t3, t2, b2) # +X face (Front)
        quad(b1, t1, t0, b0) # -X face (Rear)
        quad(t0, t1, t2, t3) # +Y face (Top)
        quad(b1, b0, b3, b2) # -Y face (Bottom)
        quad(b2, t2, t1, b1) # +Z face (Left)
        quad(b0, t0, t3, b3) # -Z face (Right)

    def add_cylinder(self, cx, cy, cz, r, h, axis='y', segs=16):
        base_idx = len(self.vertices)
        hh = h / 2.0
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


def generate_red_sports_sedan_glb():
    # 1. Premium Materials
    mat_red_paint = {
        "name": "Glossy_Red_Automotive_Paint",
        "baseColorFactor": [0.82, 0.05, 0.08, 1.0], # Vivid Rich Ferrari / Soul Red Metallic
        "metallicFactor": 0.88,
        "roughnessFactor": 0.12
    }
    mat_black_trim = {
        "name": "Gloss_Black_Aero_Trim",
        "baseColorFactor": [0.08, 0.09, 0.11, 1.0],
        "metallicFactor": 0.75,
        "roughnessFactor": 0.20
    }
    mat_chrome = {
        "name": "Polished_Chrome_Accents",
        "baseColorFactor": [0.95, 0.96, 0.98, 1.0],
        "metallicFactor": 0.98,
        "roughnessFactor": 0.05
    }
    mat_glass = {
        "name": "Tinted_Automotive_Glass",
        "baseColorFactor": [0.10, 0.14, 0.20, 0.80],
        "metallicFactor": 0.20,
        "roughnessFactor": 0.05
    }
    mat_tires = {
        "name": "Performance_Rubber_Tires",
        "baseColorFactor": [0.07, 0.08, 0.09, 1.0],
        "metallicFactor": 0.05,
        "roughnessFactor": 0.85
    }
    mat_rims_silver = {
        "name": "Machined_Sports_Alloy_Rims",
        "baseColorFactor": [0.88, 0.90, 0.94, 1.0],
        "metallicFactor": 0.95,
        "roughnessFactor": 0.15
    }
    mat_brake_rotor = {
        "name": "Ventilated_Steel_Brake_Discs",
        "baseColorFactor": [0.65, 0.68, 0.72, 1.0],
        "metallicFactor": 0.90,
        "roughnessFactor": 0.30
    }
    mat_brake_caliper = {
        "name": "Racing_Brembo_Red_Calipers",
        "baseColorFactor": [0.90, 0.02, 0.05, 1.0],
        "metallicFactor": 0.40,
        "roughnessFactor": 0.20
    }
    mat_headlight_lens = {
        "name": "Polycarbonate_Headlight_Lenses",
        "baseColorFactor": [0.95, 0.97, 1.0, 0.90],
        "metallicFactor": 0.10,
        "roughnessFactor": 0.08
    }
    mat_headlight_led = {
        "name": "Projector_LED_HighBeam",
        "baseColorFactor": [1.0, 1.0, 1.0, 1.0],
        "emissiveFactor": [0.98, 0.98, 0.98],
        "metallicFactor": 0.10,
        "roughnessFactor": 0.05
    }
    mat_drl_strip = {
        "name": "LED_Daytime_Running_Lights",
        "baseColorFactor": [0.85, 0.95, 1.0, 1.0],
        "emissiveFactor": [0.85, 0.95, 1.0],
        "metallicFactor": 0.10,
        "roughnessFactor": 0.10
    }
    mat_taillight_lens = {
        "name": "Ruby_Taillight_Lenses",
        "baseColorFactor": [0.85, 0.02, 0.04, 1.0],
        "emissiveFactor": [0.70, 0.02, 0.03],
        "metallicFactor": 0.20,
        "roughnessFactor": 0.10
    }
    mat_interior = {
        "name": "Luxury_Cabin_Interior",
        "baseColorFactor": [0.12, 0.12, 0.14, 1.0],
        "metallicFactor": 0.10,
        "roughnessFactor": 0.60
    }

    b_paint = MeshBuilder("Sedan_Red_Body", mat_red_paint)
    b_black = MeshBuilder("Sedan_GlossBlack_Trim", mat_black_trim)
    b_chrome = MeshBuilder("Sedan_Chrome_Accents", mat_chrome)
    b_glass = MeshBuilder("Sedan_Tinted_Glass", mat_glass)
    b_tires = MeshBuilder("Sedan_Performance_Tires", mat_tires)
    b_rims = MeshBuilder("Sedan_Alloy_Rims", mat_rims_silver)
    b_rotors = MeshBuilder("Sedan_Brake_Rotors", mat_brake_rotor)
    b_calipers = MeshBuilder("Sedan_Brake_Calipers", mat_brake_caliper)
    b_hl_lens = MeshBuilder("Sedan_Headlight_Lenses", mat_headlight_lens)
    b_hl_led = MeshBuilder("Sedan_LED_Projectors", mat_headlight_led)
    b_drl = MeshBuilder("Sedan_DRL_Lightbars", mat_drl_strip)
    b_taillights = MeshBuilder("Sedan_LED_Taillights", mat_taillight_lens)
    b_interior = MeshBuilder("Sedan_Cabin_Interior", mat_interior)

    # ------------------------------------------------------------
    # 2. CAR BODY & SCULPTED GEOMETRY (Total length ~ 4.4m, width ~ 1.9m, height ~ 1.42m)
    # Front is at +X, Rear is at -X, Passenger side is at +Z, Driver side at -Z
    # ------------------------------------------------------------

    # A. Lower Chassis & Underbody Platform
    b_paint.add_box(0, 0.44, 0, 4.30, 0.32, 1.86)

    # B. Front Hood & Sculpted Front Fascia
    # Raked wedge hood sloping from windshield forward
    b_paint.add_sloped_box(cx=1.35, cy=0.72, cz=0, sx=1.55, sy=0.28, sz=1.82, top_sx=1.45, top_sz=1.65, top_ox=-0.08)
    # Hood power bulge / center spine
    b_paint.add_sloped_box(cx=1.35, cy=0.88, cz=0, sx=1.45, sy=0.06, sz=0.62, top_sx=1.35, top_sz=0.48, top_ox=-0.06)

    # C. Aggressive Front Bumper, Splitter & Lower Grille
    # Front bumper nose cone
    b_paint.add_sloped_box(cx=2.16, cy=0.50, cz=0, sx=0.22, sy=0.36, sz=1.80, top_sx=0.18, top_sz=1.68, top_ox=-0.03)
    # Lower front chin splitter in gloss black
    b_black.add_box(2.20, 0.26, 0, 0.24, 0.06, 1.84)
    # Main Hexagonal Front Radiator Grille
    b_black.add_box(2.22, 0.54, 0, 0.05, 0.28, 1.05)
    # Chrome Grille Outer Surround Ring
    b_chrome.add_box(2.23, 0.54, 0, 0.03, 0.30, 1.08)
    # Front Sun Painting Works Brand Star Emblem
    b_chrome.add_cylinder(2.24, 0.58, 0, 0.055, 0.02, axis='x', segs=16)
    # Dual Lower Brake Cooling Air Ducts (Left & Right)
    b_black.add_box(2.18, 0.40, 0.68, 0.10, 0.16, 0.32)
    b_black.add_box(2.18, 0.40, -0.68, 0.10, 0.16, 0.32)

    # D. Sculpted Sports Sedan Greenhouse / Passenger Cabin
    # Main roof section
    b_paint.add_sloped_box(cx=-0.16, cy=1.18, cz=0, sx=2.10, sy=0.56, sz=1.62, top_sx=1.55, top_sz=1.36, top_ox=-0.12)
    # Aerodynamic Roof Turret Top Panel
    b_paint.add_box(-0.24, 1.46, 0, 1.48, 0.04, 1.32)
    # Shark-Fin Antenna in Gloss Black
    b_black.add_sloped_box(cx=-0.80, cy=1.52, cz=0, sx=0.18, sy=0.09, sz=0.04, top_sx=0.08, top_sz=0.02, top_ox=-0.04)

    # E. Rear Decklid, Trunk & Fastback
    # Sloping rear trunk
    b_paint.add_sloped_box(cx=-1.46, cy=0.74, cz=0, sx=1.30, sy=0.28, sz=1.82, top_sx=1.15, top_sz=1.62, top_ox=0.08)
    # Integrated Rear Lip / Ducktail Spoiler in Gloss Black
    b_black.add_box(-2.14, 0.89, 0, 0.18, 0.05, 1.62)
    # Rear Bumper
    b_paint.add_box(-2.15, 0.48, 0, 0.20, 0.34, 1.82)
    # Lower Rear Aerodynamic Diffuser with Strakes (Gloss Black)
    b_black.add_box(-2.18, 0.30, 0, 0.22, 0.12, 1.50)
    for dz in [-0.45, -0.15, 0.15, 0.45]:
        b_black.add_box(-2.22, 0.28, dz, 0.14, 0.08, 0.03)

    # Quad Stainless Chrome Exhaust Tips
    for ez in [0.58, 0.70, -0.58, -0.70]:
        b_chrome.add_cylinder(-2.24, 0.28, ez, 0.048, 0.16, axis='x', segs=14)

    # F. Aerodynamic Side Skirts & Door Rocker Panels
    b_black.add_box(0, 0.28, 0.94, 2.70, 0.08, 0.06)
    b_black.add_box(0, 0.28, -0.94, 2.70, 0.08, 0.06)
    # Door Body Character Crease (Sculpted Swage Line)
    b_paint.add_box(0, 0.68, 0.93, 2.50, 0.05, 0.03)
    b_paint.add_box(0, 0.68, -0.93, 2.50, 0.05, 0.03)

    # G. Aerodynamic Wing Mirrors (Dual-Tone Red & Gloss Black)
    for mz in [0.96, -0.96]:
        sg = 1 if mz > 0 else -1
        # Mirror mounting stalk in gloss black
        b_black.add_cylinder(0.72, 0.98, mz + sg*0.04, 0.02, 0.12, axis='z', segs=8)
        # Mirror aerodynamic housing in glossy red
        b_paint.add_sloped_box(cx=0.68, cy=1.03, cz=mz + sg*0.14, sx=0.22, sy=0.12, sz=0.16, top_sx=0.18, top_sz=0.14)
        # Mirror reflective surface / turn indicator strip
        b_chrome.add_box(0.66, 1.03, mz + sg*0.21, 0.16, 0.09, 0.02)
        b_drl.add_box(0.76, 1.03, mz + sg*0.14, 0.06, 0.02, 0.14)

    # H. Flush Chrome Door Handles (Front & Rear, Both Sides)
    for hx in [0.38, -0.38]:
        b_chrome.add_box(hx, 0.78, 0.94, 0.15, 0.035, 0.02)
        b_chrome.add_box(hx, 0.78, -0.94, 0.15, 0.035, 0.02)

    # ------------------------------------------------------------
    # 3. CABIN INTERIOR SILHOUETTE (Visible through tinted glass)
    # ------------------------------------------------------------
    # Dashboard & Instrument Binnacle
    b_interior.add_box(0.55, 0.96, 0, 0.45, 0.18, 1.30)
    # Steering wheel
    b_interior.add_cylinder(0.42, 1.04, -0.35, 0.14, 0.03, axis='x', segs=12)
    # Front Sports Bucket Seats (Driver & Passenger)
    b_interior.add_box(0.05, 0.90, 0.35, 0.48, 0.50, 0.42)
    b_interior.add_box(0.05, 0.90, -0.35, 0.48, 0.50, 0.42)
    # Front Headrests
    b_interior.add_box(-0.02, 1.25, 0.35, 0.16, 0.18, 0.22)
    b_interior.add_box(-0.02, 1.25, -0.35, 0.16, 0.18, 0.22)
    # Rear Bench Seat & Headrests
    b_interior.add_box(-0.65, 0.88, 0, 0.55, 0.46, 1.25)
    b_interior.add_box(-0.75, 1.20, 0.38, 0.15, 0.16, 0.20)
    b_interior.add_box(-0.75, 1.20, -0.38, 0.15, 0.16, 0.20)

    # ------------------------------------------------------------
    # 4. REALISTIC AUTOMOTIVE TINTED GLASS & PILLARS
    # ------------------------------------------------------------
    # Windshield (Sloped forward)
    b_glass.add_sloped_box(cx=0.74, cy=1.16, cz=0, sx=0.72, sy=0.50, sz=1.52, top_sx=0.62, top_sz=1.34, top_ox=-0.14)
    # Rear Fastback Window (Sloped backward)
    b_glass.add_sloped_box(cx=-1.06, cy=1.18, cz=0, sx=0.68, sy=0.48, sz=1.50, top_sx=0.58, top_sz=1.32, top_ox=0.14)
    # Side Windows (Left & Right)
    b_glass.add_box(-0.16, 1.18, 0.77, 1.70, 0.38, 0.04)
    b_glass.add_box(-0.16, 1.18, -0.77, 1.70, 0.38, 0.04)
    # Center B-Pillars in Gloss Black
    b_black.add_box(-0.16, 1.18, 0.79, 0.10, 0.40, 0.03)
    b_black.add_box(-0.16, 1.18, -0.79, 0.10, 0.40, 0.03)
    # Surrounding Gloss Black Window Trim Frame
    b_black.add_box(-0.16, 1.38, 0.79, 1.72, 0.03, 0.02)
    b_black.add_box(-0.16, 1.38, -0.79, 1.72, 0.03, 0.02)

    # ------------------------------------------------------------
    # 5. HIGH-INTENSITY JEWEL-EYE LED HEADLIGHTS
    # ------------------------------------------------------------
    for hz in [0.66, -0.66]:
        sg = 1 if hz > 0 else -1
        # Headlight sculpted polycarbonate outer lens
        b_hl_lens.add_sloped_box(cx=2.14, cy=0.66, cz=hz, sx=0.14, sy=0.14, sz=0.38, top_sx=0.10, top_sz=0.32, top_ox=-0.04)
        # Dual Projector LED Bulbs (High & Low Beam)
        b_hl_led.add_cylinder(2.13, 0.67, hz - sg*0.07, 0.042, 0.06, axis='x', segs=14)
        b_hl_led.add_cylinder(2.11, 0.67, hz + sg*0.07, 0.038, 0.06, axis='x', segs=14)
        # L-shaped DRL Daytime Running Light Eyebrow
        b_drl.add_box(2.14, 0.72, hz, 0.08, 0.025, 0.32)
        b_drl.add_box(2.15, 0.66, hz + sg*0.16, 0.06, 0.10, 0.025)

    # ------------------------------------------------------------
    # 6. SLEEK MODERN FULL-WIDTH LED TAILLIGHTS
    # ------------------------------------------------------------
    for tz in [0.66, -0.66]:
        b_taillights.add_box(-2.15, 0.72, tz, 0.08, 0.12, 0.42)
    # Connecting Full-Width Horizontal Light Bar across trunk deck
    b_taillights.add_box(-2.16, 0.74, 0, 0.05, 0.04, 0.90)

    # ------------------------------------------------------------
    # 7. PERFORMANCE SPORTS ALLOY WHEELS, VENTILATED DISCS & BRAKES
    # ------------------------------------------------------------
    wheel_coords = [
        (1.42, 0.35, 0.92, 1),
        (1.42, 0.35, -0.92, -1),
        (-1.42, 0.35, 0.92, 1),
        (-1.42, 0.35, -0.92, -1)
    ]

    for wx, wy, wz, side in wheel_coords:
        # A. Performance Rubber Tire with Curved Tread Profile
        b_tires.add_cylinder(wx, wy, wz, r=0.35, h=0.25, axis='z', segs=24)

        # B. Ventilated Steel Brake Rotor Disc (Inside wheel)
        b_rotors.add_cylinder(wx, wy, wz - side*0.04, r=0.23, h=0.03, axis='z', segs=20)

        # C. Racing Brembo Red Brake Caliper (Mounted at top-rear)
        b_calipers.add_box(wx - 0.12, wy + 0.14, wz - side*0.03, 0.14, 0.08, 0.06)

        # D. Machined Sports Alloy Rim Outer Lip
        b_rims.add_cylinder(wx, wy, wz + side*0.03, r=0.25, h=0.20, axis='z', segs=24)

        # E. Center Wheel Hub with Logo Cap
        b_chrome.add_cylinder(wx, wy, wz + side*0.13, r=0.065, h=0.02, axis='z', segs=16)

        # F. 10 Dual-Tone Sports Alloy Spokes radiating outward
        for spk_idx in range(10):
            theta = (2.0 * math.pi * spk_idx) / 10.0
            spk_r = 0.15
            spk_x = wx + math.cos(theta) * spk_r
            spk_y = wy + math.sin(theta) * spk_r
            b_rims.add_box(spk_x, spk_y, wz + side*0.11, 0.03, 0.03, 0.04)

        # G. 5 Chrome Lug Nuts
        for lug_idx in range(5):
            lug_theta = (2.0 * math.pi * lug_idx) / 5.0
            lug_x = wx + math.cos(lug_theta) * 0.042
            lug_y = wy + math.sin(lug_theta) * 0.042
            b_chrome.add_cylinder(lug_x, lug_y, wz + side*0.135, r=0.012, h=0.015, axis='z', segs=8)

    # ------------------------------------------------------------
    # 8. Compile All Meshes into GLB
    # ------------------------------------------------------------
    all_meshes = [
        b_paint.to_dict(),
        b_black.to_dict(),
        b_chrome.to_dict(),
        b_glass.to_dict(),
        b_interior.to_dict(),
        b_hl_lens.to_dict(),
        b_hl_led.to_dict(),
        b_drl.to_dict(),
        b_taillights.to_dict(),
        b_tires.to_dict(),
        b_rims.to_dict(),
        b_rotors.to_dict(),
        b_calipers.to_dict()
    ]

    return create_glb_from_primitives(all_meshes)


if __name__ == "__main__":
    glb_data = generate_red_sports_sedan_glb()
    target_path = "assets/models/car.glb"
    with open(target_path, "wb") as f:
        f.write(glb_data)
    print(f"Generated realistic red sports sedan GLB: {len(glb_data):,} bytes -> {target_path}")
