<?php

if (!defined("_EXPORT_SEDA_COMMENT"))
    define("_EXPORT_SEDA_COMMENT", "Export");
if (!defined("_EXPORT_SEDA_LIST"))
    define("_EXPORT_SEDA_LIST", "Liste des courriers à archiver");


if (!defined("_EXPORT_SEDA"))
    define("_EXPORT_SEDA","Transferer vos courriers");
if (!defined("_CHECK_ACKNOWLEDGEMENT"))
    define("_CHECK_ACKNOWLEDGEMENT","Vérification de l'accusé de reception");
if (!defined("_CHECK_REPLY"))
    define("_CHECK_REPLY","Vérification de la réponse au transfert");
if (!defined("_PURGE_LETTER"))
    define("_PURGE_LETTER","Purger le courrier apres l'archivage");
if (!defined("_RESET_LETTER"))
    define("_RESET_LETTER","Remise à zéro du circuit de traitement");


if (!defined("_EXPORT_SEDA_VIEW"))
    define("_EXPORT_SEDA_VIEW", "Voir le bordereau");

if (!defined("_INFORMATION_MESSAGE"))
    define("_INFORMATION_MESSAGE", "Information bordereau");

if (!defined("_PACKAGE_TITLE"))
    define("_PACKAGE_TITLE", "Titre du paquet");

if (!defined("_GENERATE_MESSAGE"))
    define("_GENERATE_MESSAGE", "Générer bordereau");

if (!defined("_MESSAGE_TITLE_EMPTY"))
    define("_MESSAGE_TITLE_EMPTY", "Titre du paquet vide");

if (!defined("_MESSAGE_IDENTIFIER"))
    define("_MESSAGE_IDENTIFIER", "Identifiant bordereau");
if (!defined("_ARCHIVAL_AGENCY_SIREN"))
    define("_ARCHIVAL_AGENCY_SIREN", "Numéro SIREN entité d'archive");
if (!defined("_TRANSFERRING_AGENCY_SIREN"))
    define("_TRANSFERRING_AGENCY_SIREN", "Numéro SIREN entité de transfert");

if (!defined("_INFORMATION_ARCHIVE"))
    define("_INFORMATION_ARCHIVE", "Information archive");
if (!defined("_ARCHIVE_IDENTIFIER"))
    define("_ARCHIVE_IDENTIFIER", "Identifiant archive");

if (!defined("_DESCRIPTION_LEVEL"))
    define("_DESCRIPTION_LEVEL", "Service de description");
if (!defined("_ITEM"))
    define("_ITEM", "Objet");
if (!defined("_RECEIVED_DATE"))
    define("_RECEIVED_DATE", "Date de reception");
if (!defined("_YEARS"))
    define("_YEARS", "an(s)");
if (!defined("_MONTHS"))
    define("_MONTHS", "mois");
if (!defined("_DAYS"))
    define("_DAYS", "jour(s)");
if (!defined("_APPRAISAL_RULE"))
    define("_APPRAISAL_RULE", "Règle de conservation");
if (!defined("_APPRAISAL_FINAL_DISPOSITION"))
    define("_APPRAISAL_FINAL_DISPOSITION", "Sort final");
if (!defined("_DESTROY"))
    define("_DESTROY", "Destruction");
if (!defined("_KEEP"))
    define("_KEEP", "Conservation");
if (!defined("_DOCUMENT_TYPE"))
    define("_DOCUMENT_TYPE", "Type de document");
if (!defined("_REPLY"))
    define("_REPLY", "Réponse");
if (!defined("_ATTACHMENT"))
    define("_ATTACHMENT", "Pièce jointe");
if (!defined("_SENT_DATE"))
    define("_SENT_DATE", "Date d'envoi");

if (!defined("_INFORMATION_ARCHIVE_CHILDREN"))
    define("_INFORMATION_ARCHIVE_CHILDREN", "Information archive enfant");

if (!defined("_ZIP"))
    define("_ZIP", "Télécharger Zip");
if (!defined("_SEND_MESSAGE"))
    define("_SEND_MESSAGE", "Transferer bordereau");
if (!defined("_VALIDATE"))
    define("_VALIDATE", "Valider");
if (!defined("_URLSAE"))
    define("_URLSAE", "> Système d'archivage <");

if (!defined("_RECEIVED_MESSAGE"))
    define("_RECEIVED_MESSAGE", "Conformité du bordereau confirmée par accusé de réception : ");

if (!defined("_ERROR_MESSAGE"))
    define("_ERROR_MESSAGE", "Bordereau non-reçu");

if (!defined("_DIRECTORY_MESSAGE_REQUIRED"))
    define("_DIRECTORY_MESSAGE_REQUIRED", "Répertoire des messages non configuré");

if (!defined("_TRANSFERRING_AGENCY_SIREN_REQUIRED"))
    define("_TRANSFERRING_AGENCY_SIREN_REQUIRED", "Numéro SIREN entité versante obligatoire");

if (!defined("_ARCHIVAL_AGENCY_SIREN_REQUIRED"))
    define("_ARCHIVAL_AGENCY_SIREN_REQUIRED", "Numéro SIREN entité d'archive obligatoire");

if (!defined("_ARCHIVAL_AGREEMENT_REQUIRED"))
    define("_ARCHIVAL_AGREEMENT_REQUIRED", "Convention d'archivage obligatoire");

if (!defined("_VALIDATE_MANUAL_DELIVERY"))
    define("_VALIDATE_MANUAL_DELIVERY", "Valider l'envoi manuel du bordereau");

if (!defined("_NO_LETTER_PURGE"))
    define("_NO_LETTER_PURGE", "Aucun courrier à supprimer");

if (!defined("_PURGE"))
    define("_PURGE", "courrier(s) supprimé(s)");

if (!defined("_ERROR_MESSAGE_ALREADY_SENT"))
    define("_ERROR_MESSAGE_ALREADY_SENT", "L'archivage d'un courrier sélectionné est déjà en cours, vous ne pouvez pas archiver deux fois le même courrier. Veuillez le désélectionner pour continuer. Numéro de courrier en cours d'archivage : ");

if (!defined("_ERROR_STATUS_SEDA"))
    define("_ERROR_STATUS_SEDA", "Le courrier selectionné n'est pas en cours d'archivage. Veuillez le désélectionner ou le transferer au système d'archivage. Numéro du courrier : ");

if (!defined("_ERROR_NO_ACKNOWLEDGEMENT"))
    define("_ERROR_NO_ACKNOWLEDGEMENT", "Aucun accusé de reception n'est referencé pour le courrier suivant : ");

if (!defined("_ERROR_NO_XML_ACKNOWLEDGEMENT"))
    define("_ERROR_NO_XML_ACKNOWLEDGEMENT", "L'accusé de reception n'est pas bien structuré. Numéro du courrier : ");

if (!defined("_ERROR_NO_REFERENCE_MESSAGE_ACKNOWLEDGEMENT"))
    define("_ERROR_NO_REFERENCE_MESSAGE_ACKNOWLEDGEMENT", "Aucun bordereau correspond à l'accusé de reception. Numéro du courrier : ");

if (!defined("_ERROR_WRONG_ACKNOWLEDGEMENT"))
    define("_ERROR_WRONG_ACKNOWLEDGEMENT", "L'accusé de reception n'est pas lié au bon courrier. Numéro du courrier : ");

if (!defined("_ERROR_NO_REPLY"))
    define("_ERROR_NO_REPLY", "Aucune réponse de transfert n'est referencé pour le courrier suivant : ");

if (!defined("_ERROR_NO_XML_REPLY"))
    define("_ERROR_NO_XML_REPLY", "La réponse de transfert n'est pas bien structuré. Numéro du courrier : ");

if (!defined("_ERROR_NO_REFERENCE_MESSAGE_REPLY"))
    define("_ERROR_NO_REFERENCE_MESSAGE_REPLY", "Aucun bordereau correspond à la réponse de transfert. Numéro du courrier : ");

if (!defined("_ERROR_WRONG_REPLY"))
    define("_ERROR_WRONG_REPLY", "La réponse de transfert n'est pas lié au bon courrier. Numéro du courrier : ");

if (!defined("_LETTER_NO_ARCHIVED"))
    define("_LETTER_NO_ARCHIVED", "Le courrier n'a pas été archivé. Veuillez regarder la réponse au transfert pour en connaitre la cause. Numéro du courrier : ");

if (!defined("_ERROR_LETTER_ARCHIVED"))
    define("_ERROR_LETTER_ARCHIVED", "Vous ne pouvez pas remettre à zéro un courrier archivé. Numéro du courrier : ");

if (!defined("_ERROR_ORIGINATOR_EMPTY"))
    define("_ERROR_ORIGINATOR_EMPTY", "Au moins un producteur doit être renseigné");

if (!defined("_ERROR_FILE_NOT_EXIST"))
    define("_ERROR_FILE_NOT_EXIST", "Tous les documents doivent être présent dans les zones de stockage");

if (!defined("_ERROR_REPLY_NOT_EXIST"))
    define("_ERROR_REPLY_NOT_EXIST", "La réponse au transfert doit être présente pour effectuer une action. Numéro du courrier : ");

if (!defined("_ERROR_EXTENSION_CERTIFICATE"))
    define("_ERROR_EXTENSION_CERTIFICATE", "Le certificat n'est pas dans le bon format (.crt ou .pem)");

if (!defined("_ERROR_UNKNOW_CERTIFICATE"))
    define("_ERROR_UNKNOW_CERTIFICATE", "Erreur avec le certificat SSL ou TLS.");

if (!defined("_UNKNOWN_TARGET"))
    define("_UNKNOWN_TARGET", "Cible inconnue");

if (!defined("_ERROR_MESSAGE_NOT_PRESENT"))
    define("_ERROR_MESSAGE_NOT_PRESENT", "Le bordereau de transfert n'est pas present.");