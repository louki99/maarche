------------
-- STRUCTURE 22.xx.0
-- (Launch the application to update structure to this current tag)
------------

SET client_encoding = 'UTF8';
SET standard_conforming_strings = off;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET escape_string_warning = off;

--DROP PROCEDURAL LANGUAGE IF EXISTS plpgsql CASCADE;
--CREATE PROCEDURAL LANGUAGE plpgsql;

SET search_path = public, pg_catalog;
SET default_tablespace = '';
SET default_with_oids = false;

CREATE EXTENSION unaccent;

CREATE TABLE actions
(
  id serial NOT NULL,
  keyword character varying(32) NOT NULL DEFAULT ''::bpchar,
  label_action character varying(255),
  id_status character varying(10),
  is_system character(1) NOT NULL DEFAULT 'N'::bpchar,
  action_page character varying(255),
  component CHARACTER VARYING (128),
  history character(1) NOT NULL DEFAULT 'N'::bpchar,
  parameters jsonb NOT NULL DEFAULT '{}',
  CONSTRAINT actions_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);


CREATE TABLE docserver_types
(
  docserver_type_id character varying(32) NOT NULL,
  docserver_type_label character varying(255) DEFAULT NULL::character varying,
  enabled character(1) NOT NULL DEFAULT 'Y'::bpchar,
  fingerprint_mode character varying(32) DEFAULT NULL::character varying,
  CONSTRAINT docserver_types_pkey PRIMARY KEY (docserver_type_id)
)
WITH (OIDS=FALSE);

CREATE TABLE docservers
(
  id serial,
  docserver_id character varying(32) NOT NULL DEFAULT '1'::character varying,
  docserver_type_id character varying(32) NOT NULL,
  device_label character varying(255) DEFAULT NULL::character varying,
  is_readonly character(1) NOT NULL DEFAULT 'N'::bpchar,
  size_limit_number bigint NOT NULL DEFAULT (0)::bigint,
  actual_size_number bigint NOT NULL DEFAULT (0)::bigint,
  path_template character varying(255) NOT NULL,
  creation_date timestamp without time zone NOT NULL,
  coll_id character varying(32) NOT NULL DEFAULT 'coll_1'::character varying,
  CONSTRAINT docservers_pkey PRIMARY KEY (docserver_id),
  CONSTRAINT docservers_id_key UNIQUE (id)
)
WITH (OIDS=FALSE);

CREATE SEQUENCE doctypes_type_id_seq
  INCREMENT 1
  MINVALUE 1
  MAXVALUE 9223372036854775807
  START 500
  CACHE 1;

CREATE TABLE doctypes
(
  coll_id character varying(32) NOT NULL DEFAULT ''::character varying,
  type_id integer NOT NULL DEFAULT nextval('doctypes_type_id_seq'::regclass),
  description character varying(255) NOT NULL DEFAULT ''::character varying,
  enabled character(1) NOT NULL DEFAULT 'Y'::bpchar,
  doctypes_first_level_id integer,
  doctypes_second_level_id integer,
  retention_final_disposition character varying(255) DEFAULT NULL,
  retention_rule character varying(15) DEFAULT NULL,
  action_current_use character varying(255) DEFAULT NULL,
  duration_current_use integer,
  process_delay INTEGER NOT NULL,
  delay1 INTEGER NOT NULL,
  delay2 INTEGER NOT NULL,
  process_mode CHARACTER VARYING(256) NOT NULL,
  CONSTRAINT doctypes_pkey PRIMARY KEY (type_id)
)
WITH (OIDS=FALSE);

CREATE SEQUENCE history_id_seq
  INCREMENT 1
  MINVALUE 1
  MAXVALUE 9223372036854775807
  START 1
  CACHE 1;

CREATE TABLE history
(
  id bigint NOT NULL DEFAULT nextval('history_id_seq'::regclass),
  table_name character varying(32) DEFAULT NULL::character varying,
  record_id character varying(255) DEFAULT NULL::character varying,
  event_type character varying(32) NOT NULL,
  user_id INTEGER,
  event_date timestamp without time zone NOT NULL,
  info text,
  id_module character varying(50) NOT NULL DEFAULT 'admin'::character varying,
  remote_ip character varying(32) DEFAULT NULL,
  event_id character varying(50),
  CONSTRAINT history_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

CREATE SEQUENCE history_batch_id_seq
  INCREMENT 1
  MINVALUE 1
  MAXVALUE 9223372036854775807
  START 1
  CACHE 1;

CREATE TABLE history_batch
(
  id bigint NOT NULL DEFAULT nextval('history_batch_id_seq'::regclass),
  module_name character varying(32) DEFAULT NULL::character varying,
  batch_id bigint DEFAULT NULL::bigint,
  event_date timestamp without time zone NOT NULL,
  total_processed bigint DEFAULT NULL::bigint,
  total_errors bigint DEFAULT NULL::bigint,
  info text,
  CONSTRAINT history_batch_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

CREATE TABLE parameters
(
  id character varying(255) NOT NULL,
  description TEXT,
  param_value_string TEXT DEFAULT NULL::character varying,
  param_value_int integer,
  param_value_date timestamp without time zone,
  CONSTRAINT parameters_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

CREATE SEQUENCE security_security_id_seq
  INCREMENT 1
  MINVALUE 1
  MAXVALUE 9223372036854775807
  START 600
  CACHE 1;

CREATE TABLE "security"
(
  security_id bigint NOT NULL DEFAULT nextval('security_security_id_seq'::regclass),
  group_id character varying(32) NOT NULL,
  coll_id character varying(32) NOT NULL,
  where_clause text,
  maarch_comment text,
  CONSTRAINT security_pkey PRIMARY KEY (security_id)
)
WITH (OIDS=FALSE);

CREATE TABLE status
(
  identifier serial,
  id character varying(10) NOT NULL,
  label_status character varying(50) NOT NULL,
  is_system character(1) NOT NULL DEFAULT 'Y'::bpchar,
  img_filename character varying(255),
  maarch_module character varying(255) NOT NULL DEFAULT 'apps'::character varying,
  can_be_searched character(1) NOT NULL DEFAULT 'Y'::bpchar,
  can_be_modified character(1) NOT NULL DEFAULT 'Y'::bpchar,
  CONSTRAINT status_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

CREATE TABLE status_images
(
  id serial,
  image_name character varying(128) NOT NULL,
  CONSTRAINT status_images_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

CREATE TABLE usergroup_content
(
  user_id INTEGER NOT NULL,
  group_id INTEGER NOT NULL,
  "role" character varying(255),
  CONSTRAINT usergroup_content_pkey PRIMARY KEY (user_id, group_id)
)
WITH (OIDS=FALSE);

CREATE TABLE usergroups
(
  id serial NOT NULL,
  group_id character varying(32) NOT NULL,
  group_desc character varying(255),
  can_index boolean NOT NULL DEFAULT FALSE,
  indexation_parameters jsonb NOT NULL DEFAULT '{"actions" : [], "entities" : [], "keywords" : []}',
  CONSTRAINT usergroups_pkey PRIMARY KEY (group_id),
  CONSTRAINT usergroups_id_key UNIQUE (id)
)
WITH (OIDS=FALSE);

CREATE TABLE usergroups_services
(
  group_id character varying NOT NULL,
  service_id character varying NOT NULL,
  parameters jsonb,
  CONSTRAINT usergroups_services_pkey PRIMARY KEY (group_id, service_id)
)
WITH (OIDS=FALSE);

CREATE TYPE users_modes AS ENUM ('standard', 'rest', 'root_visible', 'root_invisible');

CREATE TABLE users
(
  id serial NOT NULL,
  user_id character varying(128) NOT NULL,
  "password" character varying(255) DEFAULT NULL::character varying,
  firstname character varying(255) DEFAULT NULL::character varying,
  lastname character varying(255) DEFAULT NULL::character varying,
  phone character varying(32) DEFAULT NULL::character varying,
  mail character varying(255) DEFAULT NULL::character varying,
  initials character varying(32) DEFAULT NULL::character varying,
  preferences jsonb NOT NULL DEFAULT '{"documentEdition" : "java"}',
  status character varying(10) NOT NULL DEFAULT 'OK'::character varying,
  password_modification_date timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
  mode users_modes NOT NULL DEFAULT 'standard',
  refresh_token jsonb NOT NULL DEFAULT '[]',
  reset_token text,
  failed_authentication INTEGER DEFAULT 0,
  locked_until TIMESTAMP without time zone,
  authorized_api jsonb NOT NULL DEFAULT '[]',
  external_id jsonb DEFAULT '{}',
  feature_tour jsonb NOT NULL DEFAULT '[]',
  absence jsonb,
  CONSTRAINT users_pkey PRIMARY KEY (user_id),
  CONSTRAINT users_id_key UNIQUE (id)
)
WITH (OIDS=FALSE);


-- modules/attachments/sql/structure/attachments.postgresql.sql


CREATE SEQUENCE res_attachment_res_id_seq
  INCREMENT 1
  MINVALUE 1
  MAXVALUE 9223372036854775807
  START 1
  CACHE 1;

CREATE TABLE res_attachments
(
  res_id bigint NOT NULL DEFAULT nextval('res_attachment_res_id_seq'::regclass),
  title character varying(255) DEFAULT NULL::character varying,
  format character varying(50) NOT NULL,
  typist INTEGER,
  creation_date timestamp without time zone NOT NULL,
  modification_date timestamp without time zone DEFAULT NOW(),
  modified_by INTEGER,
  identifier character varying(255) DEFAULT NULL::character varying,
  relation bigint,
  docserver_id character varying(32) NOT NULL,
  path character varying(255) DEFAULT NULL::character varying,
  filename character varying(255) DEFAULT NULL::character varying,
  fingerprint character varying(255) DEFAULT NULL::character varying,
  filesize bigint,
  status character varying(10) DEFAULT NULL::character varying,
  validation_date timestamp without time zone,
  effective_date timestamp without time zone,
  work_batch bigint,
  origin character varying(50) DEFAULT NULL::character varying,
  res_id_master bigint,
  origin_id INTEGER,
  attachment_type character varying(255) DEFAULT NULL::character varying,
  recipient_id integer,
  recipient_type character varying(256),
  in_signature_book boolean DEFAULT FALSE,
  in_send_attach boolean DEFAULT FALSE,
  signatory_user_serial_id int,
  fulltext_result character varying(10) DEFAULT NULL::character varying,
  external_id jsonb DEFAULT '{}',
  external_state jsonb DEFAULT '{}',
  CONSTRAINT res_attachments_pkey PRIMARY KEY (res_id)
)
WITH (OIDS=FALSE);

CREATE TABLE adr_attachments
(
  id serial NOT NULL,
  res_id bigint NOT NULL,
  type character varying(32) NOT NULL,
  docserver_id character varying(32) NOT NULL,
  path character varying(255) NOT NULL,
  filename character varying(255) NOT NULL,
  fingerprint character varying(255) DEFAULT NULL::character varying,
  CONSTRAINT adr_attachments_pkey PRIMARY KEY (id),
  CONSTRAINT adr_attachments_unique_key UNIQUE (res_id, type)
)
WITH (OIDS=FALSE);


-- modules/basket/sql/structure/basket.postgresql.sql

CREATE TABLE actions_groupbaskets
(
  id_action bigint NOT NULL,
  where_clause text,
  group_id character varying(32) NOT NULL,
  basket_id character varying(32) NOT NULL,
  used_in_basketlist character(1) NOT NULL DEFAULT 'Y'::bpchar,
  used_in_action_page character(1) NOT NULL DEFAULT 'Y'::bpchar,
  default_action_list character(1) NOT NULL DEFAULT 'N'::bpchar,
  CONSTRAINT actions_groupbaskets_pkey PRIMARY KEY (id_action, group_id, basket_id)
)
WITH (OIDS=FALSE);

CREATE TABLE baskets
(
  id serial NOT NULL,
  coll_id character varying(32) NOT NULL,
  basket_id character varying(32) NOT NULL,
  basket_name character varying(255) NOT NULL,
  basket_desc character varying(255) NOT NULL,
  basket_clause text NOT NULL,
  is_visible character(1) NOT NULL DEFAULT 'Y'::bpchar,
  enabled character(1) NOT NULL DEFAULT 'Y'::bpchar,
  basket_order integer,
  color character varying(16),
  basket_res_order character varying(255) NOT NULL DEFAULT 'res_id desc',
  flag_notif character varying(1),
  CONSTRAINT baskets_pkey PRIMARY KEY (coll_id, basket_id),
  CONSTRAINT baskets_unique_key UNIQUE (id)
)
WITH (OIDS=FALSE);

CREATE TABLE basket_persistent_mode
(
  res_id bigint,
  user_id INTEGER not null,
  is_persistent character varying(1)
)
WITH (
  OIDS=FALSE
);

CREATE TABLE res_mark_as_read
(
  res_id bigint,
  user_id INTEGER NOT NULL,
  basket_id character varying(32)
)
WITH (
  OIDS=FALSE
);

CREATE TABLE groupbasket
(
  id serial NOT NULL,
  group_id character varying(32) NOT NULL,
  basket_id character varying(32) NOT NULL,
  list_display json DEFAULT '[]',
  list_event character varying(255) DEFAULT 'documentDetails' NOT NULL,
  list_event_data jsonb,
  CONSTRAINT groupbasket_pkey PRIMARY KEY (group_id, basket_id),
  CONSTRAINT groupbasket_unique_key UNIQUE (id)
)
WITH (OIDS=FALSE);

CREATE TABLE redirected_baskets
(
id serial NOT NULL,
actual_user_id INTEGER NOT NULL,
owner_user_id INTEGER NOT NULL,
basket_id character varying(255) NOT NULL,
group_id INTEGER NOT NULL,
CONSTRAINT redirected_baskets_pkey PRIMARY KEY (id),
CONSTRAINT redirected_baskets_unique_key UNIQUE (owner_user_id, basket_id, group_id)
)
WITH (OIDS=FALSE);

-- modules/entities/sql/structure/entities.postgresql.sql


CREATE TABLE entities
(
  id serial NOT NULL,
  entity_id character varying(32) NOT NULL,
  entity_label character varying(255),
  short_label character varying(50),
  entity_full_name text,
  enabled character(1) NOT NULL DEFAULT 'Y'::bpchar,
  address_number character varying(255),
  address_street character varying(255),
  address_additional1 character varying(255),
  address_additional2 character varying(256),
  address_postcode character varying(32),
  address_town character varying(255),
  address_country character varying(255),
  email character varying(255),
  business_id character varying(32),
  parent_entity_id character varying(32),
  entity_type character varying(64),
  ldap_id character varying(255),
  producer_service character varying(255),
  folder_import character varying(64),
  external_id jsonb DEFAULT '{}',
  CONSTRAINT entities_pkey PRIMARY KEY (entity_id),
  CONSTRAINT entities_folder_import_unique_key UNIQUE (folder_import)
)
WITH (OIDS=FALSE);


CREATE SEQUENCE listinstance_id_seq
  INCREMENT 1
  MINVALUE 1
  MAXVALUE 9223372036854775807
  START 1
  CACHE 1;

CREATE TABLE listinstance
(
  listinstance_id BIGINT NOT NULL DEFAULT nextval('listinstance_id_seq'::regclass),
  res_id bigint NOT NULL,
  "sequence" bigint NOT NULL,
  item_id INTEGER,
  item_type character varying(255) NOT NULL,
  item_mode character varying(50) NOT NULL,
  added_by_user INTEGER,
  viewed bigint,
  difflist_type character varying(50),
  process_date timestamp without time zone,
  process_comment character varying(255),
  signatory boolean default false,
  requested_signature boolean default false,
  delegate INTEGER,
  CONSTRAINT listinstance_pkey PRIMARY KEY (listinstance_id)
)
WITH (OIDS=FALSE);

CREATE TABLE difflist_types
(
  difflist_type_id character varying(50) NOT NULL,
  difflist_type_label character varying(100) NOT NULL,
  difflist_type_roles TEXT,
  allow_entities character varying(1) NOT NULL DEFAULT 'N'::bpchar,
  is_system character varying(1) NOT NULL DEFAULT 'N'::bpchar,
  CONSTRAINT "difflist_types_pkey" PRIMARY KEY (difflist_type_id)
)
WITH (
    OIDS=FALSE
);

CREATE TABLE users_entities
(
  user_id INTEGER NOT NULL,
  entity_id character varying(32) NOT NULL,
  user_role character varying(255),
  primary_entity character(1) NOT NULL DEFAULT 'N'::bpchar,
  CONSTRAINT users_entities_pkey PRIMARY KEY (user_id, entity_id)
)
WITH (OIDS=FALSE);

CREATE SEQUENCE groupbasket_redirect_system_id_seq
  INCREMENT 1
  MINVALUE 1
  MAXVALUE 9223372036854775807
  START 600
  CACHE 1;

CREATE TABLE groupbasket_redirect
(
  system_id integer NOT NULL DEFAULT nextval('groupbasket_redirect_system_id_seq'::regclass),
  group_id character varying(32) NOT NULL,
  basket_id character varying(32) NOT NULL,
  action_id int NOT NULL,
  entity_id character varying(32),
  keyword character varying(255),
  redirect_mode character varying(32) NOT NULL,
  CONSTRAINT groupbasket_redirect_pkey PRIMARY KEY (system_id)
)
WITH (OIDS=FALSE);

CREATE TABLE users_email_signatures
(
  id serial NOT NULL,
  user_id INTEGER NOT NULL,
  html_body text NOT NULL,
  title character varying NOT NULL,
  CONSTRAINT email_signatures_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

/* FOLDERS */
CREATE TABLE folders
(
  id serial NOT NULL,
  label character varying(255) NOT NULL,
  public boolean NOT NULL,
  user_id INTEGER NOT NULL,
  parent_id INTEGER,
  level INTEGER NOT NULL,
  CONSTRAINT folders_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

CREATE TABLE resources_folders
(
  id serial NOT NULL,
  folder_id INTEGER NOT NULL,
  res_id INTEGER NOT NULL,
  CONSTRAINT resources_folders_pkey PRIMARY KEY (id),
  CONSTRAINT resources_folders_unique_key UNIQUE (folder_id, res_id)
)
WITH (OIDS=FALSE);

CREATE TABLE entities_folders
(
  id serial NOT NULL,
  folder_id INTEGER NOT NULL,
  entity_id INTEGER,
  edition boolean NOT NULL,
  keyword character varying(255),
  CONSTRAINT entities_folders_pkey PRIMARY KEY (id),
  CONSTRAINT entities_folders_unique_key UNIQUE (folder_id, entity_id, keyword)
)
WITH (OIDS=FALSE);

CREATE TABLE users_pinned_folders
(
  id serial NOT NULL,
  folder_id INTEGER NOT NULL,
  user_id INTEGER NOT NULL,
  CONSTRAINT users_pinned_folders_pkey PRIMARY KEY (id),
  CONSTRAINT users_pinned_folders_unique_key UNIQUE (folder_id, user_id)
)
WITH (OIDS=FALSE);

-- modules/life_cycle/sql/structure/life_cycle.postgresql.sql

CREATE TABLE lc_policies
(
   policy_id character varying(32) NOT NULL,
   policy_name character varying(255) NOT NULL,
   policy_desc character varying(255) NOT NULL,
   CONSTRAINT lc_policies_pkey PRIMARY KEY (policy_id)
)
WITH (OIDS = FALSE);


CREATE TABLE lc_cycles
(
   policy_id character varying(32) NOT NULL,
   cycle_id character varying(32) NOT NULL,
   cycle_desc character varying(255) NOT NULL,
   sequence_number integer NOT NULL,
   where_clause text,
   break_key character varying(255) DEFAULT NULL,
   validation_mode character varying(32) NOT NULL,
   CONSTRAINT lc_cycle_pkey PRIMARY KEY (policy_id, cycle_id)
)
WITH (OIDS = FALSE);

CREATE TABLE lc_cycle_steps
(
   policy_id character varying(32) NOT NULL,
   cycle_id character varying(32) NOT NULL,
   cycle_step_id character varying(32) NOT NULL,
   cycle_step_desc character varying(255) NOT NULL,
   docserver_type_id character varying(32) NOT NULL,
   is_allow_failure character(1) NOT NULL DEFAULT 'N'::bpchar,
   step_operation character varying(32) NOT NULL,
   sequence_number integer NOT NULL,
   is_must_complete character(1) NOT NULL DEFAULT 'N'::bpchar,
   preprocess_script character varying(255) DEFAULT NULL,
   postprocess_script character varying(255) DEFAULT NULL,
   CONSTRAINT lc_cycle_steps_pkey PRIMARY KEY (policy_id, cycle_id, cycle_step_id, docserver_type_id)
)
WITH (OIDS = FALSE);

CREATE TABLE lc_stack
(
   policy_id character varying(32) NOT NULL,
   cycle_id character varying(32) NOT NULL,
   cycle_step_id character varying(32) NOT NULL,
   coll_id character varying(32) NOT NULL,
   res_id bigint NOT NULL,
   cnt_retry integer DEFAULT NULL,
   status character(1) NOT NULL,
   work_batch bigint,
   regex character varying(32),
   CONSTRAINT lc_stack_pkey PRIMARY KEY (policy_id, cycle_id, cycle_step_id, res_id)
)
WITH (OIDS = FALSE);

CREATE TABLE notes
(
  id serial,
  identifier bigint NOT NULL,
  user_id bigint NOT NULL,
  creation_date timestamp without time zone NOT NULL,
  note_text text NOT NULL,
  CONSTRAINT notes_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

CREATE TABLE blacklist
(
  id SERIAL PRIMARY KEY,
  term CHARACTER VARYING(128) UNIQUE NOT NULL
)
WITH (OIDS=FALSE);

CREATE VIEW bad_notes AS
  SELECT *
  FROM notes
  WHERE unaccent(note_text) ~* concat('\m(', array_to_string(array((select unaccent(term) from blacklist)), '|', ''), ')\M');

CREATE SEQUENCE notes_entities_id_seq
  INCREMENT 1
  MINVALUE 1
  MAXVALUE 9223372036854775807
  START 20
  CACHE 1;


CREATE TABLE note_entities
(
  id bigint NOT NULL DEFAULT nextval('notes_entities_id_seq'::regclass),
  note_id bigint NOT NULL,
  item_id character varying(50),
  CONSTRAINT note_entities_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);



-- modules/notes/sql/structure/notifications.postgresql.sql
CREATE SEQUENCE notifications_seq
  INCREMENT 1
  MINVALUE 1
  MAXVALUE 9223372036854775807
  START 100
  CACHE 1;

CREATE TABLE notifications
(
  notification_sid bigint NOT NULL DEFAULT nextval('notifications_seq'::regclass),
  notification_id character varying(50) NOT NULL,
  description character varying(255),
  is_enabled character varying(1) NOT NULL default 'Y'::bpchar,
  event_id character varying(255) NOT NULL,
  notification_mode character varying(30) NOT NULL,
  template_id bigint,
  diffusion_type character varying(50) NOT NULL,
  diffusion_properties text,
  attachfor_type character varying(50),
  attachfor_properties character varying(2048),
  CONSTRAINT notifications_pkey PRIMARY KEY (notification_sid)
)
WITH (
  OIDS=FALSE
);


CREATE SEQUENCE notif_event_stack_seq
  INCREMENT 1
  MINVALUE 1
  MAXVALUE 9223372036854775807
  START 1
  CACHE 1;

 -- DROP TABLE notif_event_stack
CREATE TABLE notif_event_stack
(
  event_stack_sid bigint NOT NULL DEFAULT nextval('notif_event_stack_seq'::regclass),
  notification_sid bigint NOT NULL,
  table_name character varying(50) NOT NULL,
  record_id character varying(128) NOT NULL,
  user_id integer NOT NULL,
  event_info character varying(255) NOT NULL,
  event_date timestamp without time zone NOT NULL,
  exec_date timestamp without time zone,
  exec_result character varying(50),
  CONSTRAINT notif_event_stack_pkey PRIMARY KEY (event_stack_sid)
)
WITH (
  OIDS=FALSE
);

CREATE SEQUENCE notif_email_stack_seq
  INCREMENT 1
  MINVALUE 1
  MAXVALUE 9223372036854775807
  START 1
  CACHE 1;

 -- DROP TABLE notif_email_stack
CREATE TABLE notif_email_stack
(
  email_stack_sid bigint NOT NULL DEFAULT nextval('notif_email_stack_seq'::regclass),
  reply_to character varying(255),
  recipient text NOT NULL,
  cc text,
  bcc text,
  subject character varying(255),
  html_body text,
  attachments text,
  exec_date timestamp without time zone,
  exec_result character varying(50),
  CONSTRAINT notif_email_stack_pkey PRIMARY KEY (email_stack_sid)
)
WITH (
  OIDS=FALSE
);

-- modules/templates/sql/structure/templates.postgresql.sql

CREATE SEQUENCE templates_seq
  INCREMENT 1
  MINVALUE 1
  MAXVALUE 9223372036854775807
  START 110
  CACHE 1;

CREATE TABLE templates
(
  template_id bigint NOT NULL DEFAULT nextval('templates_seq'::regclass),
  template_label character varying(255) DEFAULT NULL::character varying,
  template_comment character varying(255) DEFAULT NULL::character varying,
  template_content text,
  template_type character varying(32) NOT NULL DEFAULT 'HTML'::character varying,
  template_path character varying(255),
  template_file_name character varying(255),
  template_style character varying(255),
  template_datasource character varying(32),
  template_target character varying(255),
  template_attachment_type character varying(255) DEFAULT NULL::character varying,
  subject character varying(255),
  options jsonb DEFAULT '{}',
  CONSTRAINT templates_pkey PRIMARY KEY (template_id)
)
WITH (OIDS=FALSE);

CREATE TABLE templates_association
(
  id serial,
  template_id bigint NOT NULL,
  value_field character varying(255) NOT NULL,
  CONSTRAINT templates_association_pkey PRIMARY KEY (id)
)
WITH (
  OIDS=FALSE
);

CREATE TABLE contacts
(
    id SERIAL NOT NULL,
    civility INTEGER,
    firstname CHARACTER VARYING(256),
    lastname CHARACTER VARYING(256),
    company CHARACTER VARYING(256),
    department CHARACTER VARYING(256),
    function CHARACTER VARYING(256),
    address_number CHARACTER VARYING(256),
    address_street CHARACTER VARYING(256),
    address_additional1 CHARACTER VARYING(256),
    address_additional2 CHARACTER VARYING(256),
    address_postcode CHARACTER VARYING(256),
    address_town CHARACTER VARYING(256),
    address_country CHARACTER VARYING(256),
    email CHARACTER VARYING(256),
    phone CHARACTER VARYING(256),
    communication_means jsonb,
    notes text,
    creator INTEGER NOT NULL,
    creation_date TIMESTAMP without time zone NOT NULL DEFAULT NOW(),
    modification_date TIMESTAMP without time zone,
    enabled boolean NOT NULL DEFAULT TRUE,
    custom_fields jsonb DEFAULT '{}',
    external_id jsonb DEFAULT '{}',
    sector CHARACTER VARYING(256),
    CONSTRAINT contacts_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

CREATE TABLE contacts_parameters
(
    id SERIAL NOT NULL,
    identifier text NOT NULL,
    mandatory boolean NOT NULL DEFAULT FALSE,
    filling boolean NOT NULL DEFAULT FALSE,
    searchable boolean NOT NULL DEFAULT FALSE,
    displayable boolean NOT NULL DEFAULT FALSE,
    CONSTRAINT contacts_parameters_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

CREATE TABLE contacts_groups
(
  id serial,
  label text NOT NULL,
  description text NOT NULL,
  owner integer NOT NULL,
  entities jsonb NOT NULL DEFAULT '{}',
  CONSTRAINT contacts_groups_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

CREATE TABLE contacts_groups_lists
(
  id serial,
  contacts_groups_id integer NOT NULL,
  correspondent_id integer NOT NULL,
  correspondent_type CHARACTER VARYING(256) NOT NULL,
  CONSTRAINT contacts_groups_lists_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

CREATE TABLE contacts_civilities
(
    id SERIAL NOT NULL,
    label text NOT NULL,
    abbreviation CHARACTER VARYING(16) NOT NULL,
    CONSTRAINT contacts_civilities_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

CREATE TABLE search_templates (
  id serial,
  user_id integer NOT NULL,
  label character varying(255) NOT NULL,
  creation_date timestamp without time zone NOT NULL,
  query json NOT NULL,
  CONSTRAINT search_templates_pkey PRIMARY KEY (id)
) WITH (OIDS=FALSE);

CREATE SEQUENCE doctypes_first_level_id_seq
  INCREMENT 1
  MINVALUE 1
  MAXVALUE 9223372036854775807
  START 200
  CACHE 1;

CREATE TABLE doctypes_first_level
(
  doctypes_first_level_id integer NOT NULL DEFAULT nextval('doctypes_first_level_id_seq'::regclass),
  doctypes_first_level_label character varying(255) NOT NULL,
  css_style character varying(255),
  enabled character(1) NOT NULL DEFAULT 'Y'::bpchar,
  CONSTRAINT doctypes_first_level_pkey PRIMARY KEY (doctypes_first_level_id)
)
WITH (OIDS=FALSE);

CREATE SEQUENCE doctypes_second_level_id_seq
  INCREMENT 1
  MINVALUE 1
  MAXVALUE 9223372036854775807
  START 200
  CACHE 1;

CREATE TABLE doctypes_second_level
(
  doctypes_second_level_id integer NOT NULL DEFAULT nextval('doctypes_second_level_id_seq'::regclass),
  doctypes_second_level_label character varying(255) NOT NULL,
  doctypes_first_level_id integer NOT NULL,
  css_style character varying(255),
  enabled character(1) NOT NULL DEFAULT 'Y'::bpchar,
  CONSTRAINT doctypes_second_level_pkey PRIMARY KEY (doctypes_second_level_id)
)
WITH (OIDS=FALSE);

CREATE TABLE tags
(
  id serial NOT NULL,
  label character varying(128) NOT NULL,
  description text,
  parent_id INT,
  creation_date timestamp DEFAULT NOW(),
  links jsonb  DEFAULT '[]',
  usage text,
  CONSTRAINT tags_id_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

CREATE TABLE resources_tags
(
    id SERIAL NOT NULL,
    res_id INT NOT NULL,
    tag_id INT NOT NULL,
    CONSTRAINT resources_tags_id_pkey PRIMARY KEY (id),
    CONSTRAINT resources_tags_unique_key UNIQUE (res_id, tag_id)
)
WITH (OIDS=FALSE);

CREATE SEQUENCE res_id_mlb_seq
  INCREMENT 1
  MINVALUE 1
  MAXVALUE 9223372036854775807
  START 100
  CACHE 1;

CREATE TABLE res_letterbox
(
  res_id bigint NOT NULL DEFAULT nextval('res_id_mlb_seq'::regclass),
  subject text,
  type_id bigint NOT NULL,
  format character varying(50),
  typist INTEGER,
  creation_date timestamp without time zone NOT NULL,
  modification_date timestamp without time zone DEFAULT NOW(),
  doc_date timestamp without time zone,
  docserver_id character varying(32),
  path character varying(255) DEFAULT NULL::character varying,
  filename character varying(255) DEFAULT NULL::character varying,
  fingerprint character varying(255) DEFAULT NULL::character varying,
  filesize bigint,
  status character varying(10),
  destination character varying(50) DEFAULT NULL::character varying,
  work_batch bigint,
  origin character varying(50) DEFAULT NULL::character varying,
  priority character varying(16),
  policy_id character varying(32) DEFAULT NULL::character varying,
  cycle_id character varying(32) DEFAULT NULL::character varying,
  initiator character varying(50) DEFAULT NULL::character varying,
  dest_user INTEGER,
  locker_user_id INTEGER DEFAULT NULL,
  locker_time timestamp without time zone,
  confidentiality character(1),
  fulltext_result character varying(10) DEFAULT NULL::character varying,
  external_id jsonb DEFAULT '{}',
  external_state jsonb DEFAULT '{}',
  departure_date timestamp without time zone,
  opinion_limit_date timestamp without time zone default NULL,
  barcode text,
  category_id character varying(32)  NOT NULL,
  alt_identifier character varying(255),
  admission_date timestamp without time zone,
  process_limit_date timestamp without time zone,
  closing_date timestamp without time zone,
  alarm1_date timestamp without time zone,
  alarm2_date timestamp without time zone,
  flag_alarm1 char(1) default 'N'::character varying,
  flag_alarm2 char(1) default 'N'::character varying,
  model_id INTEGER NOT NULL,
  version INTEGER NOT NULL,
  integrations jsonb DEFAULT '{}' NOT NULL,
  custom_fields jsonb,
  linked_resources jsonb NOT NULL DEFAULT '[]',
  retention_frozen boolean DEFAULT FALSE NOT NULL,
  binding boolean,
  CONSTRAINT res_letterbox_pkey PRIMARY KEY  (res_id)
)
WITH (OIDS=FALSE);

CREATE TABLE adr_letterbox
(
  id serial NOT NULL,
  res_id bigint NOT NULL,
  type character varying(32) NOT NULL,
  version INTEGER NOT NULL,
  docserver_id character varying(32) NOT NULL,
  path character varying(255) NOT NULL,
  filename character varying(255) NOT NULL,
  fingerprint character varying(255) DEFAULT NULL,
  CONSTRAINT adr_letterbox_pkey PRIMARY KEY (id),
  CONSTRAINT adr_letterbox_unique_key UNIQUE (res_id, type, version)
)
WITH (OIDS=FALSE);

CREATE TABLE doctypes_indexes
(
  type_id bigint NOT NULL,
  coll_id character varying(32) NOT NULL,
  field_name character varying(255) NOT NULL,
  mandatory character(1) NOT NULL DEFAULT 'N'::bpchar,
  CONSTRAINT doctypes_indexes_pkey PRIMARY KEY (type_id, coll_id, field_name)
)
WITH (OIDS=FALSE);

CREATE TABLE user_signatures
(
  id serial,
  user_serial_id integer NOT NULL,
  signature_label character varying(255) DEFAULT NULL::character varying,
  signature_path character varying(255) DEFAULT NULL::character varying,
  signature_file_name character varying(255) DEFAULT NULL::character varying,
  fingerprint character varying(255) DEFAULT NULL::character varying,
  CONSTRAINT user_signatures_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

CREATE TABLE priorities
(
  id character varying(16) NOT NULL,
  label character varying(128) NOT NULL,
  color character varying(128) NOT NULL,
  delays integer NOT NULL,
  "order" integer,
  CONSTRAINT priorities_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

DROP TABLE IF EXISTS actions_categories;
CREATE TABLE actions_categories
(
  action_id bigint NOT NULL,
  category_id character varying(255) NOT NULL,
  CONSTRAINT actions_categories_pkey PRIMARY KEY (action_id,category_id)
);

DROP SEQUENCE IF EXISTS listinstance_history_id_seq;
CREATE SEQUENCE listinstance_history_id_seq
INCREMENT 1
MINVALUE 1
MAXVALUE 9223372036854775807
START 1
CACHE 1;

DROP TABLE IF EXISTS listinstance_history;
CREATE TABLE listinstance_history
(
listinstance_history_id bigint NOT NULL DEFAULT nextval('listinstance_history_id_seq'::regclass),
coll_id character varying(50) NOT NULL,
res_id bigint NOT NULL,
user_id INTEGER NOT NULL,
updated_date timestamp without time zone NOT NULL,
CONSTRAINT listinstance_history_pkey PRIMARY KEY (listinstance_history_id)
)
WITH ( OIDS=FALSE );

DROP SEQUENCE IF EXISTS listinstance_history_details_id_seq;
CREATE SEQUENCE listinstance_history_details_id_seq
INCREMENT 1
MINVALUE 1
MAXVALUE 9223372036854775807
START 1
CACHE 1;

DROP TABLE IF EXISTS listinstance_history_details;
CREATE TABLE listinstance_history_details
(
listinstance_history_details_id bigint NOT NULL DEFAULT nextval('listinstance_history_details_id_seq'::regclass),
listinstance_history_id bigint NOT NULL,
coll_id character varying(50) NOT NULL,
res_id bigint NOT NULL,
listinstance_type character varying(50) DEFAULT 'DOC'::character varying,
sequence bigint NOT NULL,
item_id INTEGER,
item_type character varying(255) NOT NULL,
item_mode character varying(50) NOT NULL,
added_by_user INTEGER,
visible character varying(1) NOT NULL DEFAULT 'Y'::bpchar,
viewed bigint,
difflist_type character varying(50),
process_date timestamp without time zone,
process_comment character varying(255),
requested_signature boolean default false,
signatory boolean default false,
CONSTRAINT listinstance_history_details_pkey PRIMARY KEY (listinstance_history_details_id)
) WITH ( OIDS=FALSE );

/* SHIPPING TEMPLATES */
DROP TABLE IF EXISTS shipping_templates;
CREATE TABLE shipping_templates
(
id serial NOT NULL,
label character varying(64) NOT NULL,
description character varying(255) NOT NULL,
options json DEFAULT '{}',
fee json DEFAULT '{}',
entities jsonb DEFAULT '{}',
account jsonb DEFAULT '{}',
subscriptions jsonb DEFAULT '[]',
token_min_iat TIMESTAMP WITHOUT TIME ZONE DEFAULT now(),
CONSTRAINT shipping_templates_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

CREATE TABLE shippings
(
id serial NOT NULL,
user_id INTEGER NOT NULL,
document_id INTEGER NOT NULL,
document_type character varying(255) NOT NULL,
options json DEFAULT '{}',
fee FLOAT NOT NULL,
recipient_entity_id INTEGER NOT NULL,
recipients jsonb DEFAULT '[]',
account_id character varying(64) NOT NULL,
creation_date timestamp without time zone NOT NULL,
history jsonb DEFAULT '[]',
attachments jsonb DEFAULT '[]',
sending_id character varying(64),
action_id INTEGER,
CONSTRAINT shippings_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

--VIEWS
-- view for letterbox
CREATE OR REPLACE VIEW res_view_letterbox AS
SELECT r.res_id,
       r.type_id,
       r.policy_id,
       r.cycle_id,
       d.description AS type_label,
       d.doctypes_first_level_id,
       dfl.doctypes_first_level_label,
       dfl.css_style AS doctype_first_level_style,
       d.doctypes_second_level_id,
       dsl.doctypes_second_level_label,
       dsl.css_style AS doctype_second_level_style,
       r.format,
       r.typist,
       r.creation_date,
       r.modification_date,
       r.docserver_id,
       r.path,
       r.filename,
       r.fingerprint,
       r.filesize,
       r.status,
       r.work_batch,
       r.doc_date,
       r.external_id,
       r.departure_date,
       r.opinion_limit_date,
       r.barcode,
       r.initiator,
       r.destination,
       r.dest_user,
       r.confidentiality,
       r.category_id,
       r.alt_identifier,
       r.admission_date,
       r.process_limit_date,
       r.closing_date,
       r.alarm1_date,
       r.alarm2_date,
       r.flag_alarm1,
       r.flag_alarm2,
       r.subject,
       r.priority,
       r.locker_user_id,
       r.locker_time,
       r.custom_fields,
       r.retention_frozen,
       r.binding,
       en.entity_label,
       en.entity_type AS entitytype
FROM res_letterbox r
         LEFT JOIN doctypes d ON r.type_id = d.type_id
         LEFT JOIN doctypes_first_level dfl ON d.doctypes_first_level_id = dfl.doctypes_first_level_id
         LEFT JOIN doctypes_second_level dsl ON d.doctypes_second_level_id = dsl.doctypes_second_level_id
         LEFT JOIN entities en ON r.destination::TEXT = en.entity_id::TEXT
;

/* ORDER ON CHRONO */
CREATE OR REPLACE FUNCTION order_alphanum(text) RETURNS text AS $$
declare
    tmp text;
begin
    tmp := $1;
    tmp := tmp || 'Z';
    tmp := regexp_replace(tmp, E'(\\D)', E'\\1/', 'g');

    IF count(regexp_match(tmp, E'(\\D(\\d{8})\\D)')) > 0 THEN
        tmp := regexp_replace(tmp, E'(\\D)(\\d{8}\\D)', E'\\10\\2', 'g');
    END IF;
    IF count(regexp_match(tmp, E'(\\D)(\\d{7}\\D)')) > 0 THEN
        tmp := regexp_replace(tmp, E'(\\D)(\\d{7}\\D)', E'\\100\\2', 'g');
    END IF;
    IF count(regexp_match(tmp, E'(\\D)(\\d{6}\\D)')) > 0 THEN
        tmp := regexp_replace(tmp, E'(\\D)(\\d{6}\\D)', E'\\1000\\2', 'g');
    END IF;
    IF count(regexp_match(tmp, E'(\\D)(\\d{5}\\D)')) > 0 THEN
        tmp := regexp_replace(tmp, E'(\\D)(\\d{5}\\D)', E'\\10000\\2', 'g');
    END IF;
    IF count(regexp_match(tmp, E'(\\D)(\\d{4}\\D)')) > 0 THEN
        tmp := regexp_replace(tmp, E'(\\D)(\\d{4}\\D)', E'\\100000\\2', 'g');
    END IF;
    IF count(regexp_match(tmp, E'(\\D)(\\d{3}\\D)')) > 0 THEN
        tmp := regexp_replace(tmp, E'(\\D)(\\d{3}\\D)', E'\\1000000\\2', 'g');
    END IF;
    IF count(regexp_match(tmp, E'(\\D)(\\d{2}\\D)')) > 0 THEN
        tmp := regexp_replace(tmp, E'(\\D)(\\d{2}\\D)', E'\\10000000\\2', 'g');
    END IF;
    IF count(regexp_match(tmp, E'(\\D)(\\d{1}\\D)')) > 0 THEN
        tmp := regexp_replace(tmp, E'(\\D)(\\d{1}\\D)', E'\\100000000\\2', 'g');
    END IF;

    RETURN tmp;
end;
$$ LANGUAGE plpgsql;


CREATE TABLE message_exchange
(
  message_id text NOT NULL,
  schema text,
  type text NOT NULL,
  status text NOT NULL,

  date timestamp NOT NULL,
  reference text NOT NULL,

  account_id integer,
  sender_org_identifier text NOT NULL,
  sender_org_name text,
  recipient_org_identifier text NOT NULL,
  recipient_org_name text,

  archival_agreement_reference text,
  reply_code text,
  operation_date timestamp,
  reception_date timestamp,

  related_reference text,
  request_reference text,
  reply_reference text,
  derogation boolean,

  data_object_count integer,
  size numeric,

  data text,

  active boolean,
  archived boolean,

  res_id_master numeric default NULL,

  docserver_id character varying(32) DEFAULT NULL,
  path character varying(255) DEFAULT NULL,
  filename character varying(255) DEFAULT NULL,
  fingerprint character varying(255) DEFAULT NULL,
  filesize bigint,
  file_path text default NULL,

  PRIMARY KEY ("message_id")
)
WITH (
  OIDS=FALSE
);

CREATE TABLE unit_identifier
(
  message_id text NOT NULL,
  tablename text NOT NULL,
  res_id text NOT NULL,
  disposition text default NULL
);

DROP TABLE IF EXISTS users_baskets_preferences;
CREATE TABLE users_baskets_preferences
(
  id serial NOT NULL,
  user_serial_id integer NOT NULL,
  group_serial_id integer NOT NULL,
  basket_id character varying(32) NOT NULL,
  display boolean NOT NULL,
  color character varying(16),
  CONSTRAINT users_baskets_preferences_pkey PRIMARY KEY (id),
  CONSTRAINT users_baskets_preferences_key UNIQUE (user_serial_id, group_serial_id, basket_id)
)
WITH (OIDS=FALSE);


-- convert working table
DROP TABLE IF EXISTS convert_stack;
CREATE TABLE convert_stack
(
  coll_id character varying(32) NOT NULL,
  res_id bigint NOT NULL,
  convert_format character varying(32) NOT NULL DEFAULT 'pdf'::character varying,
  cnt_retry integer,
  status character(1) NOT NULL,
  work_batch bigint,
  regex character varying(32),
  CONSTRAINT convert_stack_pkey PRIMARY KEY (coll_id, res_id, convert_format)
)
WITH (OIDS=FALSE);

CREATE TABLE password_rules
(
  id serial,
  label character varying(64) NOT NULL,
  "value" integer NOT NULL,
  enabled boolean DEFAULT FALSE NOT NULL,
  CONSTRAINT password_rules_pkey PRIMARY KEY (id),
  CONSTRAINT password_rules_label_key UNIQUE (label)
)
WITH (OIDS=FALSE);

CREATE TABLE password_history
(
  id serial,
  user_serial_id INTEGER NOT NULL,
  password character varying(255) NOT NULL,
  CONSTRAINT password_history_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

CREATE TABLE contacts_filling
(
  id serial NOT NULL,
  enable boolean NOT NULL,
  first_threshold int NOT NULL,
  second_threshold int NOT NULL,
  CONSTRAINT contacts_filling_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

/* Sender/Recipient */
DROP TABLE IF EXISTS resource_contacts;
CREATE TABLE resource_contacts
(
  id serial NOT NULL,
  res_id int NOT NULL,
  item_id int NOT NULL,
  type character varying(32) NOT NULL,
  mode character varying(32) NOT NULL,
  CONSTRAINT resource_contacts_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

CREATE TABLE configurations
(
id serial NOT NULL,
privilege character varying(64) NOT NULL,
value jsonb DEFAULT '{}' NOT NULL,
CONSTRAINT configuration_pkey PRIMARY KEY (id),
CONSTRAINT configuration_unique_key UNIQUE (privilege)
)
WITH (OIDS=FALSE);

CREATE TABLE emails
(
id serial NOT NULL,
user_id INTEGER NOT NULL,
sender json DEFAULT '{}' NOT NULL,
recipients json DEFAULT '[]' NOT NULL,
cc json DEFAULT '[]' NOT NULL,
cci json DEFAULT '[]' NOT NULL,
object character varying(256),
body text,
document json,
is_html boolean NOT NULL DEFAULT TRUE,
status character varying(16) NOT NULL,
message_exchange_id text,
creation_date timestamp without time zone NOT NULL,
send_date timestamp without time zone,
CONSTRAINT emails_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

CREATE TABLE exports_templates
(
id serial NOT NULL,
user_id INTEGER NOT NULL,
delimiter character varying(3),
format character varying(3) NOT NULL,
data json DEFAULT '[]' NOT NULL,
CONSTRAINT exports_templates_pkey PRIMARY KEY (id),
CONSTRAINT exports_templates_unique_key UNIQUE (user_id, format)
)
WITH (OIDS=FALSE);

CREATE TABLE acknowledgement_receipts
(
    id serial NOT NULL,
    res_id INTEGER NOT NULL,
    type CHARACTER VARYING(16) NOT NULL,
    format CHARACTER VARYING(8) NOT NULL,
    user_id INTEGER NOT NULL,
    contact_id INTEGER NOT NULL,
    creation_date timestamp without time zone NOT NULL,
    send_date timestamp without time zone,
    docserver_id CHARACTER VARYING(128) NOT NULL,
    path CHARACTER VARYING(256) NOT NULL,
    filename CHARACTER VARYING(256) NOT NULL,
    fingerprint CHARACTER VARYING(256) NOT NULL,
    cc jsonb DEFAULT '[]',
    cci jsonb DEFAULT '[]',
    CONSTRAINT acknowledgement_receipts_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

CREATE TYPE custom_fields_modes AS ENUM ('form', 'technical');

CREATE TABLE custom_fields
(
    id serial NOT NULL,
    label character varying(256) NOT NULL,
    type character varying(256) NOT NULL,
    mode custom_fields_modes NOT NULL DEFAULT 'form',
    values jsonb,
    CONSTRAINT custom_fields_pkey PRIMARY KEY (id),
    CONSTRAINT custom_fields_unique_key UNIQUE (label)
)
WITH (OIDS=FALSE);

CREATE TABLE indexing_models
(
    id SERIAL NOT NULL,
    label character varying(256) NOT NULL,
    category character varying(256) NOT NULL,
    "default" BOOLEAN NOT NULL,
    owner INTEGER NOT NULL,
    private BOOLEAN NOT NULL,
    master INTEGER DEFAULT NULL,
    enabled BOOLEAN DEFAULT TRUE NOT NULL,
    mandatory_file BOOLEAN DEFAULT FALSE NOT NULL,
    CONSTRAINT indexing_models_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

CREATE TABLE indexing_models_fields
(
    id SERIAL NOT NULL,
    model_id INTEGER NOT NULL,
    identifier text NOT NULL,
    mandatory BOOLEAN NOT NULL,
    enabled BOOLEAN DEFAULT TRUE NOT NULL,
    default_value json,
    unit text NOT NULL,
    allowed_values jsonb,
    CONSTRAINT indexing_models_fields_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

CREATE TABLE contacts_custom_fields_list
(
    id serial NOT NULL,
    label character varying(256) NOT NULL,
    type character varying(256) NOT NULL,
    values jsonb,
    CONSTRAINT contacts_custom_fields_list_pkey PRIMARY KEY (id),
    CONSTRAINT contacts_custom_fields_list_unique_key UNIQUE (label)
)
WITH (OIDS=FALSE);

CREATE TABLE list_templates
(
    id SERIAL NOT NULL,
    title text NOT NULL,
    description text,
    type CHARACTER VARYING(32) NOT NULL,
    entity_id INTEGER,
    owner INTEGER DEFAULT NULL,
    CONSTRAINT list_templates_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

CREATE TABLE list_templates_items
(
    id SERIAL NOT NULL,
    list_template_id INTEGER NOT NULL,
    item_id INTEGER NOT NULL,
    item_type CHARACTER VARYING(32) NOT NULL,
    item_mode CHARACTER VARYING(64) NOT NULL,
    sequence INTEGER NOT NULL,
    CONSTRAINT list_templates_items_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

/* users followed resources */
CREATE TABLE users_followed_resources
(
    id serial NOT NULL,
    res_id int NOT NULL,
    user_id int NOT NULL,
    CONSTRAINT users_followed_resources_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

CREATE TABLE IF NOT EXISTS registered_mail_issuing_sites
(
    id                  SERIAL                 NOT NULL,
    label               CHARACTER VARYING(256) NOT NULL,
    post_office_label   CHARACTER VARYING(256),
    account_number      INTEGER,
    address_number      CHARACTER VARYING(256) NOT NULL,
    address_street      CHARACTER VARYING(256) NOT NULL,
    address_additional1 CHARACTER VARYING(256),
    address_additional2 CHARACTER VARYING(256),
    address_postcode    CHARACTER VARYING(256) NOT NULL,
    address_town        CHARACTER VARYING(256) NOT NULL,
    address_country     CHARACTER VARYING(256),
    CONSTRAINT registered_mail_issuing_sites_pkey PRIMARY KEY (id)
);
CREATE TABLE IF NOT EXISTS registered_mail_issuing_sites_entities
(
    id        SERIAL  NOT NULL,
    site_id   INTEGER NOT NULL,
    entity_id INTEGER NOT NULL,
    CONSTRAINT registered_mail_issuing_sites_entities_pkey PRIMARY KEY (id),
    CONSTRAINT registered_mail_issuing_sites_entities_unique_key UNIQUE (site_id, entity_id)
);
CREATE TABLE IF NOT EXISTS registered_mail_number_range (
    id SERIAL NOT NULL,
    type CHARACTER VARYING(15) NOT NULL,
    tracking_account_number CHARACTER VARYING(256) NOT NULL,
    range_start INTEGER NOT NULL,
    range_end INTEGER NOT NULL,
    creator INTEGER NOT NULL,
    creation_date timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    status CHARACTER VARYING(10) NOT NULL,
    current_number INTEGER,
    CONSTRAINT registered_mail_number_range_pkey PRIMARY KEY (id),
    CONSTRAINT registered_mail_number_range_unique_key UNIQUE (tracking_account_number)
);

CREATE TABLE IF NOT EXISTS registered_mail_resources (
    id SERIAL NOT NULL,
    res_id INTEGER NOT NULL,
    type CHARACTER VARYING(2) NOT NULL,
    issuing_site INTEGER NOT NULL,
    warranty CHARACTER VARYING(2) NOT NULL,
    letter BOOL NOT NULL DEFAULT FALSE,
    recipient jsonb NOT NULL,
    number INTEGER NOT NULL,
    reference TEXT,
    generated BOOL NOT NULL DEFAULT FALSE,
    deposit_id INTEGER,
    received_date TIMESTAMP WITHOUT TIME ZONE,
    return_reason CHARACTER VARYING(256),
    CONSTRAINT registered_mail_resources_pkey PRIMARY KEY (id),
    CONSTRAINT registered_mail_resources_unique_key UNIQUE (res_id)
);

CREATE TABLE attachment_types
(
    id SERIAL NOT NULL,
    type_id text NOT NULL,
    label text NOT NULL,
    visible BOOLEAN NOT NULL,
    email_link BOOLEAN NOT NULL,
    signable BOOLEAN NOT NULL,
    signed_by_default BOOLEAN NOT NULL,
    icon text,
    chrono BOOLEAN NOT NULL,
    version_enabled BOOLEAN NOT NULL,
    new_version_default BOOLEAN NOT NULL,
    CONSTRAINT attachment_types_pkey PRIMARY KEY (id),
    CONSTRAINT attachment_types_unique_key UNIQUE (type_id)
)
WITH (OIDS=FALSE);

CREATE TABLE tiles
(
    id SERIAL NOT NULL,
    user_id INTEGER NOT NULL,
    type text NOT NULL,
    view text NOT NULL,
    position INTEGER NOT NULL,
    color text,
    parameters jsonb DEFAULT '{}' NOT NULL,
    CONSTRAINT tiles_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

CREATE TABLE address_sectors
(
    id SERIAL NOT NULL,
    address_number CHARACTER VARYING(256),
    address_street CHARACTER VARYING(256),
    address_postcode CHARACTER VARYING(256),
    address_town CHARACTER VARYING(256),
    label CHARACTER VARYING(256),
    ban_id CHARACTER VARYING(256),
    CONSTRAINT address_sectors_key UNIQUE (address_number, address_street, address_postcode, address_town),
    CONSTRAINT address_sectors_pkey PRIMARY KEY (id)
)
    WITH (OIDS=FALSE);

-- Create a sequence for chronos and update value in parameters table
CREATE OR REPLACE FUNCTION public.increase_chrono(chrono_seq_name text, chrono_id_name text) returns table (chrono_id bigint) as $$
DECLARE
    retval bigint;
BEGIN
    -- Check if sequence exist, if not create
	IF NOT EXISTS (SELECT 0 FROM pg_class where relname = chrono_seq_name ) THEN
      EXECUTE 'CREATE SEQUENCE "' || chrono_seq_name || '" INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 START 1 CACHE 1;';
    END IF;
    -- Check if chrono exist in parameters table, if not create
    IF NOT EXISTS (SELECT 0 FROM parameters where id = chrono_id_name ) THEN
      EXECUTE 'INSERT INTO parameters (id, param_value_int) VALUES ( ''' || chrono_id_name || ''', 1)';
    END IF;
    -- Get next value of sequence, update the value in parameters table before returning the value
    SELECT nextval(chrono_seq_name) INTO retval;
	  UPDATE parameters set param_value_int = retval WHERE id =  chrono_id_name;
	  RETURN QUERY SELECT retval;
END;
$$ LANGUAGE plpgsql;

-- reset les chronos
DROP FUNCTION IF EXISTS reset_chronos;
-- Create a sequence for chronos and update value in parameters table
CREATE OR REPLACE FUNCTION public.reset_chronos() returns void as $$
DECLARE
  chrono record;
BEGIN
  -- Loop through each chrono found in parameters table
	FOR chrono IN (SELECT * FROM parameters WHERE id LIKE '%_' || extract(YEAR FROM current_date)) LOOP
    EXECUTE 'SELECT setVal(''' || CONCAT(chrono.id, '_seq') || ''', 1)';
    UPDATE parameters SET param_value_int = '1' WHERE id = chrono.id;
  END LOOP;
END
$$ LANGUAGE plpgsql;

CREATE TABLE IF NOT EXISTS difflist_roles (
  id SERIAL NOT NULL,
  role_id CHARACTER varying(32) UNIQUE NOT NULL,
  label CHARACTER varying(255) NOT NULL,
  keep_in_list_instance BOOL NOT NULL DEFAULT FALSE,
  CONSTRAINT roles_id_pkey PRIMARY KEY (id)
);
