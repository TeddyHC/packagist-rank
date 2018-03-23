CREATE TABLE `packages` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `vendor` varchar(100) NOT NULL,
    `description` varchar(8000) DEFAULT NULL,
    `url` varchar(255) DEFAULT NULL,
    `repository` varchar(255) DEFAULT NULL,
    `downloads` int(11) NOT NULL DEFAULT '0',
    `favers` int(11) NOT NULL DEFAULT '0',
    `updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `isDeleted` tinyint(4) NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `nameIndex` (`name`),
    KEY `downloadIndex` (`downloads`),
    KEY `faverIndex` (`favers`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
