CREATE EXTENSION IF NOT EXISTS postgis;

CREATE TABLE app_user (
  id BIGSERIAL PRIMARY KEY,
  email VARCHAR(180) NOT NULL UNIQUE,
  alias VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'citizen'
    CHECK (role IN ('citizen','hero','admin')),
  reliability_score INT NOT NULL DEFAULT 100
    CHECK (reliability_score BETWEEN 0 AND 100),
  is_blocked BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE incident_type (
  id SMALLSERIAL PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  label VARCHAR(50) NOT NULL,
  icon VARCHAR(50) NOT NULL
);

CREATE TABLE district (
  id SERIAL PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  geom geography(MultiPolygon, 4326) NOT NULL
);
CREATE INDEX idx_district_geom ON district USING GIST (geom);

CREATE TABLE incident (
  id BIGSERIAL PRIMARY KEY,
  type_id SMALLINT NOT NULL REFERENCES incident_type(id),
  reporter_id BIGINT NOT NULL REFERENCES app_user(id),
  district_id INT REFERENCES district(id),
  description TEXT,
  severity SMALLINT NOT NULL DEFAULT 2 CHECK (severity BETWEEN 1 AND 4),
  status VARCHAR(20) NOT NULL DEFAULT 'reported'
    CHECK (status IN ('reported','validated','false_alert','resolved')),
  location geography(Point, 4326) NOT NULL,
  altitude_m REAL,
  accuracy_m REAL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  reviewed_by BIGINT REFERENCES app_user(id),
  reviewed_at TIMESTAMPTZ,
  closed_at TIMESTAMPTZ
);
CREATE INDEX idx_incident_location ON incident USING GIST (location);
CREATE INDEX idx_incident_status ON incident (status);
CREATE INDEX idx_incident_type_date ON incident (type_id, created_at);

CREATE TABLE incident_status_history (
  id BIGSERIAL PRIMARY KEY,
  incident_id BIGINT NOT NULL REFERENCES incident(id) ON DELETE CASCADE,
  old_status VARCHAR(20),
  new_status VARCHAR(20) NOT NULL,
  changed_by BIGINT NOT NULL REFERENCES app_user(id),
  reason TEXT,
  changed_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE assignment (
  id BIGSERIAL PRIMARY KEY,
  incident_id BIGINT NOT NULL REFERENCES incident(id) ON DELETE CASCADE,
  hero_id BIGINT NOT NULL REFERENCES app_user(id),
  assigned_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  released_at TIMESTAMPTZ
);
CREATE UNIQUE INDEX uq_assignment_active
  ON assignment (incident_id, hero_id) WHERE released_at IS NULL;

CREATE TABLE false_alert_report (
  incident_id BIGINT NOT NULL REFERENCES incident(id) ON DELETE CASCADE,
  user_id BIGINT NOT NULL REFERENCES app_user(id),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  PRIMARY KEY (incident_id, user_id)
);

INSERT INTO incident_type (code, label, icon) VALUES
  ('incendie', 'Incendie', 'flame'),
  ('vol', 'Vol', 'warning'),
  ('crime', 'Criminalité', 'shield'),
  ('autre', 'Autre', 'plus');
