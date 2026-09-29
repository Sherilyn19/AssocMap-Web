import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
import zipfile

import worker


class FileTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.path = Path(self.directory.name)
        self.rows = [{"association_id": "1", "location_name": "Coastal & Landing", "latitude": "10.123456789", "longitude": "123.5", "crs": "EPSG:4326"}]

    def test_csv_geojson_kml_round_trip(self):
        for format_name, reader in [("csv", worker.read_csv), ("geojson", worker.read_geojson), ("kml", worker.read_kml)]:
            with self.subTest(format=format_name):
                target = self.path / format_name
                worker.export(self.rows, format_name, target)
                rows, crs = reader(target)
                self.assertEqual([], worker.validate(rows))
                self.assertEqual(self.rows[0]["location_name"], rows[0]["location_name"])
                self.assertAlmostEqual(10.123456789, float(rows[0]["latitude"]))
                self.assertIn("4326", crs)

    @unittest.skipUnless(importlib.util.find_spec("geopandas") and importlib.util.find_spec("pyogrio"), "GIS packages unavailable")
    def test_real_shapefile_round_trip_and_projected_crs(self):
        import geopandas as gpd
        import pyogrio
        target = self.path / "shape.zip"
        worker.export(self.rows, "zip", target)
        rows, _ = worker.read_shape(target)
        self.assertEqual([], worker.validate(rows))
        directory = self.path / "projected"
        directory.mkdir()
        frame = gpd.GeoDataFrame({"assoc_id": ["1"], "loc_name": ["Projected"]}, geometry=gpd.points_from_xy([123], [10]), crs=4326).to_crs(3857)
        pyogrio.write_dataframe(frame, directory / "points.shp", driver="ESRI Shapefile")
        with zipfile.ZipFile(target, "w") as archive:
            for file in directory.iterdir():
                archive.write(file, file.name)
        rows, crs = worker.read_shape(target)
        self.assertIn("3857", crs)
        self.assertAlmostEqual(10, rows[0]["latitude"])

    def test_csv_rejects_unknown_crs_missing_columns_and_invalid_coordinates(self):
        self.rows[0]["crs"] = "unknown"
        self.assertTrue(worker.validate(self.rows))
        self.rows[0]["crs"] = "EPSG:4326"
        for value in ["NaN", "Infinity", "91", "x", True]:
            self.rows[0]["latitude"] = value
            self.assertTrue(worker.validate(self.rows))
        target = self.path / "bad.csv"
        target.write_text("name,x,y\nSite,1,2", encoding="utf-8")
        with self.assertRaises(ValueError):
            worker.read_csv(target)

    def test_geojson_rejects_lines_legacy_crs_and_nonfinite_values(self):
        target = self.path / "bad.json"
        for data in [{"type": "FeatureCollection", "crs": "EPSG:4326", "features": []},
                     {"type": "FeatureCollection", "features": [{"type": "Feature", "geometry": {"type": "LineString", "coordinates": [[1, 2], [3, 4]]}}]}]:
            target.write_text(json.dumps(data))
            with self.assertRaises(ValueError):
                worker.read_geojson(target)
        target.write_text('{"value":NaN}')
        with self.assertRaises(ValueError):
            worker.read_geojson(target)

    def test_xml_rejects_entities_and_external_links(self):
        target = self.path / "bad.kml"
        for data in ['<!DOCTYPE kml [<!ENTITY x SYSTEM "file:///secret">]><kml>&x;</kml>',
                     '<kml xmlns="http://www.opengis.net/kml/2.2"><NetworkLink/></kml>']:
            target.write_text(data)
            with self.assertRaises(ValueError):
                worker.read_kml(target)

    def test_zip_rejects_paths_extra_types_missing_files_and_bombs(self):
        for name, payload in [("../points.shp", b"x"), ("/points.shp", b"x"), ("run.exe", b"x"), ("points.shp", b"0" * 1024 * 1024)]:
            target = self.path / "bad.zip"
            with zipfile.ZipFile(target, "w", zipfile.ZIP_DEFLATED) as archive:
                archive.writestr(name, payload)
                for suffix in ["shx", "dbf", "prj"]:
                    archive.writestr("points." + suffix, b"x")
            with self.assertRaises(ValueError):
                worker.extract_shape(target, self.path)

    def test_limits_and_csv_formula_safety(self):
        with self.assertRaises(ValueError):
            worker.bounded_rows(range(1001))
        self.rows[0]["location_name"] = "=1+1"
        target = self.path / "safe.csv"
        worker.export(self.rows, "csv", target)
        self.assertIn("'=1+1", target.read_text())


if __name__ == "__main__":
    unittest.main()
