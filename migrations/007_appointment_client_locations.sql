-- WBS 1.5: Saved service locations for appointments.
-- Existing appointments remain valid.

ALTER TABLE appointments
    ADD COLUMN client_location_id BIGINT UNSIGNED NULL
        AFTER client_id,
    ADD INDEX idx_appt_client_location
        (client_location_id),
    ADD CONSTRAINT fk_appt_client_location
        FOREIGN KEY (client_location_id)
        REFERENCES client_locations(id)
        ON DELETE SET NULL;
