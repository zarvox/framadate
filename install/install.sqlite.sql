CREATE TABLE IF NOT EXISTS `comments` (
    `id_comment` INTEGER PRIMARY KEY NOT NULL,
    `id_sondage` char(16) not null,
    `comment` text not null,
    `usercomment` text
);

CREATE TABLE IF NOT EXISTS `sondage` (
    `id_sondage` char(16) NOT NULL PRIMARY KEY,
    `commentaires` text,
    `mail_admin` varchar(128) DEFAULT NULL,
    `nom_admin` varchar(64) DEFAULT NULL,
    `titre` text,
    `id_sondage_admin` char(24) DEFAULT NULL,
    `format` varchar(2) DEFAULT NULL,
    `mailsonde` tinyint(1) DEFAULT '0'
);

CREATE TABLE IF NOT EXISTS `sujet_studs` (
    `id_sondage` char(16) NOT NULL PRIMARY KEY,
    `sujet` text
);

CREATE TABLE IF NOT EXISTS `user_studs` (
    `id_users` INTEGER PRIMARY KEY NOT NULL,
    `nom` varchar(64) NOT NULL,
    `id_sondage` char(16) NOT NULL,
    `reponses` text NOT NULL
);
