/* Warning : This script erase all data in the application Maarch. It keeps in database parameters */

TRUNCATE TABLE listinstance;
ALTER SEQUENCE listinstance_id_seq restart WITH 1;

TRUNCATE TABLE listinstance_history;
ALTER SEQUENCE listinstance_history_id_seq restart WITH 1;

TRUNCATE TABLE listinstance_history_details;
ALTER SEQUENCE listinstance_history_details_id_seq restart WITH 1;

TRUNCATE TABLE history;
ALTER SEQUENCE history_id_seq restart WITH 1;

TRUNCATE TABLE history_batch;
ALTER SEQUENCE history_batch_id_seq restart WITH 1;

TRUNCATE TABLE notes;
ALTER SEQUENCE notes_id_seq restart WITH 1;

TRUNCATE TABLE note_entities;

TRUNCATE TABLE res_letterbox;
ALTER SEQUENCE res_id_mlb_seq restart WITH 1;

TRUNCATE TABLE res_attachments;
ALTER SEQUENCE res_attachment_res_id_seq restart WITH 1;

TRUNCATE TABLE adr_letterbox;
ALTER SEQUENCE adr_letterbox_id_seq restart WITH 1;

TRUNCATE TABLE adr_attachments;
ALTER SEQUENCE adr_attachments_id_seq restart WITH 1;

TRUNCATE TABLE res_mark_as_read;

TRUNCATE TABLE lc_stack;

TRUNCATE TABLE tags;
ALTER SEQUENCE tags_id_seq restart WITH 1;

TRUNCATE TABLE resources_tags;

TRUNCATE TABLE emails;

TRUNCATE TABLE notif_event_stack;
ALTER SEQUENCE notif_event_stack_seq restart WITH 1;

TRUNCATE TABLE notif_email_stack;
ALTER SEQUENCE notif_email_stack_seq restart WITH 1;

TRUNCATE TABLE user_signatures;
ALTER SEQUENCE user_signatures_id_seq restart WITH 1;

TRUNCATE TABLE acknowledgement_receipts;
ALTER SEQUENCE acknowledgement_receipts_id_seq restart WITH 1;

TRUNCATE TABLE emails;
ALTER SEQUENCE emails_id_seq restart WITH 1;

TRUNCATE TABLE registered_mail_resources;
ALTER SEQUENCE registered_mail_resources_id_seq restart WITH 1;

TRUNCATE TABLE resource_contacts;
ALTER SEQUENCE resource_contacts_id_seq restart WITH 1;

TRUNCATE TABLE resources_folders;
ALTER SEQUENCE resources_folders_id_seq restart WITH 1;

TRUNCATE TABLE unit_identifier;

TRUNCATE TABLE users_followed_resources;
ALTER SEQUENCE users_followed_resources_id_seq restart WITH 1;

TRUNCATE TABLE message_exchange;

TRUNCATE TABLE shippings;
ALTER SEQUENCE shippings_id_seq restart WITH 1;


/* reset chrono */
SELECT reset_chronos();