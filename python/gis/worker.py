"""Bounded point-file conversion. Laravel alone authorizes and saves records."""
import csv
import json
import math
import os
from pathlib import Path
import re
import stat
import sys
import tempfile
import xml.etree.ElementTree as ET
import zipfile

MAX_BYTES = 5 * 1024 * 1024
MAX_ROWS = 1000
MAX_EXPANDED = 20 * 1024 * 1024
FIELDS = ["association_id", "location_name", "latitude", "longitude", "crs"]
NS = "http://www.opengis.net/kml/2.2"


def limit_resources():
    """Apply a hard memory cap before any GIS driver reads untrusted data."""
    cap = 1024 * 1024 * 1024
    if os.name == "posix":
        import resource
        resource.setrlimit(resource.RLIMIT_AS, (cap, cap))
        resource.setrlimit(resource.RLIMIT_CPU, (25, 25))
        resource.setrlimit(resource.RLIMIT_FSIZE, (MAX_EXPANDED, MAX_EXPANDED))
        return None
    if os.name != "nt":
        raise ValueError("The processing platform does not support the required resource limits.")
    import ctypes as c
    from ctypes import wintypes as w

    class Basic(c.Structure):
        _fields_ = [("process_time", c.c_int64), ("job_time", c.c_int64), ("flags", w.DWORD),
                    ("min_working", c.c_size_t), ("max_working", c.c_size_t), ("processes", w.DWORD),
                    ("affinity", c.c_size_t), ("priority", w.DWORD), ("scheduling", w.DWORD)]

    class Extended(c.Structure):
        _fields_ = [("basic", Basic), ("io", c.c_uint64 * 6), ("process_memory", c.c_size_t),
                    ("job_memory", c.c_size_t), ("peak_process", c.c_size_t), ("peak_job", c.c_size_t)]

    kernel = c.WinDLL("kernel32", use_last_error=True)
    kernel.CreateJobObjectW.argtypes, kernel.CreateJobObjectW.restype = [c.c_void_p, w.LPCWSTR], w.HANDLE
    kernel.SetInformationJobObject.argtypes, kernel.SetInformationJobObject.restype = [w.HANDLE, c.c_int, c.c_void_p, w.DWORD], w.BOOL
    kernel.GetCurrentProcess.argtypes, kernel.GetCurrentProcess.restype = [], w.HANDLE
    kernel.AssignProcessToJobObject.argtypes, kernel.AssignProcessToJobObject.restype = [w.HANDLE, w.HANDLE], w.BOOL
    job = kernel.CreateJobObjectW(None, None)
    limits = Extended()
    limits.basic.flags = 0x100 | 0x8  # Process memory and one active process.
    limits.basic.processes = 1
    limits.process_memory = cap
    if not job or not kernel.SetInformationJobObject(job, 9, c.byref(limits), c.sizeof(limits)) or not kernel.AssignProcessToJobObject(job, kernel.GetCurrentProcess()):
        raise ValueError("The operating system could not apply GIS process limits.")
    # Retain this handle until exit; the OS releases it with the process.
    return job


def bounded_rows(rows):
    result = []
    for row in rows:
        if len(result) >= MAX_ROWS:
            raise ValueError("A file may contain at most 1,000 records.")
        result.append(row)
    if not result:
        raise ValueError("The file contains no locations.")
    return result


def point(properties, coordinates):
    if not isinstance(properties, dict) or not isinstance(coordinates, (list, tuple)) or len(coordinates) != 2:
        raise ValueError("Use point features with exactly longitude and latitude.")
    return {"association_id": properties.get("association_id", properties.get("assoc_id")),
            "location_name": properties.get("location_name", properties.get("loc_name")),
            "latitude": coordinates[1], "longitude": coordinates[0], "crs": "EPSG:4326"}


def read_csv(path):
    csv.field_size_limit(4096)
    with path.open(encoding="utf-8-sig", newline="") as source:
        reader = csv.DictReader(source, strict=True)
        if reader.fieldnames != FIELDS:
            raise ValueError("CSV columns must be association_id,location_name,latitude,longitude,crs in that order.")
        return bounded_rows(reader), "EPSG:4326 (declared per row)"


def read_geojson(path):
    data = json.loads(path.read_text(encoding="utf-8-sig"), parse_constant=lambda _: (_ for _ in ()).throw(ValueError("Non-finite JSON number.")))
    if not isinstance(data, dict) or data.get("type") != "FeatureCollection" or "crs" in data:
        raise ValueError("Use an RFC 7946 FeatureCollection without a legacy CRS declaration.")
    features = data.get("features")
    if not isinstance(features, list):
        raise ValueError("GeoJSON features must be an array.")
    rows = []
    for feature in bounded_rows(features):
        if not isinstance(feature, dict) or feature.get("type") != "Feature":
            raise ValueError("Invalid GeoJSON feature.")
        geom = feature.get("geometry")
        if not isinstance(geom, dict) or geom.get("type") != "Point" or "crs" in feature or "crs" in geom:
            raise ValueError("Only WGS84 Point features are supported.")
        props = feature.get("properties")
        if isinstance(props, dict) and props.get("crs", "EPSG:4326") != "EPSG:4326":
            raise ValueError("Conflicting feature CRS.")
        rows.append(point(props, geom.get("coordinates")))
    return rows, "EPSG:4326 (RFC 7946)"


def read_kml(path):
    # UTF-8 only, no DTD or entities: reject before the XML parser sees the document.
    text = path.read_text(encoding="utf-8-sig")
    if re.search(r"<!\s*(DOCTYPE|ENTITY)", text, re.I):
        raise ValueError("KML document types and entities are not allowed.")
    root = ET.fromstring(text)
    if root.tag != f"{{{NS}}}kml":
        raise ValueError("Use an OGC KML 2.2 document.")
    forbidden = {"NetworkLink", "Model", "MultiGeometry", "LineString", "Polygon", "Track", "GroundOverlay"}
    if any(element.tag.rsplit("}", 1)[-1] in forbidden for element in root.iter()):
        raise ValueError("KML supports local Point placemarks only.")
    rows = []
    for mark in bounded_rows(root.iter(f"{{{NS}}}Placemark")):
        points = mark.findall(f"{{{NS}}}Point")
        if len(points) != 1:
            raise ValueError("Each placemark must have one Point.")
        coords = (points[0].findtext(f"{{{NS}}}coordinates") or "").strip().split(",")
        if len(coords) not in (2, 3) or (len(coords) == 3 and float(coords[2]) != 0):
            raise ValueError("Only two-dimensional points (or zero altitude) are supported.")
        attributes = {}
        for entry in mark.findall(f"{{{NS}}}ExtendedData/{{{NS}}}Data"):
            key = entry.get("name")
            if key in attributes:
                raise ValueError("Duplicate KML property.")
            attributes[key] = entry.findtext(f"{{{NS}}}value")
        attributes["location_name"] = mark.findtext(f"{{{NS}}}name")
        rows.append(point(attributes, coords[:2]))
    return rows, "EPSG:4326 (KML 2.2)"


def extract_shape(path, directory):
    with zipfile.ZipFile(path) as archive:
        files = archive.infolist()
        if not 4 <= len(files) <= 5:
            raise ValueError("Provide one Shapefile with four required files and optional .cpg.")
        names = set()
        total = 0
        for entry in files:
            if not re.fullmatch(r"[A-Za-z0-9_-]{1,64}\.(shp|shx|dbf|prj|cpg)", entry.filename, re.I):
                raise ValueError("ZIP filenames must be simple Shapefile companion names without paths.")
            name = entry.filename.lower()
            if name in names or stat.S_ISLNK(entry.external_attr >> 16) or entry.flag_bits & 1:
                raise ValueError("Duplicate, linked, or encrypted ZIP entries are not allowed.")
            names.add(name)
            total += entry.file_size
            if total > MAX_EXPANDED or entry.file_size > 200 * max(1, entry.compress_size):
                raise ValueError("ZIP expansion exceeds the safe limits.")
        stems = {Path(name).stem for name in names}
        extensions = {Path(name).suffix for name in names}
        if len(stems) != 1 or not {".shp", ".shx", ".dbf", ".prj"}.issubset(extensions):
            raise ValueError("The .shp, .shx, .dbf and .prj files must share one name.")
        for entry in files:
            with archive.open(entry) as source, (directory / entry.filename.lower()).open("wb") as target:
                copied = 0
                while chunk := source.read(65536):
                    copied += len(chunk)
                    if copied > entry.file_size or copied > MAX_EXPANDED:
                        raise ValueError("ZIP data exceeds its declared size.")
                    target.write(chunk)
        return directory / (next(iter(stems)) + ".shp")


def read_shape(path):
    with tempfile.TemporaryDirectory(dir=path.parent) as temporary:
        shp = extract_shape(path, Path(temporary))
        import geopandas as gpd
        import pyogrio
        frame = pyogrio.read_dataframe(shp, max_features=MAX_ROWS + 1)
        bounded_rows(frame.index)
        if frame.crs is None or frame.crs.to_epsg() not in (4326, 3857):
            raise ValueError("Shapefile .prj must identify EPSG:4326 or EPSG:3857.")
        crs = frame.crs.to_string()
        if not frame.geometry.geom_type.eq("Point").all() or not frame.geometry.is_valid.all() or frame.geometry.has_z.any():
            raise ValueError("Shapefile must contain valid two-dimensional points only.")
        frame = gpd.GeoDataFrame(frame).to_crs(4326)
        rows = [point(row.to_dict(), [row.geometry.x, row.geometry.y]) for _, row in frame.iterrows()]
        return rows, crs


def validate(rows):
    errors = []
    for index, row in enumerate(rows, 1):
        try:
            if not re.fullmatch(r"[1-9][0-9]{0,14}", str(row.get("association_id", ""))):
                raise ValueError("association_id must be an existing positive database ID.")
            name = row.get("location_name")
            if not isinstance(name, str) or not name.strip() or len(name) > 255:
                raise ValueError("location_name is required and must be at most 255 characters.")
            if row.get("crs") != "EPSG:4326":
                raise ValueError("CRS must be explicitly EPSG:4326.")
            for field, limit in (("latitude", 90), ("longitude", 180)):
                value = row.get(field)
                if isinstance(value, bool) or len(str(value)) > 128 or not math.isfinite(float(value)) or abs(float(value)) > limit:
                    raise ValueError("Coordinates must be finite and within geographic ranges.")
            row["location_name"] = name.strip()
        except (ValueError, TypeError) as error:
            errors.append({"row": index, "message": str(error)})
    return errors


def export(rows, format_name, target):
    rows = bounded_rows(rows)
    if validate(rows):
        raise ValueError("Export records failed point validation.")
    if format_name == "csv":
        with target.open("w", encoding="utf-8", newline="") as output:
            writer = csv.DictWriter(output, fieldnames=FIELDS)
            writer.writeheader()
            for row in rows:
                safe = {field: row[field] for field in FIELDS}
                # Prevent spreadsheet formulas when opening an administrator export.
                if safe["location_name"].lstrip().startswith(("=", "+", "-", "@")):
                    safe["location_name"] = "'" + safe["location_name"]
                writer.writerow(safe)
    elif format_name == "geojson":
        features = [{"type": "Feature", "geometry": {"type": "Point", "coordinates": [float(r["longitude"]), float(r["latitude"])]},
                     "properties": {"association_id": r["association_id"], "location_name": r["location_name"]}} for r in rows]
        target.write_text(json.dumps({"type": "FeatureCollection", "features": features}, allow_nan=False), encoding="utf-8")
    elif format_name == "kml":
        ET.register_namespace("", NS)
        root = ET.Element(f"{{{NS}}}kml")
        doc = ET.SubElement(root, f"{{{NS}}}Document")
        for row in rows:
            mark = ET.SubElement(doc, f"{{{NS}}}Placemark")
            ET.SubElement(mark, f"{{{NS}}}name").text = row["location_name"]
            metadata = ET.SubElement(mark, f"{{{NS}}}ExtendedData")
            entry = ET.SubElement(metadata, f"{{{NS}}}Data", name="association_id")
            ET.SubElement(entry, f"{{{NS}}}value").text = str(row["association_id"])
            geom = ET.SubElement(mark, f"{{{NS}}}Point")
            ET.SubElement(geom, f"{{{NS}}}coordinates").text = f"{row['longitude']},{row['latitude']}"
        ET.ElementTree(root).write(target, encoding="utf-8", xml_declaration=True)
    elif format_name == "zip":
        import geopandas as gpd
        import pyogrio
        with tempfile.TemporaryDirectory(dir=target.parent) as temporary:
            directory = Path(temporary)
            if any(len(r["location_name"].encode("utf-8")) > 254 for r in rows):
                raise ValueError("Shapefile names must fit in 254 UTF-8 bytes; use GeoJSON to preserve longer names.")
            frame = gpd.GeoDataFrame({"assoc_id": [str(r["association_id"]) for r in rows], "loc_name": [r["location_name"] for r in rows]},
                                    geometry=gpd.points_from_xy([float(r["longitude"]) for r in rows], [float(r["latitude"]) for r in rows]), crs=4326)
            pyogrio.write_dataframe(frame, directory / "locations.shp", driver="ESRI Shapefile", encoding="UTF-8")
            with zipfile.ZipFile(target, "w", zipfile.ZIP_DEFLATED) as archive:
                for path in sorted(directory.iterdir()):
                    archive.write(path, path.name)
    else:
        raise ValueError("Unsupported export format.")
    if target.stat().st_size > 10 * 1024 * 1024:
        raise ValueError("Export exceeds 10 MiB.")


def main():
    resource_handle = limit_resources()
    operation, format_name, source_name, target_name = sys.argv[1:]
    source, target = Path(source_name), Path(target_name)
    if source.stat().st_size > MAX_BYTES:
        raise ValueError("Input exceeds 5 MiB.")
    if operation == "import":
        readers = {"csv": read_csv, "geojson": read_geojson, "kml": read_kml, "zip": read_shape}
        if format_name not in readers:
            raise ValueError("Unsupported import format.")
        rows, detected = readers[format_name](source)
        result = {"version": 1, "geometry": "Point", "crs": "EPSG:4326", "detected_crs": detected,
                  "records": rows, "errors": validate(rows), "warnings": []}
        target.write_text(json.dumps(result, allow_nan=False), encoding="utf-8")
    elif operation == "export":
        export(json.loads(source.read_text(encoding="utf-8")), format_name, target)
    else:
        raise ValueError("Unsupported operation.")
    print(json.dumps({"ok": True, "version": 1}))


if __name__ == "__main__":
    try:
        main()
    except ModuleNotFoundError:
        print(json.dumps({"ok": False, "message": "GIS dependencies are unavailable. Configure the documented Python environment."}))
        sys.exit(3)
    except (ValueError, TypeError, KeyError, OSError, OverflowError, RecursionError, MemoryError, csv.Error, ET.ParseError, zipfile.BadZipFile) as error:
        print(json.dumps({"ok": False, "message": str(error)[:300]}))
        sys.exit(2)
    except Exception:
        print(json.dumps({"ok": False, "message": "GIS processing failed safely. Check the dataset and configured drivers."}))
        sys.exit(4)
