ALTER TABLE tenants
    ADD COLUMN business_email VARCHAR(190) NULL AFTER currency,
    ADD COLUMN business_phone VARCHAR(40) NULL AFTER business_email,
    ADD COLUMN website VARCHAR(190) NULL AFTER business_phone,
    ADD COLUMN address1 VARCHAR(190) NULL AFTER website,
    ADD COLUMN address2 VARCHAR(190) NULL AFTER address1,
    ADD COLUMN city VARCHAR(100) NULL AFTER address2,
    ADD COLUMN state VARCHAR(100) NULL AFTER city,
    ADD COLUMN postal_code VARCHAR(30) NULL AFTER state,
    ADD COLUMN invoice_footer TEXT NULL AFTER postal_code,
    ADD COLUMN payment_instructions TEXT NULL AFTER invoice_footer;
