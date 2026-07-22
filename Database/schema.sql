CREATE DATABASE IF NOT EXISTS smartsoft_saiverapp;
USE smartsoft_saiverapp;

CREATE TABLE usuarios (
    id         INT          NOT NULL,
    nickname   VARCHAR(30)  NOT NULL,
    nombre     VARCHAR(60)  NOT NULL DEFAULT '',
    email      VARCHAR(120) NOT NULL DEFAULT '',
    pwd        VARCHAR(128) NOT NULL,
    admin      INT          NOT NULL DEFAULT 0,
    bio_token  VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_nickname (nickname)
);
