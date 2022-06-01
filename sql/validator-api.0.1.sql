-- Initialisation de la base de données
CREATE TABLE validation (
    uid VARCHAR(24) NOT NULL,
    dataset_name VARCHAR(100) NOT NULL,
    arguments JSON DEFAULT NULL,
    date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    status character varying(16) CHECK (status IN ('waiting_for_args','pending','processing','finished','archived','error')),
    message TEXT DEFAULT NULL,
    date_start TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    date_finish TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    results JSON DEFAULT NULL,
    PRIMARY KEY(uid)
);

CREATE INDEX validation_uid_idx ON validation (uid);