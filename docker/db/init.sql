SELECT id, status, ST_AsGeoJSON(location::geometry) AS geojson
FROM incident
WHERE ST_DWithin(
  location,
  ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)::geography,
  :radius_m
)
AND status <> 'false_alert';
