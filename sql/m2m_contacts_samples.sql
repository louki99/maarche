DELETE FROM usergroups WHERE group_id = 'MAARCHTOGEC';
INSERT INTO usergroups (group_id,group_desc) VALUES ('MAARCHTOGEC', 'Envoi dématérialisé');
DELETE FROM usergroups_services WHERE group_id = 'MAARCHTOGEC';
INSERT INTO usergroups_services (group_id, service_id) VALUES ('MAARCHTOGEC', 'manage_numeric_package');

DELETE FROM security WHERE group_id = 'MAARCHTOGEC';
INSERT INTO security (group_id, coll_id, where_clause, maarch_comment) VALUES ('MAARCHTOGEC', 'letterbox_coll', '1=0', 'Aucun courrier');

DELETE FROM users WHERE user_id = 'cchaplin';
INSERT INTO users (user_id, password, firstname, lastname, mail, status, mode) VALUES ('cchaplin', '$2y$10$C.QSslBKD3yNMfRPuZfcaubFwPKiCkqqOUyAdOr5FSGKPaePwuEjG', 'Jean', 'WEBSERVICE', 'dev.maarch@maarch.org', 'OK', 'rest');
DELETE FROM usergroup_content WHERE user_id = 24;
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (24, 11, '');

DELETE FROM contacts where id >= 1000000;

-- INSTANCE A
INSERT INTO contacts VALUES (1000000, 1, 'Custom 1', 'Custom 1', 'Custom 1', NULL, NULL, '13', 'RUE LA PREFECTURE', NULL, NULL, '99000', 'MAARCH LES BAINS', NULL, NULL, NULL, '{"url": "http://cchaplin:maarch@127.0.0.1/MaarchCourrier/cs_custom_1/"}', NULL, 21, '2018-04-18 12:43:54.97424', '2020-03-24 15:06:58.16582', true, NULL, '{"m2m": "org_custom_1"}');
INSERT INTO contacts VALUES (1000001, 1, 'Custom 2', 'Custom 2', 'Custom 2', NULL, NULL, '13', 'RUE LA PREFECTURE', NULL, NULL, '99000', 'MAARCH LES BAINS', NULL, NULL, NULL, '{"url": "http://cchaplin:maarch@127.0.0.1/MaarchCourrier/cs_custom_2/"}', NULL, 21, '2018-04-18 12:43:54.97424', '2020-03-24 15:06:58.16582', true, NULL, '{"m2m": "org_custom_2"}');
INSERT INTO contacts VALUES (1000002, 1, 'Custom 3', 'Custom 3', 'Custom 3', NULL, NULL, '13', 'RUE LA PREFECTURE', NULL, NULL, '99000', 'MAARCH LES BAINS', NULL, NULL, NULL, '{"url": "http://cchaplin:maarch@127.0.0.1/MaarchCourrier/cs_custom_3/"}', NULL, 21, '2018-04-18 12:43:54.97424', '2020-03-24 15:06:58.16582', true, NULL, '{"m2m": "org_custom_3"}');

DO $$
BEGIN
	IF (SELECT current_database() = 'custom_1') THEN
		UPDATE entities set business_id = 'org_custom_1';
	END IF;
END $$;
DO $$
BEGIN
	IF (SELECT current_database() = 'custom_2') THEN
		UPDATE entities set business_id = 'org_custom_2';
	END IF;
END $$;
DO $$
BEGIN
	IF (SELECT current_database() = 'custom_3') THEN
		UPDATE entities set business_id = 'org_custom_3';
	END IF;
END $$;
