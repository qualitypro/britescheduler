ALTER TABLE invoices
ADD UNIQUE KEY uq_invoice_appointment (
    tenant_id,
    appointment_id
);
