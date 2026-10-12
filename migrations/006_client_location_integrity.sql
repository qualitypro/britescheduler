-- WBS 1.4: Enforce client/tenant integrity.
-- Apply once after migration 005.

ALTER TABLE clients
  ADD INDEX idx_clients_tenant_id (tenant_id, id);

ALTER TABLE client_locations
  ADD CONSTRAINT fk_cl_tenant_client
  FOREIGN KEY (tenant_id, client_id)
  REFERENCES clients (tenant_id, id)
  ON DELETE CASCADE;
