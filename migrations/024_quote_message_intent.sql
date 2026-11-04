-- Explicit quote message intent; existing messages remain ordinary replies.
ALTER TABLE contractor_quote_messages
 ADD COLUMN message_type ENUM('reply','request_photos','request_data') NOT NULL DEFAULT 'reply' AFTER sender_user_id;
